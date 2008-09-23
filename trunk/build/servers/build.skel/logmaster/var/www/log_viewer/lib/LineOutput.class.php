<?php
class LineOutput
{
	public static $displayed_lines = 0;

	public static function display($line, $extra_classes = '')
	{
		self::$displayed_lines++;

		$offset = $line->get_offset();

		echo "<div id='" . $offset . "' class='log_line " . $extra_classes . "'>";
		echo "[<a href='?log=" . $_GET['log'] . "&offset=" . max(($offset - 8192), 0) . "&length=12288#" . $offset . "' name='" . $offset . "'>" . $offset .  "</a>] ";
		echo (string)$line;
		echo "</div>\n";
	}
}
?>
