<?php
class PHPLogBacktrace extends PHPLog
{
	protected $php_backtrace = null;


	public function __construct($line)
	{
		parent::__construct($line);

		$this->php_backtrace = $this->php_error_message;
		$this->php_error_message = null;
	}


	public function __toString()
	{
		$string  = parent::__toString();
		if (defined('DEBUG') && DEBUG)
		{
			$string .= '<span class="debug">Begin ' . __CLASS__ . '</span>';
		}
		if (! is_null($this->php_backtrace))
		{
			$string .= "<span class='errorlevel_" . $this->error_level . "'>";
			$backtrace_components = preg_split('!<-!', $this->php_backtrace, -1);
			// The first array element will contain the error message as well as the first actual backtrace
			// element.  Split it up so that the error message is the first array element and the first actual
			// backtrace component is the second array element.
			if (count($backtrace_components) > 0)
			{
				$tmp = preg_split('! (/[^*])!', $backtrace_components[0], 2, PREG_SPLIT_DELIM_CAPTURE);
				while (count($tmp) > 2)
				{
					// Concatenate the last two elements of $tmp together into the
					// last element, shortening the array by one.
					$tmp[count($tmp) - 2] .= array_pop($tmp);
				}
				array_splice($backtrace_components, 0, 1, $tmp);
				
			}
			for ($i = 0; $i < count($backtrace_components); $i++)
			{
				$backtrace_components[$i] = htmlspecialchars($backtrace_components[$i], ENT_QUOTES);
			}
			$string .= implode("<br>\n<spacer type='block' width='40'/>", $backtrace_components);
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
		return new PHPLogBacktrace($line);
	}


	public static function handles($line)
	{
		if (parent::handles($line))
		{
			# Regular Expression Map:
			#  'toString(0)<-/'
			return preg_match('/\w+[(]\d+[)]<-/', $line);
		}
		return false;
	}


	public static function priority()
	{
		# Over-ride PHPLog for backtraces.
		return parent::priority() + 1;
	}
}
?>
