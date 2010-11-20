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
		$query_params = array
		(
			'environment' => $_GET['environment'],
			'language' => $_GET['language'],
			'start_timestamp' => $line->log_timestamp() - 1,
			'end_timestamp' => $line->log_timestamp() + 5
		);
		$line_identifier = $line->log_timestamp() . '.' . $line->get_offset();

		$url = '?';
		foreach ($query_params as $key => $value)
		{
			$url .= $key . '=' . urlencode($value) . '&';
		}
		$url = substr($url, 0, -1) . '#' . $line_identifier;

		return "<a href='$url' id='" . $line_identifier . "' name='" . $line_identifier . "'>link</a>";
	}
}
?>
