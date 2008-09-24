<?php
class LineOutput
{
	public static $displayed_lines = 0;

	public static function display($line, $extra_classes = '')
	{
		self::$displayed_lines++;

		$offset = $line->get_offset();
		$merged_offsets = $line->get_merged_offsets();

		echo "<div class='log_line " . $extra_classes . "'>";
		echo '(' . number_format(self::$displayed_lines) . ($merged_offsets ? '+' . number_format(count($merged_offsets)) : '' ) . ') ';
		echo '[';
			echo LineOutput::offset_link($offset);
			foreach ($merged_offsets as $redirected_offset)
			{
				echo ', ';
				echo LineOutput::offset_link($redirected_offset);
			}
		echo '] ';
		echo (string)$line;
		echo "</div>\n";
	}

	protected static function offset_link($offset)
	{
		return "<a href='?log=" . $_GET['log'] . "&offset=" . max(($offset - 8192), 0) . "&length=12288#" . $offset . "' id='" . $offset . "' name='" . $offset . "'>" . $offset .  "</a>";
	}
}
?>
