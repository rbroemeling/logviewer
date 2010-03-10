<?php
/**
 * Orwell Status Monitor
 *
 * A simple status monitor that parses through today's log files and determines
 * whether Orwell executed, how many e-mails it sent, and whether it finished
 * "on time" or not.
 *
 * Meant to be used as a passive service report to Nagios, via NSCA, in order
 * to allow for Nagios monitoring of Orwell execution runs.
 **/
include_once(dirname(__FILE__) . '/lib/Log/RubyLog.class.php');
include_once(dirname(__FILE__) . '/lib/LogSet.class.php');

if ($_SERVER)
{
	header("Content-type: text/plain");
	set_time_limit(600);
}

// Debug-mode: output extra information about the log handlers being used and
// the log files that are being opened/read/closed, as well as a summary of
// discovered orwell information after reading the logfiles.
if (! defined('DEBUG'))
{
	if ($_GET['debug'] || getenv('debug'))
	{
		define('DEBUG', true);
	}
	else
	{
		define('DEBUG', false);
	}
}

/**
 * Trivial utility function to setup the counters for an orwell "chunk" in the
 * $orwell_chunks array.
 **/
function ensure_orwell_chunk($i)
{
	global $orwell_chunks;
	
	if (! isset($orwell_chunks[$i]))
	{
		$orwell_chunks[$i] = array
		(
			'start' => array(),
			'stop' => array()
		);
	}
}

/**
 * Trivial utility function to output the information from this script in the
 * format that Nagios/NSCA expects (tab-delimited fields).
 **/
function nagios_service_report($return_code, $message)
{
	global $environment;
	
	$fields = array();
	$fields[] = "logmaster";                                    // Hostname.
	$fields[] = "Orwell: " . ucfirst(strtolower($environment)); // Service description.
	$fields[] = $return_code;                                   // Check result (0 = OK, 1 = WARNING, 2 = CRITICAL).
	$fields[] = $message;                                       // Plugin output (verbose description/message).

	echo join("\t", $fields) . "\n";
	exit;
}

$environment = 'live'; // beta, live, or stage
if ($_GET['environment'])
{
	$environment = $_GET['environment'];
}
if (getenv('environment'))
{
	$environment = getenv('environment');
}
$orwell_chunks = array();
$orwell_end_timestamp = null;
$orwell_start_timestamp = null;

$log_set = new LogSet($environment, 'ruby', strtotime('today 09:00'), strtotime('today 22:00'));
while ($entry = $log_set->gets())
{
	if (! $entry instanceof RubyLog)
	{
		continue;
	}
	if ($entry->ruby_component != 'orwell')
	{
		continue;
	}
	if (is_null($orwell_start_timestamp))
	{
		$orwell_start_timestamp = $entry->syslog_timestamp;
	}
	if (preg_match('/Adding Orwell task to ppq_tasks, server_ids => (\[[0-9, ]+\])/', $entry->line, $matches))
	{
		ensure_orwell_chunk($matches[1]);
		array_push($orwell_chunks[$matches[1]]['start'], $entry->syslog_timestamp);
	}
	elseif (preg_match('/Finished Orwell run, server_ids => (\[[0-9, ]+\])/', $entry->line, $matches))
	{
		ensure_orwell_chunk($matches[1]);
		array_push($orwell_chunks[$matches[1]]['stop'], $entry->syslog_timestamp);
	}
	elseif (stripos($entry->line, 'Completed Orwell run') !== false)
	{
		$orwell_end_timestamp = $entry->syslog_timestamp;
		break;
	}
}

if (DEBUG)
{
	echo 'Orwell Start Time: ' . (is_null($orwell_start_timestamp) ? 'NULL (Not Found)' : date('Y-m-d H:i:s', $orwell_start_timestamp)) . ".\n";
	echo 'Orwell End Time: ' . (is_null($orwell_end_timestamp) ? 'NULL (Not Found)' : date('Y-m-d H:i:s', $orwell_end_timestamp)) . ".\n";
	echo "\n";
	foreach (array_keys($orwell_chunks) as $chunk)
	{
		echo 'Orwell Chunk ' . $chunk . "\n";
		for ($i = 0; $i < max(count($orwell_chunks[$chunk]['start']), count($orwell_chunks[$chunk]['stop'])); $i++)
		{
			if (isset($orwell_chunks[$chunk]['start'][$i]))
			{
				echo "\t" . date('Y-m-d H:i:s', $orwell_chunks[$chunk]['start'][$i]);
			}
			else
			{
				echo "\t" . str_repeat(' ', 19);
			}
			if (isset($orwell_chunks[$chunk]['stop'][$i]))
			{
				echo "\t" . date('Y-m-d H:i:s', $orwell_chunks[$chunk]['stop'][$i]);
			}
			else
			{
				echo "\t" . str_repeat(' ', 19);
			}
			if (isset($orwell_chunks[$chunk]['start'][$i]) && isset($orwell_chunks[$chunk]['stop'][$i]))
			{
				echo "\t" . number_format($orwell_chunks[$chunk]['stop'][$i] - $orwell_chunks[$chunk]['start'][$i]) . 's elapsed';
			}
			echo "\n";
		}
		echo "\n";
	}	
}

if (is_null($orwell_start_timestamp))
{
	nagios_service_report(2, 'CRITICAL: Start of orwell run could not be found.');
}
if (is_null($orwell_end_timestamp))
{
	nagios_service_report(2, 'CRITICAL: End of orwell run could not be found.');
}
$orwell_elapsed = $orwell_end_timestamp - $orwell_start_timestamp;
if ($orwell_elapsed > (4 * 3600))
{
	nagios_service_report(1, 'WARNING: Orwell run took ' . number_format($orwell_elapsed) . ' seconds.');
}
foreach (array_keys($orwell_chunks) as $chunk)
{
	if (count($orwell_chunks[$chunk]['start']) > 1)
	{
		nagios_service_report(1, 'WARNING: Orwell server_id chunk ' . $chunk . ' executed more than once.');
	}
	if (count($orwell_chunks[$chunk]['start']) != count($orwell_chunks[$chunk]['stop']))
	{
		nagios_service_report(1, 'WARNING: Mismatched start/stop count (' . count($orwell_chunks[$chunk]['start']) . '/' . count($orwell_chunks[$chunk]['stop']) . ') for orwell server_id chunk ' . $chunk . '.');
	}
}
nagios_service_report(0, 'OK: Orwell completed ' . count($orwell_chunks) . ' server_id chunks in ' . number_format($orwell_elapsed) . ' seconds.');
?>
