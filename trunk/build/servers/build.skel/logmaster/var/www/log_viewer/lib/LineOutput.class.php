<?php
class LineOutput
{
	public static $displayed_lines = 0;

	public static function display($line, $extra_classes = '')
	{
		self::$displayed_lines++;

		$offset = $line->get_offset();

		echo "<div class='log_line " . $extra_classes . "'>";
		echo '(' . number_format(self::$displayed_lines) . ') ';
		echo '[' . LineOutput::offset_link($offset) . '] ';
		echo (string)$line;
		echo "</div>\n";
	}

	protected static function offset_link($offset)
	{
		return "<a href='?log=" . $_GET['log'] . "&offset=" . max(($offset - 8192), 0) . "&length=12288#" . $offset . "' id='" . $offset . "' name='" . $offset . "'>" . $offset .  "</a>";
	}
}
?>
