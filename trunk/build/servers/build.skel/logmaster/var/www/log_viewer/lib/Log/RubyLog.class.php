<?php
class RubyLog extends Log
{
	protected $ruby_component = null;
	protected $ruby_configuration = null;
	protected $ruby_error_level = null;
	protected $ruby_error_message = null;
	protected $ruby_pid = null;
	protected $ruby_request_identifier = null;


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
		#  '(live).(19426).(general).(error)( (req:19426:211)): (.*)'
		if (preg_match('!([a-z]+)\.(\d+)\.([a-z]+)\.([a-z]+)( +\(req:\d+:\d+.*?\))?: *(.*)!i', $this->extra_data, $matches))
		{
			$this->extra_data = null;

			$this->ruby_configuration = $matches[1];
			$this->ruby_pid = $matches[2];
			$this->ruby_component = $matches[3];
			$this->ruby_error_level = $matches[4];
			if (strlen($matches[5]))
			{
				$this->ruby_request_identifier = trim($matches[5]);
			}
			$this->ruby_error_message = $matches[6];

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
		if (defined('DEBUG') && DEBUG)
		{
			$string .= '<span class="debug">Begin ' . __CLASS__ . '</span>';
		}
		if (! is_null($this->ruby_pid))
		{
			$string .= " <span class='pid'>" . htmlspecialchars($this->ruby_pid, ENT_QUOTES) . "</span>.";
		}
		if (! is_null($this->ruby_component))
		{
			$string .= "<span class='ruby_component_" . $this->ruby_component . "'>" . htmlspecialchars($this->ruby_component, ENT_QUOTES) . "</span>.";
		}
		if (! is_null($this->ruby_error_level) || ! is_null($this->ruby_request_identifier) || ! is_null($this->ruby_error_message))
		{
			$string .= "<span class='errorlevel_" . $this->error_level . "'>";
			if (! is_null($this->ruby_error_level))
			{
				$string .= htmlspecialchars($this->ruby_error_level, ENT_QUOTES);
			}
			if (! is_null($this->ruby_request_identifier))
			{
				$string .= " " . htmlspecialchars($this->ruby_request_identifier, ENT_QUOTES);
			}
			if (! is_null($this->ruby_error_level) || ! is_null($this->ruby_request_identifier))
			{
				$string .= ": ";
			}
			if (! is_null($this->ruby_error_message))
			{
				$string .= htmlspecialchars($this->ruby_error_message, ENT_QUOTES);
			}
			$string .= "</span>";
		}
		if (defined('DEBUG') && DEBUG)
		{
			$string .= '<span class="debug">End ' . __CLASS__ . '</span>';
		}

		return $string;
	}


	public static function factory($line)
	{
		return new RubyLog($line);
	}


	public static function handles($line)
	{
		if (parent::handles($line))
		{
			# Regular Expression Map:
			#  ' live.31564.general.critical:'
			if (preg_match('/ [a-z]+\.\d+\.[a-z]+\.[a-z]+\W/', $line))
			{
				return true;
			}
			# Regular Expression Map:
			#  'pagehandler.rb:653:in `'
			if (preg_match('/\w+\.rb:\d+:in `/', $line))
			{
				return true;
			}
			return false;
		}
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
