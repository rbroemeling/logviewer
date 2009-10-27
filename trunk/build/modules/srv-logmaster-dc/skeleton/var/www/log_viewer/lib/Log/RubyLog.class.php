<?php
class RubyLog extends Log
{
	private static $handle_cache_id = null;
	private static $handle_cache_result = null;

	public $ruby_component = null;
	public $ruby_configuration = null;
	public $ruby_revision = null;
	public $ruby_error_level = null;
	public $ruby_error_message = null;
	public $ruby_pid = null;
	public $ruby_request_identifier = null;


	public function __construct($line)
	{
		$matches = array();

		parent::__construct($line);

		if (! $this->extra_data)
		{
			return;
		}

		# Regular Expression Map:
		#  '(live).(31564).(general).(critical): (.*)'
		#  '(live).(r26781.)(31564).(general).(critical): (.*)'
		#  '(live).(r26781.)(19426).(general).(error)( (req:19426:211)): (.*)'
		if (preg_match('!([a-z]+)\.(r\d+\.)?(\d+)\.([a-z_]+)\.([a-z]+)( +\(req:\d+:\d+.*?\))?: *(.*)!i', $this->extra_data, $matches))
		{
			$this->extra_data = null;

			$this->ruby_configuration = $matches[1];
			if (strlen($matches[2]))
			{
				$this->ruby_revision = substr($matches[2], 1, -1);
			}
			$this->ruby_pid = $matches[3];
			$this->ruby_component = $matches[4];
			$this->ruby_error_level = $matches[5];
			if (strlen($matches[6]))
			{
				$this->ruby_request_identifier = trim($matches[6]);
			}
			$this->ruby_error_message = $matches[7];

			switch ($this->ruby_error_level)
			{
				case 'critical':
				case 'error':
				case 'warning':
				case 'info':
				case 'debug':
				case 'spam':
					$this->error_level = $this->ruby_error_level;
					break;
			}
		}
	}


	public function __toString()
	{
		$string  = parent::__toString();
		if (DEBUG)
		{
			$string .= '<span class="debug">Begin ' . __CLASS__ . '</span>';
		}
		if (! is_null($this->ruby_configuration))
		{
			$string .= ' <span class="configuration">' . htmlspecialchars($this->ruby_configuration, ENT_QUOTES) . '</span>.';
		}
		if (! is_null($this->ruby_revision))
		{
			$string .= '<span class="revision">r' . htmlspecialchars($this->ruby_revision, ENT_QUOTES) . '</span>.';
		}
		if (! is_null($this->ruby_pid))
		{
			$string .= '<span class="pid">' . htmlspecialchars($this->ruby_pid, ENT_QUOTES) . '</span>.';
		}
		if (! is_null($this->ruby_component))
		{
			$string .= '<span class="ruby_component_' . $this->ruby_component . '">' . htmlspecialchars($this->ruby_component, ENT_QUOTES) . '</span>.';
		}
		if (! is_null($this->ruby_error_level) || ! is_null($this->ruby_request_identifier) || ! is_null($this->ruby_error_message))
		{
			$string .= '<span class="errorlevel_' . $this->error_level . '">';
			if (! is_null($this->ruby_error_level))
			{
				$string .= htmlspecialchars($this->ruby_error_level, ENT_QUOTES);
			}
			if (! is_null($this->ruby_request_identifier))
			{
				$string .= ' ' . htmlspecialchars($this->ruby_request_identifier, ENT_QUOTES);
			}
			if (! is_null($this->ruby_error_level) || ! is_null($this->ruby_request_identifier))
			{
				$string .= ': ';
			}
			if (! is_null($this->ruby_error_message))
			{
				$string .= htmlspecialchars($this->ruby_error_message, ENT_QUOTES);
			}
			$string .= '</span>';
		}
		if (DEBUG)
		{
			$string .= '<span class="debug">End ' . __CLASS__ . '</span>';
		}

		return $string;
	}


	public static function factory($line)
	{
		return new RubyLog($line);
	}


	public static function handles($line, $uuid)
	{
		if (self::$handle_cache_id != $uuid)
		{
			self::$handle_cache_id = $uuid;
			self::$handle_cache_result = false;
			if (parent::handles($line, $uuid))
			{
				# Regular Expression Map:
				#  ' live.31564.general.critical:'
				#  ' live.r26781.31564.general.critical:'
				if (preg_match('/ [a-z]+\.(r\d+\.)?\d+\.[a-z_]+\.[a-z]+\W/', $line))
				{
					self::$handle_cache_result = true;
				}
				# Regular Expression Map:
				#  'pagehandler.rb:653:in `'
				elseif (preg_match('/\w+\.rb:\d+:in `/', $line))
				{
					self::$handle_cache_result = true;
				}
			}
		}
		return self::$handle_cache_result;
	}


	public function matches_log_facilities()
	{
		if ((! $_GET['log_facilities']) || $_GET['log_facilities'][$this->ruby_component])
		{
			return true;
		}
		return false;
	}
	

	public static function priority()
	{
		# This class must be a higher priority than PHPLog, because they are
		# siblings and will often match the same lines.
		# We want this class to take priority when they do.
		return PHPLog::priority() + 1;
	}
}
?>
