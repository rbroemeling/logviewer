<?php
class PHPLog extends Log
{
	private static $handle_cache_id = null;
	private static $handle_cache_result = null;

	public $php_configuration = null;
	public $php_error_level = null;
	public $php_error_message = null;
	public $php_script_lineno = null;
	public $php_script_name = null;
	public $php_script_path = null;

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
		
		# Regular Expression Map:
		# '(/cache/templates/forums/forumviewthread.parsed.php):(117) (.*)'
		if (preg_match('!^([^:]+):(\d+) +(.*)!', $this->php_error_message, $matches))
		{
			$this->php_script_path = $matches[1];
			$this->php_script_lineno = $matches[2];
			$this->php_error_message = $matches[3];
		}
		
		# Regular Expression Map:
		# '([3296920/)(75.157.111.45]) (.*)'
		# '([anon/)(202.175.26.145]) (.*)'
		if (preg_match('!^(\[[ano0-9-]+/)([0-9.]+\]) +(.*)!', $this->php_error_message, $matches))
		{
			$this->client_uid = $matches[1];
			$this->client_ip = $matches[2];
			$this->php_error_message = $matches[3];
		}
	}


	public function __toString()
	{
		$string  = parent::__toString();
		if (DEBUG)
		{
			$string .= '<span class="debug">Begin ' . __CLASS__ . '</span>';
		}

		$string .= ' <span class="configuration">(';
		if (! is_null($this->php_configuration))
		{
			$string .= $this->php_configuration;
		}
		$string .= ')</span> ';
		$string .= "<span class='errorlevel_" . $this->error_level . "'>";
		if (! is_null($this->php_script_name))
		{
			$string .= htmlspecialchars($this->php_script_name, ENT_QUOTES) . " ";
		}
		if (! is_null($this->php_error_level))
		{
			$string .= "(" . htmlspecialchars($this->php_error_level, ENT_QUOTES) . ") ";
		}
		if (! is_null($this->php_script_path))
		{
			$string .= $this->php_script_path . ":";
		}
		if (! is_null($this->php_script_lineno))
		{
			$string .= $this->php_script_lineno . " ";
		}
		if (! is_null($this->php_error_message))
		{
			$string .= htmlspecialchars($this->php_error_message, ENT_QUOTES);
		}
		$string .= '</span>';
		if (DEBUG)
		{
			$string .= '<span class="debug">End ' . __CLASS__ . '</span>';
		}
		return $string;
	}


	public static function factory($line)
	{
		return new PHPLog($line);
	}


	public static function handles($line, $uuid)
	{
		if (self::$handle_cache_id != $uuid)
		{
			self::$handle_cache_id = $uuid;
			self::$handle_cache_result = false;
			if (parent::handles($line, $uuid))
			{
				self::$handle_cache_result = (strpos($line, ' php-site: ') !== false);
			}
		}
		return self::$handle_cache_result;
	}


	public static function priority()
	{
		return parent::priority() + 1;
	}
}
?>
