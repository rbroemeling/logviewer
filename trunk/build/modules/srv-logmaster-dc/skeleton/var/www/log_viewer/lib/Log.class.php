<?php
class Log
{
	private static $classes = null;
	private static $handle_id = 0;
	private static $last_input_timestamp = null;
	private static $last_input_timestring = null;
	private static $last_output_timestamp = null;
	private static $last_output_timestring = null;
	
	public $line = null;
	public $line_offset = null;

	public $client_ip = null;
	public $client_uid = null;
	public $error_level = null;
	public $syslog_timestamp = null;
	public $syslog_host = null;
	public $syslog_program = null;

	public $extra_data = null;


	public function __construct($line)
	{
		$fields = array();

		$this->line = $line;
		$this->error_level = 'unknown';

		# Regular Expression Map:
		#	'(Sep 11 13:07:27) (10.0.3.8) (.*)'
		#	'(Sep 11 13:07:27) (10.0.3.8/10.0.0.16) (.*)'
		if (preg_match('!^([a-z]{3} +\d+ +[0-9:]{8}) +([0-9./]+) +(.*)\n?$!i', $this->line, $fields))
		{
			if (strcmp(self::$last_input_timestring, $fields[1]))
			{
				self::$last_input_timestring = $fields[1];
				self::$last_input_timestamp = strtotime($fields[1]);
			}
			$this->syslog_timestamp = self::$last_input_timestamp;
			$this->syslog_host = $fields[2];

			$this->extra_data = $fields[3];
		}
		else
		{
			# This is note a default syslog line... we really don't know what to do
			# with it.
			return;
		}

		# Regular Expression Map:
		#	'(php-site:) (.*)'
		#	'(nexopia-parent - initializing:) (.*)'
		if (preg_match('!^([^:]+:) +(.*)!', $this->extra_data, $fields))
		{
			$this->syslog_program = $fields[1];

			$this->extra_data = $fields[2];
		}

		# Regular Expression Map:
		#  '([3443893/)(68.149.93.179]) (.*)'
		if (preg_match('!^(\[[0-9-]+/)([0-9.]+\]) +(.*)!', $this->extra_data, $fields))
		{
			$this->client_uid = $fields[1];
			$this->client_ip = $fields[2];

			$this->extra_data = $fields[3];
		}
		# Regular Expression Map:
		#  '([68.149.93.179]) (.*)'
		elseif (preg_match('!^(\[[0-9.]+\]) +(.*)!', $this->extra_data, $fields))
		{
			$this->client_ip = $fields[1];

			$this->extra_data = $fields[2];
		}
	}


	public function __toString()
	{
		if (is_null($this->error_level))
		{
			return $this->line;
		}

		$string  = '';
		if (DEBUG)
		{
			$string .= '<span class="debug">Begin ' . __CLASS__ . '</span>';
		}
		if (self::$last_output_timestamp != $this->syslog_timestamp)
		{
			self::$last_output_timestamp = $this->syslog_timestamp;
			self::$last_output_timestring = htmlspecialchars(strftime('%b %e %H:%M:%S', $this->syslog_timestamp), ENT_QUOTES);			
		}
		$string .= '<span class="date">' . self::$last_output_timestring . '</span> ';
		$string .= '<span class="host">' . htmlspecialchars($this->syslog_host, ENT_QUOTES) . '</span> ';
		$string .= '<span class="program">' . htmlspecialchars($this->syslog_program, ENT_QUOTES) . '</span> ';
		if (! is_null($this->client_uid))
		{
			$string .= '<span class="uid">' . htmlspecialchars($this->client_uid, ENT_QUOTES) . '</span>';
		}
		if (! is_null($this->client_ip))
		{
			$string .= '<span class="ip">' . htmlspecialchars($this->client_ip, ENT_QUOTES) . '</span> ';
		}
		$string .= htmlspecialchars($this->extra_data, ENT_QUOTES);
		if (DEBUG)
		{
			$string .= '<span class="debug">End ' . __CLASS__ . '</span>';
		}
		return $string;
	}


	public static function factory($line)
	{
		if (is_null(self::$classes))
		{
			self::initialize();
		}
		
		self::$handle_id++;
		foreach (self::$classes as $class_summary)
		{
			if (call_user_func(array($class_summary['class'], 'handles'), $line, self::$handle_id))
			{
				if (DEBUG)
				{
					echo '<div class="debug">Handler ' . $class_summary['class'] . ' chosen for line: "' . $line . '"</div>' . "\n";
				}
				return new $class_summary['class']($line);
			}
		}

		if (DEBUG)
		{
			echo '<div class="debug">Handler ' . __CLASS__ . ' chosen for line: "' . $line . '"</div>' . "\n";
		}
		return new Log($line);
	}


	public static function handles($line, $uuid)
	{
		return true;
	}


	private static function initialize()
	{
		foreach (scandir(dirname(__FILE__) . '/Log') as $file)
		{
			if (! is_file(dirname(__FILE__) . '/Log/' . $file))
			{
				continue;
			}
			include_once(dirname(__FILE__) . '/Log/' . $file);
		}

		self::$classes = array();
		foreach (get_declared_classes() as $class)
		{
			if (is_subclass_of($class, __CLASS__))
			{
				$class_summary = array();
				$class_summary['class'] = $class;
				$class_summary['priority'] = call_user_func(array($class_summary['class'], 'priority'));
				array_push(self::$classes, $class_summary);
			}
		}
		uasort
		(
			self::$classes,
			create_function
			(
				'$a,$b',
				'
					if ($a["priority"] == $b["priority"])
					{
						return 0;
					}
					return ($a["priority"] < $b["priority"]) ? 1 : -1;
				'
			)
		);
	}


	public function matches_log_facilities()
	{
		return true;
	}
	
	
	public function matches_log_levels()
	{
		if ((! $_GET['log_levels']) || $_GET['log_levels'][$this->error_level])
		{
			return true;
		}
		return false;
	}
	
	
	public function matches_filters()
	{
		if (! $_GET['filter'])
		{
			return true;
		}
		
		$filter_results = array();
		$j = count($_GET['filter']);
		for ($i = 0; $i < $j; $i++)
		{
			if (! $_GET['filter'][$i])
			{
				$filter_results[$i] = (1 XOR $_GET['negate_filter'][$i]);
			}
			elseif (preg_match($_GET['filter'][$i], $this->line) XOR $_GET['negate_filter'][$i])
			{
				$filter_results[$i] = 1;
			}
			else
			{
				$filter_results[$i] = 0;
			}
		}

		$j = count($filter_results);
		for ($i = 0; $i < $j - 1; $i++)
		{
			if ($_GET['logic_filter'][$i] == 'AND')
			{
				$filter_results[$i + 1] = $filter_results[$i] && $filter_results[$i + 1];
				unset($filter_results[$i]);
			}
		}

		return (array_sum($filter_results) > 0);
	}


	public function matches_source_hosts()
	{
		if ((! $_GET['source_hosts']) || $_GET['source_hosts'][$this->syslog_host])
		{
			return true;
		}
		return false;
	}


	public static function priority()
	{
		return 0;
	}
}
?>
