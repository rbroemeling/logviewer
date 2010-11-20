<?php
/**
 * The MIT License (http://www.opensource.org/licenses/mit-license.php)
 * 
 * Copyright (c) 2010 Nexopia.com, Inc.
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 **/

include_once(dirname(__FILE__) . '/LogFile.class.php');

class LogSet
{
	/**********************************************************************
	 * The location of the log files.  In general, log file paths are
	 * expected to follow the below template:
	 *
	 * [LOG_ROOT]/[environment]/[YYYY]-[MM]-[DD]-[HH].[language].log
	 * 
	 *********************************************************************/
	const LOG_ROOT = '/var/log/development';


	protected $end_timestamp = null;
	protected $environment = null;
	protected $hooks = array();
	protected $language = null;
	protected $log_file = null;
	protected $log_timestamp = null;


	public function __construct($environment, $language, $start_timestamp, $end_timestamp)
	{
		// Sanitize incoming data for safety and sanity.
		$environment = preg_replace('/[^A-Za-z0-9]/', '', $environment);
		$language = preg_replace('/[^A-za-z0-9]/', '', $language);
		$start_timestamp = intval($start_timestamp);
		$end_timestamp = intval($end_timestamp);
		
		$this->end_timestamp = $end_timestamp;
		$this->environment = $environment;
		$this->hooks = array();
		$this->language = $language;
		$this->log_file = null;
		$this->log_timestamp = mktime(date('H', $start_timestamp), 0, 0, date('n', $start_timestamp), date('j', $start_timestamp), date('Y', $start_timestamp));
		$this->start_timestamp = $start_timestamp;
	}


	public function gets()
	{
		while ($this->log_timestamp <= $this->end_timestamp)
		{
			if (is_null($this->log_file))
			{
				$log_path = sprintf('%s/%s/%s.%s.log', self::LOG_ROOT, $this->environment, strftime('%Y-%m-%d-%H', $this->log_timestamp), $this->language);
				if (! file_exists($log_path))
				{
					// We don't have an uncompressed version
					// of the log, so we'll try to use a
					// compressed version (if one exists).
					if (file_exists($log_path . '.gz'))
					{
						$log_path .= '.gz';
					}
				}

				if (file_exists($log_path))
				{
					$this->log_file = new LogFile();
					if (! $this->log_file->open($log_path))
					{
						$this->trigger_hook('read_error', $log_path);
						$this->log_file = null;
					}
					else
					{
						$this->trigger_hook('opened_file', $this->log_file->path);
					}
				}
				else
				{
					$this->trigger_hook('missing_file', $log_path);
				}
			}

			if (! is_null($this->log_file))
			{
				$line = $this->log_file->gets();
				while (($line !== false) && ($line->syslog_timestamp < $this->start_timestamp))
				{
					$line = $this->log_file->gets();
				}
				if ($line !== false)
				{
					if ($line->syslog_timestamp <= $this->end_timestamp)
					{
						return $line;
					}
				}
				else
				{
					$this->trigger_hook('closed_file', $this->log_file->path);
				}
			}
			$this->log_file = null;
			$this->log_timestamp = strtotime('+1 hour', $this->log_timestamp);
		}
		return false;
	}


	function register_hook($hook, $function)
	{
		if (! isset($this->hooks[$hook]))
		{
			$this->hooks[$hook] = array();
		}
		array_push($this->hooks[$hook], $function);
	}

	
	function trigger_hook($hook, $argument)
	{
		if (! isset($this->hooks[$hook]))
		{
			return;
		}

		foreach ($this->hooks[$hook] as $function)
		{
			$function($argument);
		}
	}
}
