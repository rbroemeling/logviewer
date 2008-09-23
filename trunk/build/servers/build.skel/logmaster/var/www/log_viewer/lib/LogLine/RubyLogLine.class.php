<?php
class RubyLogLine extends LogLine implements iLogLine
{
	protected $ruby_component = null;
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
		#  '(31564).(general).(critical): (.*)'
		#  '(19426).(general).(error)( (req:19426:211)): (.*)'
		if (preg_match('!(\d+)\.([a-z]+)\.([a-z]+)( +\(req:\d+:\d+.*?\))?: *(.*)!i', $this->extra_data, $matches))
		{
			$this->extra_data = null;

			$this->ruby_pid = $matches[1];
			$this->ruby_component = $matches[2];
			$this->ruby_error_level = $matches[3];
			if (strlen($matches[4]))
			{
				$this->ruby_request_identifier = trim($matches[4]);
			}
			$this->ruby_error_message = $matches[5];

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
		$string = parent::__toString();
		if (! is_null($this->ruby_pid))
		{
			$string .= " <span class='pid'>" . htmlspecialchars($this->ruby_pid, ENT_QUOTES) . "</span>.";
		}
		if (! is_null($this->ruby_component))
		{
			$string .= "<span class='ruby_component_" . $this->ruby_component . "'>" . htmlspecialchars($this->ruby_component, ENT_QUOTES) . "</span>.";
		}
		$string .= "<span class='errorlevel_" . $this->error_level . "'>";
		if (! is_null($this->ruby_error_level))
		{
			$string .= htmlspecialchars($this->ruby_error_level, ENT_QUOTES);
		}
		if (! is_null($this->ruby_request_identifier))
		{
			$string .= " " . htmlspecialchars($this->ruby_request_identifier, ENT_QUOTES);
		}
		$string .= ": ";
		if (! is_null($this->ruby_error_message))
		{
			$string .= htmlspecialchars($this->ruby_error_message, ENT_QUOTES);
		}
		$string .= "</span>";

		return $string;
	}


	public static function factory($line)
	{
		return new RubyLogLine($line);
	}


	public static function handles($line)
	{
		if (parent::handles($line))
		{
			# Regular Expression Map:
			#  ' 31564.general.critical:'
			return preg_match('/ \d+\.[a-z]+\.[a-z]+\W/', $line);
		}
	}


	public static function priority()
	{
		# This class must be a higher priority than PHPLogLine, because they are
		# siblings and will often match the same lines.
		# We want this class to take priority when they do.
		return PHPLogLine::priority() + 1;
	}
}
?>
