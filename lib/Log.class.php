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

class Log
{
	private static $cached_filters = null;
	private static $classes = null;
	public static $debug = false;
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
		if (preg_match('!^([a-z]{3} +\d+ +[0-9:]{8}) +([0-9./]+) +(.*)\s*$!i', $this->line, $fields))
		{
			if (strcmp(self::$last_input_timestring, $fields[1]))
			{
				self::$last_input_timestring = $fields[1];
				self::$last_input_timestamp = strtotime($fields[1]);
				if (self::$last_input_timestamp > time())
				{
					# If our timestamp is higher than the current time, then
					# we are dealing with a log entry that occurred *last year*,
					# so adjust it accordingly.
					$t = localtime(self::$last_input_timestamp);
					self::$last_input_timestamp = mktime($t[2], $t[1], $t[0], $t[4] + 1, $t[3], $t[5] + 1900 - 1);
					unset($t);
				}
			}
			$this->syslog_timestamp = self::$last_input_timestamp;
			$this->syslog_host = $fields[2];

			$this->extra_data = $fields[3];
		}
		else
		{
			# This is not a default syslog line... we really don't know what to do
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
		#  '([/)(68.149.93.179]) (.*)'
		#  '([3443893/)(68.149.93.179]) (.*)'
		if (preg_match('!^(\[[0-9-]*/)([0-9.]+\]) +(.*)!', $this->extra_data, $fields))
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
		if (self::$debug)
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
		if (! is_null($this->syslog_program))
		{
			$string .= '<span class="program">' . htmlspecialchars($this->syslog_program, ENT_QUOTES) . '</span> ';
		}
		if (! is_null($this->client_uid))
		{
			$string .= '<span class="uid">' . htmlspecialchars($this->client_uid, ENT_QUOTES) . '</span>';
		}
		if (! is_null($this->client_ip))
		{
			$string .= '<span class="ip">' . htmlspecialchars($this->client_ip, ENT_QUOTES) . '</span> ';
		}
		$string .= htmlspecialchars($this->extra_data, ENT_QUOTES);
		if (self::$debug)
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
				if (self::$debug)
				{
					echo '<div class="debug">Handler ' . $class_summary['class'] . ' chosen for line: "' . $line . '"</div>' . "\n";
				}
				return new $class_summary['class']($line);
			}
		}

		if (self::$debug)
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
		if ((! $_GET['log_facilities']) || $_GET['log_facilities']['unknown'])
		{
			return true;
		}
		return false;
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
		if (is_null(self::$cached_filters))
		{
			self::$cached_filters = array();
			if ($_GET['filter'])
			{
				$i = 0;
				self::$cached_filters[$i] = array();
				for ($j = 0; $j < count($_GET['filter']); $j++)
				{
					self::$cached_filters[$i][] = array
					(
						'filter' => $_GET['filter'][$j],
						'inverted' => $_GET['negate_filter'][$j],
						'type' => (preg_match('!^/.*/$!', $_GET['filter'][$j]) ? 'regex' : 'plain')
					);
					if ($_GET['logic_filter'][$j] == 'OR')
					{
						$i++;
						self::$cached_filters[$i] = array();
					}
				}
				if (! self::$cached_filters[$i])
				{
					unset(self::$cached_filters[$i]);
				}
			}
		}
		if (! self::$cached_filters)
		{
			return true;
		}
		
		for ($i = 0, $j = count(self::$cached_filters); $i < $j; $i++)
		{
			# If the line matches any one of the groups of cached filters,
			# then it is a match.  A 'group' is a series of AND'ed filters --
			# so a line only matches a group if it matches every filter in it.
			$match = true;
			for ($k = 0, $m = count(self::$cached_filters[$i]); $k < $m; $k++)
			{
				$filter = self::$cached_filters[$i][$k]['filter'];
				$filter_type = self::$cached_filters[$i][$k]['type'];
				$inverted = self::$cached_filters[$i][$k]['inverted'];
				if (! $filter)
				{
					# Empty filter text matches everything... unless it is inverted, in
					# which case it doesn't match anything.
					if ($inverted)
					{
						$match = false;
						break;
					}
					continue;
				}
				if ($filter_type == 'regex')
				{
					if (preg_match($filter, $this->line) XOR $inverted)
					{
						continue;
					}
					$match = false;
					break;
				}
				else
				{
					if ((stripos($this->line, $filter) !== false) XOR $inverted)
					{
						continue;
					}
					$match = false;
					break;
				}
			}
			if ($match)
			{
				return true;
			}
		}
		return false;
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
