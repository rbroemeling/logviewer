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

		# Initialize the necessary convenience variables as necessary.
		if (is_null($encoded_environment))
		{
			$encoded_environment = urlencode($_GET['environment']);
		}
		if (is_null($encoded_language))
		{
			$encoded_language = urlencode($_GET['language']);
		}
		$log_timestamp = $line->log_timestamp();
		$log_offset = $line->get_offset();

		# Create an array of query parameters and create the line identifier
		# for this particular log line.
		$query_params = array
		(
			'display_extended_filters=0',
			'environment=' . $encoded_environment,
			'language=' . $encoded_language,
			'start_timestamp=' . ($log_timestamp - 1),
			'end_timestamp=' . ($log_timestamp + 5)
		);
		$line_identifier = $log_timestamp . '.' . $log_offset;

		# Create the link by imploding an array of strings.  This is done
		# rather than string concatenation for performance reasons (this
		# is quicker).
		$url_array = array
		(
			'<a href="?',
			implode('&', $query_params),
			'#',
			$line_identifier,
			'" id="',
			$line_identifier,
			'" name="',
			$line_identifier,
			'">link</a>'
		);
		return implode('', $url_array);
	}
}
?>
