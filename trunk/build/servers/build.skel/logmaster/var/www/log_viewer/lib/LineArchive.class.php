<?php
/**
 * LineArchive
 *
 * A simple class that is used to maintain an archive of lines that we have
 * encountered in the log file but that we do not yet wish to display.  This
 * can happen if we are using filters and the line in question does not match
 * the filters.  We need to save the lines, rather than disposing of them and
 * ignoring the result, so that we can display them if they are needed as
 * context for a later match.
 **/
class LineArchive
{
	protected static $archive = array();
	public static $count = 0;


	public static function add($line)
	{
		// We trim the archive whenever it gets larger than 150% of MAX_CONTEXT,
		if (count(self::$archive) > (MAX_CONTEXT * 1.5))
		{
			array_splice(self::$archive, 0, (count(self::$archive) - MAX_CONTEXT));
		}
		self::$archive[] = $line;
		self::$count++;
	}


	public static function pop_last($count)
	{
		self::$count = max(self::$count - $count, 0);
		return array_splice(self::$archive, $count * -1, $count);
	}


	public static function reset()
	{
		self::$archive = array();
		self::$count = 0;
	}


	public static function skip_warning()
	{
		if (self::$count)
		{
			return "<div class='warning'>Skipped " . number_format(self::$count) . " log line" . (self::$count > 1 ? 's' : '') . " based on filters applied.</div>\n";
		}
		return "";
	}
}
?>
