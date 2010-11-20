<?php
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
