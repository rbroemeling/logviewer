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

include_once(dirname(__FILE__) . '/RubyLog.class.php');

class RubyLogBacktrace extends RubyLog
{
	public $ruby_backtrace = null;


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
		if (DEBUG)
		{
			$string .= '<span class="debug">Begin ' . __CLASS__ . '</span>';
		}
		if (! is_null($this->ruby_backtrace))
		{
			$string .= "<span class='errorlevel_" . $this->error_level . "'>";
			$backtrace_components = preg_split("!(:\d+:in `[^']+')!", $this->ruby_backtrace, -1, PREG_SPLIT_DELIM_CAPTURE);
			for ($i = 0, $j = count($backtrace_components); $i < $j; $i++)
			{
				$string .= htmlspecialchars($backtrace_components[$i], ENT_QUOTES);
				if (($i % 2) && ($i < ($j - 1)))
				{
					$string .= "<br>\n<spacer type='block' width='40'/>";
				}
			}
			$string .= "</span>";
		}
		if (DEBUG)
		{
			$string .= '<span class="debug">End ' . __CLASS__ . '</span>';
		}
		return $string;
	}


	public static function handles($line, $uuid)
	{
		if (parent::handles($line, $uuid))
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
