/*
 * queue_counts.c
 *
 * Alternative client side daemon for PostChecker.  Returns the number of
 * items in the configured spool directories
 *
 *
 * Copyright (c) 2005 Matt Willman
 * ALL RIGHTS RESERVED
 *
 * Contact: willman.matt@threepaw.com
 *
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, write to the Free Software Foundation, Inc., 59 Temple
 * Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * Initial release
 * 07/2005       MBWillman
 *
 */


// includes
#include <arpa/inet.h>
#include <dirent.h>
#include <errno.h>
#include <netinet/in.h>
#include <signal.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/socket.h>
#include <sys/types.h>
#include <sys/wait.h>
#include <unistd.h>
#include <syslog.h>

// defines
#define PC_PORT 7678
#define RESTRICTED 1

// functions
int dircount(char *);

// main
int main()
{
	int i, current_count, sock_fd, client_fd, sin_size, ip_matches;

	int yes = 1;

	char spool[] = "/var/spool/postfix/";
	char *dirs[] = {"active", "deferred", "corrupt", "hold"};
	char *restricted[] = {"0.0.0.0"};
	char *current_dir, *current_string;

	struct sockaddr_in my_addr;
	struct sockaddr_in client_addr;

	pid_t pid;

	current_dir = malloc(255);
	current_string = malloc(255);

	// open the connection to syslog
	openlog("queue_counts", LOG_PID, LOG_LOCAL1);

	// security is a good thing
	if (RESTRICTED && (strcmp("0.0.0.0", restricted[0]) == 0))
	{
		syslog(LOG_ERR, "you must configure a server IP address, exiting...\n");
		exit(-1);
	}

	// fork off
	if ((pid = fork()) < 0)
	{
		syslog(LOG_ERR, "unable to fork new child process, exiting...\n");
		exit(-1);
	}
	else if (pid != 0)
	{
		// parent exits
		syslog(LOG_INFO, "starting normal execution\n");
		exit(0);
	}

	// child

	// session leader
	setsid();
	
	// change working directory
	chdir("/");

	// clear umask
	umask(0);

	// open the socket
	if ((sock_fd = socket(AF_INET, SOCK_STREAM, 0)) == -1)
	{
		syslog(LOG_ERR, "unable to open socket %d, exiting...", PC_PORT);
		exit(-1);
	}

	// boilerplate
	my_addr.sin_family = AF_INET;
	my_addr.sin_port = htons(PC_PORT);
	my_addr.sin_addr.s_addr = htonl(INADDR_ANY);
	memset(&(my_addr.sin_zero), '\0', 8);

	// allow socket reuse
	if (setsockopt(sock_fd, SOL_SOCKET, SO_REUSEADDR, &yes,sizeof(int)) == -1)
	{
		syslog(LOG_ERR, "unable to set socket options, exiting...");
		exit(-1);
	}

	// bind to the socket
	if (bind(sock_fd, (struct sockaddr *)&my_addr, sizeof(struct sockaddr)) == -1)
	{
		syslog(LOG_ERR, "unable to bind to socket %d, exiting...", PC_PORT);
		exit(-1);
	}

	// maximum of 5 backlog
	if (listen(sock_fd, 5) == -1)
	{
		syslog(LOG_ERR, "unable to set backlog queue on socket %d, exiting...", PC_PORT);
		exit(-1);
	}

	// sit here and wait
	while(1)
	{
		sin_size = sizeof(struct sockaddr);
		if ((client_fd = accept(sock_fd, (struct sockaddr *)&client_addr, &sin_size)) == -1)
		{
			// couldn't accept for some reason
			syslog(LOG_WARNING, "unable to accept connection from %s on socket %d, resuming operation", inet_ntoa(client_addr.sin_addr), PC_PORT);
			continue;
		}
		else
		{
			// fake out the check below if we're not in RESTRICTED mode
			ip_matches = 1;

			if (RESTRICTED)
			{
				ip_matches = 0;

				// allow for multiple connecting IP addresses
				for (i = 0; i < (sizeof(restricted)/sizeof(restricted[0])); i++)
				{
					if (strcmp(restricted[i], inet_ntoa(client_addr.sin_addr)) == 0)
					{
						ip_matches = 1;
					}
				}

				if (!ip_matches)
				{
					// if IP's don't match, close the socket
					syslog(LOG_ERR, "rejected connection from %s, resuming operation", inet_ntoa(client_addr.sin_addr)); 
					close(client_fd);
				}
				else
				{
					syslog(LOG_INFO, "accepted connection from %s", inet_ntoa(client_addr.sin_addr));
				}
			}

			// if we reject the connection, don't bother checking the queues
			if (ip_matches)
			{
				// you should be able to change the spool directories
				// without changing the loop parameters
				for (i = 0; i < (sizeof(dirs)/sizeof(dirs[0])); i++)
				{
					// construct the current queue dir
					current_dir = strcpy(current_dir, spool);
					current_dir = strcat(current_dir, dirs[i]);

					// get the count
					current_count = dircount(current_dir);

					// format the output string
					sprintf(current_string, "%s:%d\n", dirs[i], current_count);

					// send it back to the client
					send(client_fd, current_string, strlen(current_string), 0);
				}

				// close the connection
				close(client_fd);
			}
		}
	}// while(1)
}// int main()


/*
 * int dircount(char *)
 *
 * accept a directory name, and return the number of files within it
 */

int dircount(char *current_dir)
{
	int count = 0;

	struct dirent *dp;
	DIR *dfd;

	// open the directory
	if ((dfd = opendir(current_dir)) == NULL)
	{
		syslog(LOG_ERR, "unable to open spool directory %s, skipping", current_dir);
		return(-1);
	}

	// iterate through the directory and count up the files
	while ((dp = readdir(dfd)) != NULL)
	{
		if (strcmp(dp->d_name, ".") == 0
			|| strcmp(dp->d_name, "..") == 0)
		{
			continue;
		}

		count++;
	}

	// be a good citizen
	closedir(dfd);

	return(count);
}// int dircount(char *current_dir)
