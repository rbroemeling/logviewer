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

include_once(dirname(__FILE__) . '/PHPLog.class.php');

class PHPLogBacktrace extends PHPLog
{
	public $php_backtrace = null;


	public function __construct($line)
	{
		parent::__construct($line);

		$this->php_backtrace = $this->php_error_message;
		$this->php_error_message = null;
	}


	public function __toString()
	{
		$string  = parent::__toString();
		if (DEBUG)
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
