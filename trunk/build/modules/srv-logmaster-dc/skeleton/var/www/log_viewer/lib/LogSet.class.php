<?php
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


	protected $current_timestamp = null;
	protected $end_timestamp = null;
	protected $environment = null;
	protected $hooks = array();
	protected $language = null;
	protected $log_file = null;
	

	public function __construct($environment, $language, $start_timestamp, $end_timestamp)
	{
		$this->current_timestamp = $start_timestamp;
		$this->end_timestamp = $end_timestamp;
		$this->environment = $environment;
		$this->hooks = array();
		$this->language = $language;
		$this->log_file = null;
	}


	public function gets()
	{
		while ($this->current_timestamp <= $this->end_timestamp)
		{
			if (is_null($this->log_file))
			{
				$log_path = sprintf('%s/%s/%s.%s.log', self::LOG_ROOT, $this->environment, strftime('%Y-%m-%d-%H', $this->current_timestamp), $this->language);
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
				while (($line !== false) && ($line->syslog_timestamp < $this->current_timestamp))
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
			$this->current_timestamp += 3600;
		}
		return false;
	}


	function register_hook($hook, $function)
	{
		if (! isset($this->hooks[$hook]))
		{
			$this->hooks[$hook] = array();
		}
		array_push($this->hooks, $function);
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
