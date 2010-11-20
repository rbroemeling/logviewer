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

class LineOutput
{
	public static $displayed_lines = 0;
	private static $encoded_environment = null;
	private static $encoded_language = null;

	public static function display($line)
	{
		self::$displayed_lines++;

		echo "<div class='log_line'>",
			'(', number_format(self::$displayed_lines), ') ',
			'[', LineOutput::create_link($line), '] ',
			(string)$line,
			"</div>\n";
	}

	protected static function create_link($line)
	{
		# urlencode our environment and language query parameters as
		# necessary.
		if (is_null(self::$encoded_environment))
		{
			self::$encoded_environment = urlencode($_GET['environment']);
		}
		if (is_null(self::$encoded_language))
		{
			self::$encoded_language = urlencode($_GET['language']);
		}

		# Create an array of query parameters and create the line identifier
		# for this particular log line.
		$query_params = array
		(
			'display_extended_filters=0',
			'environment=' . self::$encoded_environment,
			'language=' . self::$encoded_language,
			'start_timestamp=' . ($line->syslog_timestamp - 1),
			'end_timestamp=' . ($line->syslog_timestamp + 5)
		);
		$line_id = $line->syslog_timestamp . '.' . $line->line_offset;

		return '<a href="?' . implode('&', $query_params) . '#' . $line_id . '" id="' . $line_id . '" name="' . $line_id . '">link</a>';
	}
}
?>
