<?php
class PHPLogLine extends LogLine implements iLogLine
{
	protected $php_error_level = null;
	protected $php_error_message = null;
	protected $php_script_name = null;


	public function __construct($line)
	{
		$matches = array();

		parent::__construct($line);

		if (! $this->extra_data)
		{
			return;
		}

		# Regular Expression Map:
		#  '(/prefs.php) ((User Notice)) (.*)'
		#  '(/prefs.php) ((Warning)) (.*)'
		if (preg_match('!(\S+) +\((\w+ ?\w+)\) +(.*)!i', $this->extra_data, $matches))
		{
			$this->extra_data = null;

			$this->php_script_name = $matches[1];
			$this->php_error_level = $matches[2];
			$this->php_error_message = $matches[3];

			switch ($this->php_error_level)
			{
				case 'Error':
				case 'User Error':
					$this->error_level = 'error';
					break;
				case 'Notice':
				case 'User Notice':
					$this->error_level = 'info';
					break;
				case 'Warning':
				case 'User Warning':
					$this->error_level = 'warning';
					break;
				case 'PHP Strict':
					$this->error_level = 'spam';
					break;
			}
		}
	}


	public function __toString()
	{
		$string = parent::__toString();
		if (! is_null($this->php_script_name))
		{
			$string .= " " . htmlspecialchars($this->php_script_name, ENT_QUOTES) . " ";
		}
		if (! is_null($this->php_error_level))
		{
			$string .= "<span class='errorlevel_" . $this->error_level . "'>(" . htmlspecialchars($this->php_error_level, ENT_QUOTES) . ")</span> ";
		}
		if (! is_null($this->php_error_message))
		{
			$string .= htmlspecialchars($this->php_error_message, ENT_QUOTES);
		}
		return $string;
	}


	public static function factory($line)
	{
		return new PHPLogLine($line);
	}


	public static function handles($line)
	{
		return (strpos($line, ' PHP error: ') !== FALSE);
	}


	public static function priority()
	{
		return parent::priority() + 1;
	}
}
?>
