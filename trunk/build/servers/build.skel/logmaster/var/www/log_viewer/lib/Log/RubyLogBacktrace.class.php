<?php
class RubyLogBacktrace extends RubyLog
{
	protected $ruby_backtrace = null;


	public function __construct($line)
	{
		parent::__construct($line);

		$this->ruby_backtrace = $this->ruby_error_message;
		$this->ruby_error_message = null;
	}


	public function __toString()
	{
		$string = parent::__toString();
		if (! is_null($this->ruby_backtrace))
		{
			$string .= "<span class='errorlevel_" . $this->error_level . "'>";
			$backtrace_components = preg_split('! (\.?/)!', $this->ruby_backtrace, -1, PREG_SPLIT_DELIM_CAPTURE);
			for ($i = 0; $i < count($backtrace_components);)
			{
				$string .= htmlspecialchars($backtrace_components[$i++], ENT_QUOTES);
				if (($i % 2) && ($i < (count($backtrace_components) - 1)))
				{
					$string .= "<br>\n<spacer type='block' width='40'/>";
				}
			}
			$string .= "</span>";
		}
		return $string;
	}


	public static function factory($line)
	{
		return new RubyLogBacktrace($line);
	}


	public static function handles($line)
	{
		if (parent::handles($line))
		{
			return preg_match('/\w+\.rb:\d+:in `/', $line);
		}
		return false;
	}


	public function merge($other)
	{
		$this->ruby_backtrace .= $other->ruby_backtrace;
		$this->line .= $other->line;
	}


	public static function priority()
	{
		# Over-ride RubyLog for backtraces.
		return parent::priority() + 1;
	}


	public function related($other)
	{
		if (! is_a($other, __CLASS__))
		{
			return false;
		}
		if (strcmp($other->syslog_host, $this->syslog_host))
		{
			return false;
		}
		if (abs($other->syslog_date - $this->syslog_date) > 5)
		{
			return false;
		}
		return true;
	}
}
?>
