<?php
class PHPLog extends Log
{
	protected $php_configuration = null;
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
		#  '((live)) (/prefs.php) ((User Notice)) (.*)'
		#  '(()) (/prefs.php) ((Warning)) (.*)'
		if (preg_match('!\(([a-z]*)\) (\S+) +\((\w+ ?\w+)\) +(.*)!i', $this->extra_data, $matches))
		{
			$this->extra_data = null;

			if (strlen($matches[1]))
			{
				$this->php_configuration = $matches[1];
			}
			$this->php_script_name = $matches[2];
			$this->php_error_level = $matches[3];
			$this->php_error_message = $matches[4];

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
		$string  = parent::__toString();
		if (defined('DEBUG') && DEBUG)
		{
			$string .= '<span class="debug">Begin ' . __CLASS__ . '</span>';
		}

		$string .= ' <span class="configuration">(';
		if (! is_null($this->php_configuration))
		{
			$string .= $this->php_configuration;
		}
		$string .= ')</span> ';
		if (! is_null($this->php_script_name))
		{
			$string .= htmlspecialchars($this->php_script_name, ENT_QUOTES) . " ";
		}
		if (! is_null($this->php_error_level))
		{
			$string .= "<span class='errorlevel_" . $this->error_level . "'>(" . htmlspecialchars($this->php_error_level, ENT_QUOTES) . ")</span> ";
		}
		if (! is_null($this->php_error_message))
		{
			$string .= htmlspecialchars($this->php_error_message, ENT_QUOTES);
		}
		if (defined('DEBUG') && DEBUG)
		{
			$string .= '<span class="debug">End ' . __CLASS__ . '</span>';
		}
		return $string;
	}


	public static function factory($line)
	{
		return new PHPLog($line);
	}


	public static function handles($line)
	{
		if (parent::handles($line))
		{
			return (strpos($line, ' php-site: ') !== FALSE);
		}
	}


	public static function priority()
	{
		return parent::priority() + 1;
	}
}
?>
