/*
 *	queue_counts.go
 *
 * listens on port (7678), and returns number of items in the configured
 * subDirs
 *
 * Author - Matt Willman - willman.matt@threepaw.com
 */

package main

import (
	"fmt"
	"io/fs"
	"log"
	"log/syslog"
	"net"
	"path/filepath"
	"strings"
)

// by default, client will accept all connections
// update allowedIPs if you want to restrict access
var (
	debug				= true
	allowedIPs	= []string{"0.0.0.0"}
	port				= ":7678"
	spoolDir		= "/var/spool/postfix/"
	subDirs			= []string{"active", "deferred", "corrupt", "hold"}
)

func main() {
	// setup syslog output
	go init_syslog()

	// Start listening
	listener, err := net.Listen("tcp", port)
	if err != nil {
		log.Fatalf("Unable to bind to port %s: %v", port, err)
	}
	defer listener.Close()

	log.Printf("INFO: server started, listening on: %v", port)

	// wait for clients, handle clients
	for {
		conn, err := listener.Accept()
		if err != nil {
			log.Printf("ERROR: failed to accept connection: %v", err)
			continue
		}

		go handleClient(conn)
	}
}

// setup syslog emitter
func init_syslog() {
	syslogger, err := syslog.New(syslog.LOG_INFO|syslog.LOG_INFO, "queue_counts")
	if err != nil {
		log.Fatalf("ERROR: unable to connecto to syslog: %v", err)
	}
	log.SetOutput(syslogger)
}

// count and return the number of files per client if allowed
func handleClient(conn net.Conn) {
	defer conn.Close()

	if debug {
		log.Printf("DEBUG: complete remote addr is %s", conn.RemoteAddr().String())
	}

	clientIP := strings.Split(conn.RemoteAddr().String(), ":")[0]

	if !ipAllowed(clientIP) {
		log.Printf("WARNING: rejected connection from %s", clientIP)
		return
	}
	log.Printf("INFO: accepted connection from %s", clientIP)

	for _, dir := range subDirs {
		fullPath := filepath.Join(spoolDir, dir)
		count, err := countFiles(fullPath)
		if err != nil {
			log.Printf("ERROR: issue counting files in %s: %v", fullPath, err)
			continue
		}

		msg := fmt.Sprintf("%s:%d\n", dir, count)
		conn.Write([]byte(msg))
	}
}

// verify that the connecting client is either in the allowed list,
// or we have not configured security at all
func ipAllowed(ip string) bool {
	if debug {
		log.Printf("DEBUG: clientIP is %s", ip)
	}

	for _, allowed := range allowedIPs {
		if ip == allowed || "0.0.0.0" == allowed {
			return true
		}
	}
	return false
}

// count all the files in passed in directory name, and return the count
// skip any directories that have an error in the count
func countFiles(dir string) (int, error) {
	var count int
	err := filepath.WalkDir(dir, func(path string, d fs.DirEntry, err error) error {
		if err != nil {
			return nil // skip unreadable entries
		}
		if d.Type().IsRegular() {
			count++
		}
		return nil
	})
	return count, err
}
