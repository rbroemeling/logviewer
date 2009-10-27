<?php
class LineOutput
{
	public static $displayed_lines = 0;

	public static function display($line, $extra_classes = '')
	{
		self::$displayed_lines++;

		echo "<div class='log_line " . $extra_classes . "'>";
		echo '(' . number_format(self::$displayed_lines) . ') ';
		echo '[' . LineOutput::create_link($line) . '] ';
		echo (string)$line;
		echo "</div>\n";
	}

	protected static function create_link($line)
	{
		static $encoded_environment = null;
		static $encoded_language = null;

		# urlencode our environment and language query parameters as
		# necessary.
		if (is_null($encoded_environment))
		{
			$encoded_environment = urlencode($_GET['environment']);
		}
		if (is_null($encoded_language))
		{
			$encoded_language = urlencode($_GET['language']);
		}

		# Create an array of query parameters and create the line identifier
		# for this particular log line.
		$query_params = array
		(
			'display_extended_filters=0',
			'environment=' . $encoded_environment,
			'language=' . $encoded_language,
			'start_timestamp=' . ($line->syslog_timestamp - 1),
			'end_timestamp=' . ($line->syslog_timestamp + 5)
		);
		$line_id = $line->syslog_timestamp . '.' . $line->line_offset;

		return '<a href="?' . implode('&', $query_params) . '#' . $line_id . '" id="' . $line_id . '" name="' . $line_id . '">link</a>';
	}
}
?>
