<?php
/*
 * postchecker_core.php
 *
 * Contains all the core functions used by the frontends
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
 * Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, write to the Free Software Foundation, Inc., 59 Temple
 * Place, Suite 330, Boston, MA 02111-1307 USA
 *
 * Initial release
 * 07/2005	MBWillman
 */


// includes
include ("/path/to/jpgraph.php");
include ("/path/to/jpgraph_bar.php");

// defaults
$pc_cache = "/path/to/writeable/apache/temp/directory";
$pc_webroot = "/truncated/web/path/to/$pc_cache";
$debug = false;

// set local error handler
set_error_handler('error_hand');


/*
 * function parse_config()
 *
 * parse the configuration file
 */

function parse_config($update)
{
	// default to 60 if not configured
	$default_update = 60;

	// default to 2500 if not configured
	$red_queue = 25;

	// location of the config file
	$config_file = "/path/to/postchecker.cfg";

	// read in the config file
	if (fopen($config_file, 'r'))
	{
		$config = parse_ini_file($config_file);

		// set $update if !isset
		if (!(isset($config['update'])))
		{
			$update = $default_update;
		}
		else
		{
			$update = $config['update'];
		}

		// set $red_queue if !isset
		if (!(isset($config['red_queue'])))
		{
			$config['red_queue'] = $red_queue;
		}

		// that's not good
		if (count($config) == 0)
		{
			trigger_error("no monitored servers configured", E_USER_ERROR);
		}

		// get the servers
		reset($config);

		while (list($key, $red_queue) = each($config))
		{
			if (!(preg_match('/(update|red_queue)/', $key)))
			{
				if ($config[$key] == "")
				{
					$config[$key] = $config['red_queue'];
				}

				// populate the server array
				$servers[$key] = $config[$key];
			}
		}

		if (count($servers) == 0)
		{
			trigger_error("no monitored servers configured", E_USER_ERROR);
		}
	}
	else
	{
		trigger_error("cannot open configuration file", E_USER_ERROR);
	}

	return $servers;
}// parse_config($update)


/*
 * get_current_counts()
 *
 * get the current queue counts from all the configured servers
 */

function get_current_counts($stats, $servers)
{
	// connect to all configured machines
	while (list($key, $red_queue) = each($servers))
	{
		$socket = fsockopen($key, 7678, $errno, $errstr, 10);

		if (!$socket)
		{
			// whoopsie
			trigger_error("unable to open socket", E_USER_ERROR);
		}
		else
		{
			while (!feof($socket))
			{
				$current = rtrim(fgets($socket));

				if ($current)
				{
					// parse the input
					list($queue, $number) = explode(":", $current);

					// update the queue numbers
					if (isset($stats[$key][$queue]))
					{
						// make sure we shift the array
						if (count($stats[$key][$queue]) == 5)
						{
							$trash = array_shift($stats[$key][$queue]);
						}
							
						// and now add it in
						$stats[$key][$queue] = array_merge($stats[$key][$queue], array($number));
					}
					else
					{	
						// first time ever through the loop
						$stats[$key][$queue] = array($number);
					}
				}
			}

			fclose($socket);

			// add the red_queue value
			$stats[$key]['red_queue'] = array($red_queue);
		}
	}// while (list($key, $red_queue) = each($servers))

	return $stats;
}// get_current_counts()


/*
 * generate_graph($l_server, $l_queue, $l_number, $l_red_queue, $filename)
 *
 * generate the graph from input
 */

function generate_graph($l_server, $l_queue, $l_number, $l_red_queue, $filename)
{
	// graph setup
	$current_graph = new Graph(200,200);
	$current_graph->SetScale("textint");
	$current_graph->SetShadow();
	$current_graph->yaxis->scale->SetGrace(10);
	$current_graph->SetMargin(50,20,0,40);

	// for every number in array($l_number), create a barplot
	foreach ($l_number as $current_number)
	{
		$current_array = array($current_number);

		$current_plot = new BarPlot($current_array);
		$current_plot->value->Show();
		$current_plot->value->SetFormat('%d');

		// prettify the formatting
		if ($current_number != 0)
		{
			$current_plot->SetValuePos('center');
		}
		
		// are we green, yellow, or red?
		if ($current_number >= $l_red_queue)
		{
			$current_plot->SetFillColor('red');
		}
		else
		{
			if ($current_number >= ($l_red_queue * .75))
			{
				$current_plot->SetFillColor('yellow');
			}
			else
			{
				$current_plot->SetFillColor('green');
			}
		}

		// first time through the loop?
		if (isset($current_group_array))
		{
			$current_group_array = array_merge($current_group_array, array($current_plot));
		}
		else
		{
			$current_group_array = array($current_plot);
		}
	}

	// add up all the barplots
	$current_group_plot = new GroupBarPlot($current_group_array);
	$current_group_plot->SetWidth(0.8);
	$current_graph->Add($current_group_plot);

	$current_graph->title->Set($l_queue);
	$current_graph->yaxis->title->Set("Items");
	$current_graph->yaxis->title->SetMargin(10);

	// assumes your default output is png
	$current_graph->Stroke("$filename.png");

}// generate_graph($l_server, $l_queue, $l_number, $l_red_queue, $filename)


/*
 * function error_hand($errno, $errstr, $errfile, $errline)
 *
 * standard error handling function
 */

function error_hand($errno, $errstr, $errfile, $errline)
{
	global $debug;

	if ($debug)
	{
		echo "errno: $errno<br>";
		echo "errstr: $errstr<br>";
		echo "errfile: $errfile<br>";
		echo "errline: $errline<br>";
		
		exit(1);
	}

	echo "FATAL ERROR: $errstr\n";
	exit(1);

}// function error_hand($errno, $errstr, $errfile, $errline)

?>
