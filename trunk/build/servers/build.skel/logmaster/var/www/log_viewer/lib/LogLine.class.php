<?php
class LogLine implements iLogLine
{
	private static $subclasses = null;

	protected $line = null;
	protected $line_offset = null;

	protected $client_ip = null;
	protected $client_uid = null;
	protected $error_level = null;
	protected $syslog_date = null;
	protected $syslog_host = null;
	protected $syslog_program = null;

	protected $extra_data = null;


	public function __construct($line)
	{
		$fields = array();
		$matches = array();

		$this->line = rtrim($line, "\n");
		$this->error_level = 'unknown';

		# Regular Expression Map:
		#	'(Sep 11 13:07:27) (10.0.3.8) (PHP error:) (.*)'
		#	'(Sep 11 13:07:27) (10.0.3.8/10.0.0.16) (nexopia-parent - initializing:) (.*)'
		if (preg_match('!^([a-z]{3} +\d+ +[0-9:]{8}) +([0-9./]+) +([^:]+:) +(.*)!i', $this->line, $fields))
		{
			# Alright, this appears to be a log in the default syslog format.
			$this->syslog_date = $fields[1];
			$this->syslog_host = $fields[2];
			$this->syslog_program = $fields[3];

			# Regular Expression Map:
			#  '([3443893/)(68.149.93.179]) (.*)'
			if (preg_match('!(\[[0-9-]+/)([0-9.]+\]) +(.*)!', $fields[4], $matches))
			{
				$this->client_uid = $matches[1];
				$this->client_ip = $matches[2];
				$this->extra_data = $matches[3];
			}
			# Regular Expression Map:
			#  '([68.149.93.179]) (.*)'
			elseif (preg_match('!(\[[0-9.]+\]) +(.*)!', $fields[4], $matches))
			{
				$this->client_ip = $matches[1];
				$this->extra_data = $matches[2];
			}
			else
			{
				$this->extra_data = $fields[4];
			}
		}
	}


	public function __toString()
	{
		if (is_null($this->error_level))
		{
			return $this->line;
		}

		$string  = '';
		$string .= "<span class='date'>" . htmlspecialchars($this->syslog_date, ENT_QUOTES) . "</span> ";
		$string .= "<span class='host'>" . htmlspecialchars($this->syslog_host, ENT_QUOTES) . "</span> ";
		$string .= "<span class='program'>" . htmlspecialchars($this->syslog_program, ENT_QUOTES) . "</span> ";
		if (! is_null($this->client_uid))
		{
			$string .= "<span class='uid'>" . htmlspecialchars($this->client_uid, ENT_QUOTES) . "</span>";
		}
		if (! is_null($this->client_ip))
		{
			$string .= "<span class='ip'>" . htmlspecialchars($this->client_ip, ENT_QUOTES) . "</span> ";
		}
		$string .= htmlspecialchars($this->extra_data, ENT_QUOTES);
		return $string;
	}


	public static function factory($line)
	{
		$owner = array();
		$owner['priority'] = LogLine::priority();
		$owner['class'] = __CLASS__;

		if (is_null(LogLine::$subclasses))
		{
			LogLine::initialize();
		}

		foreach (LogLine::$subclasses as $subclass)
		{
			if (call_user_func(array($subclass, 'handles'), $line))
			{
				$subclass_priority = call_user_func(array($subclass, 'priority'));
				if ($subclass_priority > $owner['priority'])
				{
					$owner['priority'] = $subclass_priority;
					$owner['class'] = $subclass;
				}
			}
		}

		if ($owner['class'] == __CLASS__)
		{
			return new LogLine($line);
		}
		return call_user_func(array($owner['class'], 'factory'), $line);
	}


	public function get_offset()
	{
		return $this->line_offset;
	}


	public static function handles($line)
	{
		return true;
	}


	private static function initialize()
	{
		foreach (scandir(dirname(__FILE__) . '/LogLine') as $file)
		{
			if (! is_file(dirname(__FILE__) . '/LogLine/' . $file))
			{
				continue;
			}
			include_once(dirname(__FILE__) . '/LogLine/' . $file);
		}

		LogLine::$subclasses = array();
		foreach (get_declared_classes() as $class)
		{
			if (is_subclass_of($class, 'LogLine'))
			{
				array_push(LogLine::$subclasses, $class);
			}
		}
	}


	public function matches_filters()
	{
		$filter_results = array();

		for ($i = 0; $i < count($_GET['filter']); $i++)
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
		for ($i = 0; $i < $j; $i++)
		{
			if ($_GET['logic_filter'][$i] == 'AND')
			{
				$filter_results[$i + 1] = $filter_results[$i] && $filter_results[$i + 1];
				unset($filter_results[$i]);
			}
		}

		return (array_sum($filter_results) > 0);
	}


	public static function priority()
	{
		return 0;
	}


	public function set_offset($line_offset)
	{
		$this->line_offset = $line_offset;
	}
}
?>
