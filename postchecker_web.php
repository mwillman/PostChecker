<?php
/*
 * postchecker_web.php
 *
 * Web front end to PostChecker.  Generates a one-time HTML page that generates graphs depicting the current queues
 *
 * Author - Matt Willman - willman.matt@threepaw.com
 *
 */

// includes
require_once('/path/to/postchecker_core.php'); 

// defaults
$stats = array();
$trash = "";

// parse config file
$servers = parse_config($trash);

// generate unique filenames
$unique = rand();

// get the current queue counts
$stats = get_current_counts($stats, $servers);

// start web page generation
echo "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\"\n";
echo "   \"http://www.w3.org/TR/html4/loose.dtd\">\n";
echo "<html>\n";
echo "<head>\n";
echo "<title>PostChecker - 1.2</title>\n";
echo "</head>\n";
echo "<body>\n";
echo "<h1>PostChecker - 1.2</h1>\n";
echo "<hr noshade>\n";

echo "Queue status as of ->";
print strftime('%c');
echo "<- <br><br>\n";

// generate the graphs
foreach ($stats as $server => $trash)
{
	echo "Server: $server\n";
	echo "<center>\n";

	foreach ($stats[$server] as $queue => $number)
	{
		if ($queue != 'red_queue')
		{
			// set the filename
			$filename = "$server.$queue.$unique";

			// make the graph for the active queue
			generate_graph($queue, $number, $stats[$server]['red_queue'][0], "$pc_cache/$filename");

			// include it in the web page
			echo "<img src=\"$pc_webroot/$filename.png\"";
			echo " alt=\"$number[0] items in the $queue queue\">\n";
		}
	}

	echo "</center>\n";
	echo "<br>\n";
}

// end web page generation
echo "</body>\n";
echo "</html>\n";

?>
