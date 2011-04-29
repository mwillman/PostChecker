#!/usr/bin/php -q

<?php
/*
 * postchecker_server.php
 *
 * Server side HTML generator for PostChecker.  Checks the configured machines * every $update seconds, and regenerates the graphs.  Keeps the last five * results returned in the graphs by default.
 *
 * Author - Matt Willman - willman.matt@threepaw.com
 */

// includes
require_once('/path/to/postchecker_core.php'); 

// defaults
$stats = array();

// parse config
$servers = parse_config(&$update);

// get current queue counts
$stats = get_current_counts($stats, $servers);

// open the html file
$file = fopen("$pc_cache/postchecker.html", 'w') or trigger_error("cannot open file for writing", E_USER_ERROR);

// start web page generation
fwrite($file, "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"\n");
fwrite($file, "   \"http://www.w3.org/TR/html4/loose.dtd\">\n");
fwrite($file, "<html>\n");
fwrite($file, "<head>\n");
fwrite($file, "<meta http-equiv=\"refresh\" content=\"$update\">\n");
fwrite($file, "<title>PostChecker - 1.2</title>\n");
fwrite($file, "</head>\n");
fwrite($file, "<body>\n");
fwrite($file, "<h1>PostChecker - 1.2</h1>\n");
fwrite($file, "This page is updated every $update seconds<br>\n");
fwrite($file, "<hr noshade>\n");

// generate the graphs
foreach ($stats as $server => $trash)
{
	fwrite($file, "Server: $server\n");
	fwrite($file, "<center>\n");

	foreach ($stats[$server] as $queue => $number)
	{
		if ($queue != 'red_queue')
		{
			// set the filename
			$filename = "$server.$queue";

			// make the graph for the active queue
			generate_graph($server, $queue, $number, $stats[$server]['red_queue'][0], "$pc_cache/$filename");

			// make sure the webserver can read it
			chmod("$pc_cache/$filename.png", 0755);

			// include it in the web page
			fwrite($file, "<img src=\"$pc_webroot/$filename.png\"");

			// we're not updating the web page (just the graphs)
			// so we don't always have the current number
			fwrite($file, " alt=\"number of items in the $queue queue\">\n");
		}
	}

	fwrite($file, "</center>\n");
	fwrite($file, "<br>\n");
}

// end web page generation
fwrite($file, "</body>\n");
fwrite($file, "</html>\n");

// flush and close
fflush($file);
fclose($file);

// make sure the webserver can read it
chmod("$pc_cache/postchecker.html", 0755);

// re-generate the graphs every $update seconds
while (true)
{
	sleep($update);

	// update the counts
	$stats = get_current_counts($stats, $servers);

	foreach ($stats as $server => $trash)
	{
		foreach ($stats[$server] as $queue => $number)
		{
			if ($queue != 'red_queue')
			{
				// set the filename
				$filename = "$server.$queue";

				// make the graph for the active queue
				generate_graph($server, $queue, $number, $stats[$server]['red_queue'][0], "$pc_cache/$filename");

				// make sure the webserver can read it
				chmod("$pc_cache/$filename.png", 0755);
			}
		}
	}
}// while(true)

?>
