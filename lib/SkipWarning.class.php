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

/**
 * SkipWarning
 *
 * A simple class that is used to maintain a count of skipped lines, which are
 * lines that we have encountered in the log file but that we do not wish to
 * display.  This can happen if we are using filters and the line in question
 * does not match the filters.
 **/
class SkipWarning
{
	public static $total_skipped = 0;
	public static $current_skipped = 0;


	public static function add()
	{
		self::$total_skipped++;
		self::$current_skipped++;
	}


	public static function reset()
	{
		self::$current_skipped = 0;
	}


	public static function warning($reset = false)
	{
		$s = "";

		if (self::$current_skipped)
		{
			$s = "<div class='warning'>Skipped " . number_format(self::$current_skipped) . " log line" . (self::$current_skipped > 1 ? 's' : '') . " based on filters applied.</div>\n";
		}
		if ($reset)
		{
			self::reset();
		}
		return $s;
	}
}
?>
