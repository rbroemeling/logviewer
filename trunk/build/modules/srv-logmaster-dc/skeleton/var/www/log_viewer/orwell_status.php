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
include_once(dirname(__FILE__) . '/lib/log/RubyLog.class.php');
include_once(dirname(__FILE__) . '/lib/LogSet.class.php');

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

$environment = 'live'; // beta, live, or stage
$orwell_chunks = array();
$orwell_end_timestamp = null;
$orwell_start_timestamp = null;

$log_set = new LogSet($environment, 'ruby', strtotime('midnight'), strtotime('midnight tomorrow - 1 second'));
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
	}
}
?>
