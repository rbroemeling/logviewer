<?php
interface iLogLine
{
	public function __construct($line);
	public function __toString();
	public static function factory($line);
	public static function handles($line);
	public static function priority();
}
?>
