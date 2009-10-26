<?php
class RubyLogBacktrace extends RubyLog
{
	protected $ruby_backtrace = null;


	public function __construct($line)
	{
		parent::__construct($line);

		$this->ruby_backtrace = $this->ruby_error_message;
		$this->ruby_error_message = null;

		if (is_null($this->ruby_backtrace))
		{
			# If the line is a backtrace continuation, then RubyLog won't have
			# parsed it, and the information is in $this->extra_data.
			$this->ruby_backtrace = $this->extra_data;
			$this->extra_data = null;
		}
	}


	public function __toString()
	{
		$string  = parent::__toString();
		if (defined('DEBUG') && DEBUG)
		{
			$string .= '<span class="debug">Begin ' . __CLASS__ . '</span>';
		}
		if (! is_null($this->ruby_backtrace))
		{
			# Check for a RAP backtrace, because they are displayed slightly differently
			# so we need to translate it into a normal ruby backtrace format if they are.
			if (strpos($this->ruby_backtrace, 'RAP Error Backtrace:') !== false)
			{
				$this->ruby_backtrace = str_replace("'/", "' /", $this->ruby_backtrace);
				$this->ruby_backtrace = str_replace("'./", "' ./", $this->ruby_backtrace);
			}

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
		if (defined('DEBUG') && DEBUG)
		{
			$string .= '<span class="debug">End ' . __CLASS__ . '</span>';
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
			# Regular Expression Map:
			#  'pagehandler.rb:653:in `'
			return preg_match('/\w+\.rb:\d+:in `/', $line);
		}
		return false;
	}


	public static function priority()
	{
		# Over-ride RubyLog for backtraces.
		return parent::priority() + 1;
	}
}
?>