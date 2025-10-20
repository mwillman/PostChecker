#!/usr/bin/env python3
"""queue_counts.py
    listens on port {PC_PORT} and returns number of items in the configured
    queue directories {queue_dirs}.  Depends on Python3.7+ semantics

    Author - Matt Willman - willman.matt@threepaw.com
"""

import os
import socket
import sys
import syslog
import subprocess
from pathlib import Path

DEBUG = False
PC_PORT = 7678
# default is unrestricted - replace with restricted IPs if you want to
# limit connections
restricted_ips = ['0.0.0.0']
queue_dirs = ["active", "deferred", "corrupt", "hold"]

def find_spool_dir():
    """Determine the base location of the spool directory"""
    postconf_location = subprocess.run(["which postconf"],\
        capture_output=True, check=False, shell=True, text=True)

    if postconf_location.stdout:
        postconf_cmd = [postconf_location.stdout.rstrip(), '-h', 'queue_directory']
        syslog.syslog(f"INFO: found postconf, running {postconf_cmd}")
        spool_dir = subprocess.run(postconf_cmd, capture_output=True, check=False, text=True)
    else:
        syslog.syslog("ERROR: unable to determine postconf location")
        sys.exit(-1)

    if spool_dir.stdout:
        syslog.syslog(f"INFO: found spool directory at {spool_dir.stdout.rstrip()}")
        return spool_dir.stdout.rstrip()

    syslog.syslog(f"ERROR: cannot determine spool directory with {postconf_location.stdout}")
    sys.exit(-1)

def count_files(directory: str) -> int:
    """Count the number of files in each configured queue directory"""
    try:
        return sum(1 for entry in Path(directory).iterdir() if entry.is_file())
    except OSError as e:
        syslog.syslog(f"ERROR: counting queue files returned {e}")
        return -1

def handle_client(client_socket, client_ip, spool_dir):
    """Return queue counts to client"""
    if client_ip not in restricted_ips and "0.0.0.0" not in restricted_ips:
        syslog.syslog(f"WARNING: rejected connection from {client_ip}")
        client_socket.close()
        return

    syslog.syslog(f"INFO: accepted connection from {client_ip}")
    for queue in queue_dirs:
        dir_path = os.path.join(spool_dir, queue)
        if DEBUG:
            syslog.syslog(f"INFO: checking counts in queue directory {dir_path}")
        count = count_files(dir_path)
        message = f"{queue}:{count}\n"
        try:
            client_socket.sendall(message.encode())
        except OSError as e:
            syslog.syslog(f"ERROR: failed to send data to {client_ip}: {e}")

    client_socket.close()

def main():
    """Main loop, wait for client connections"""
    syslog.openlog(logoption=syslog.LOG_PID, facility=syslog.LOG_INFO)
    syslog.syslog("INFO: starting queue_counts.py")
    spool_dir = find_spool_dir()
    syslog.syslog("INFO: daemon started, listening for connections...")

    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as server_socket:
        server_socket.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
        server_socket.bind(('', PC_PORT))
        server_socket.listen(5)

        while True:
            try:
                client_socket, addr = server_socket.accept()
                client_ip = addr[0]
                handle_client(client_socket, client_ip, spool_dir)
            except OSError as e:
                syslog.syslog(f"ERROR: exception in handling client: {e}")

if __name__ == "__main__":
    main()
