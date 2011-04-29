#!/usr/bin/php -q

<?php
/*
 * postchecker_cli.php
 *
 * CLI frontend to PostChecker.  Accepts one command line arg to present it's
 * output in machine parseable format.  Otherwise the output is human readable.
 *
 * ./postchecker_cli.php
 *	-- will generate human readable output
 *
 * ./postchecker_cli.php --parseable
 *	-- will generate machine parseable output for use in other tools
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
 * 07/2005	MBWillman
 */

// includes
require_once('/path/to/postchecker_core.php'); 

// defaults
$parseable = false;
$stats = array();
$trash = "";

// parse config
$servers = parse_config($trash);

// check command line args
if (isset($_SERVER['argv'][1]))
{
	if ($_SERVER['argv'][1] == '--parseable')
	{
		$parseable = true;
	}
}

// get the current queue counts
$stats = get_current_counts($stats, $servers);

// human readable gets a nice title
if (!$parseable)
{
	print "PostChecker 1.2\n";

	echo "Queue status as of ->";
	print strftime('%c');
	echo "<-\n\n";
}

// walk through the servers and print out the numbers
foreach ($stats as $server => $trash)
{
	if ($parseable)
	{
		print "$server:";
	}
	else
	{
		print "Server: $server";
	}

	foreach ($stats[$server] as $queue => $trash)
	{
		foreach ($stats[$server][$queue] as $number)
		{
			if ($parseable)
			{
				echo "$queue:$number:";
			}
			else
			{
				echo "  Queue: $queue = $number";
			}
		}
	}

	print "\n";
}// foreach ($stats as $server => $trash)

?>
