<?php
class LineOutput
{
	public static $displayed_lines = 0;
	private static $encoded_environment = null;
	private static $encoded_language = null;

	public static function display($line)
	{
		self::$displayed_lines++;

		echo "<div class='log_line'>";
		echo '(', number_format(self::$displayed_lines), ') ';
		echo '[', LineOutput::create_link($line), '] ';
		echo (string)$line;
		echo "</div>\n";
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
