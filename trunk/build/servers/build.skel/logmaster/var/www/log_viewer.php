<?php
/**
 * log_viewer.php
 *
 * A monolithic script that allows web access to select log files.
 *
 * This script is monolithic for the simplicity of installation that such a
 * design entails.  It has no dependencies.  Simply drop it into a directory,
 * perhaps modify some of the configuration variables, and it is ready to go.
 **/

/******************************************************************************
 * Configuration Constants
 ******************************************************************************/
// Default length for the interface to use, if the user does not supply one.
define('DEFAULT_LENGTH', -1 * 64 * 1024);

// A limit on the maximum number of lines that is allowed in filter context.
// This limitation is necessary as we need to archive all of the lines that
// we are hiding so that they can be recalled for the filter context if
// necessary.  Thus this restriction helps keep memory usage down.
define('MAX_CONTEXT', 100);

// Maximum number of lines to ever display, the script will never display more
// than this number of lines.  This is a simple sanity check only, to prevent
// run-away log data display.
define('MAX_LINES', 15000);


/******************************************************************************
 * Configuration Variables
 ******************************************************************************/
// An array of all of the log sources that this script can be used to view.
$log_sources = array
(
	'php-beta' => '/var/log/php-beta.log',
	'php-live' => '/var/log/php-live.log',
	'php-stage' => '/var/log/php-stage.log',
	'ruby-beta' => '/var/log/ruby-beta.log',
	'ruby-live' => '/var/log/ruby-live.log',
	'ruby-stage' => '/var/log/ruby-stage.log'
);


/******************************************************************************
 * Classes
 ******************************************************************************/
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


class LogLine
{
	protected static $error_level;
	protected static $fields;
	protected static $line;


	public static function display()
	{
		if (! self::$fields)
		{
			return self::$line;
		}

		$string = '';
		$i = 0;
		$string .= "<span class='date'>" . htmlspecialchars(self::$fields[$i++], ENT_QUOTES) . "</span> ";
		$string .= "<span class='host'>" . htmlspecialchars(self::$fields[$i++], ENT_QUOTES) . "</span> ";
		$string .= "<span class='program'>" . htmlspecialchars(self::$fields[$i++], ENT_QUOTES) . "</span> ";
		$string .= "<span class='uid'>" . htmlspecialchars(self::$fields[$i++], ENT_QUOTES) . "</span>";
		$string .= "<span class='ip'>" . htmlspecialchars(self::$fields[$i++], ENT_QUOTES) . "</span> ";
		for ($i = $i; $i < count(self::$fields); $i++)
		{
			$string .= htmlspecialchars(self::$fields[$i], ENT_QUOTES);
		}
		return $string;
	}


	public static function parse($line)
	{
		self::$error_level = 'unknown';
		self::$fields = array();
		self::$line = $line;

		if (! preg_match('!^([a-z]{3} +\d+ +[0-9:]{8}) +([\d./]+) +([^:]+:) +(.*)!i', self::$line, self::$fields))
		{
			return false;
		}
		array_shift(self::$fields);

		$matches = array();
		if (preg_match('!(\[[0-9-]+/)([0-9.]+\]) +(.*)!', self::$fields[count(self::$fields) - 1], $matches))
		{
			array_shift($matches);
			array_splice(self::$fields, -1, 1, $matches);
		}
		elseif (preg_match('!(\[[0-9.]+\]) +(.*)!', self::$fields[count(self::$fields) - 1], $matches))
		{
			$matches[0] = '';
			array_splice(self::$fields, -1, 1, $matches);
		}
		else
		{
			array_splice(self::$fields, -1, 0, array('', ''));
		}
		return true;
	}
}


class PHPLogLine extends LogLine
{
	protected static $php_fields;


	public static function display()
	{
		$string = parent::display();
		if (! self::$php_fields)
		{
			return $string;
		}


		$i = 0;

		$string .= " " . htmlspecialchars(self::$php_fields[$i++], ENT_QUOTES) . " ";

		$string .= "<span class='errorlevel_" . parent::$error_level . "'>(" . htmlspecialchars(self::$php_fields[$i++], ENT_QUOTES) . ")</span> ";

		for ($i = $i; $i < count(self::$php_fields); $i++)
		{
			$string .= htmlspecialchars(self::$php_fields[$i], ENT_QUOTES);
		}
		return $string;
	}


	public static function parse($line)
	{
		if (! parent::parse($line))
		{
			return false;
		}
		if (preg_match('!(\S+) +\((\w+ ?\w+)\) +(.*)!i', parent::$fields[count(LogLine::$fields) - 1], self::$php_fields))
		{
			array_pop(parent::$fields);
			array_shift(self::$php_fields);

			switch (self::$php_fields[1])
			{
				case 'Error':
				case 'User Error':
					parent::$error_level = 'error';
					break;
				case 'Notice':
				case 'User Notice':
					parent::$error_level = 'info';
					break;
				case 'Warning':
				case 'User Warning':
					parent::$error_level = 'warning';
					break;
				case 'PHP Strict':
					parent::$error_level = 'spam';
					break;
			}
			return true;
		}
		else
		{
			return false;
		}
	}
}


class RubyLogLine extends LogLine
{
	protected static $component;
	protected static $request_identifier;
	protected static $ruby_fields;


	public static function display()
	{
		$string = parent::display();
		if (! self::$ruby_fields)
		{
			return $string;
		}

		$i = 0;

		$string .= " <span class='pid'>" . htmlspecialchars(self::$ruby_fields[$i++], ENT_QUOTES) . "</span>.";

		$string .= "<span class='ruby_component_" . self::$component . "'>" . htmlspecialchars(self::$ruby_fields[$i++], ENT_QUOTES) . "</span>.";

		$string .= "<span class='errorlevel_" . parent::$error_level . "'>" . htmlspecialchars(self::$ruby_fields[$i++], ENT_QUOTES) . "</span>";
		if (self::$request_identifier)
		{
			$string .= " (" . implode(':', self::$request_identifier) . ")";
		}
		$string .= ": ";

		$string .= "<span class='errorlevel_" . parent::$error_level . "'>";
		for ($i = $i; $i < count(self::$ruby_fields); $i++)
		{
			$string .= htmlspecialchars(self::$ruby_fields[$i], ENT_QUOTES);
		}
		$string .= "</span>";

		return $string;
	}


	public static function parse($line)
	{
		if (! parent::parse($line))
		{
			return false;
		}
		if (preg_match('!(\d+)\.([a-z]+)\.([a-z]+)( +\(req:\d+:\d+.*?\))?: *(.*)!i', parent::$fields[count(LogLine::$fields) - 1], self::$ruby_fields))
		{
			array_pop(parent::$fields);
			array_shift(self::$ruby_fields);

			self::$component = htmlspecialchars(self::$ruby_fields[1], ENT_QUOTES);
			parent::$error_level = htmlspecialchars(self::$ruby_fields[2], ENT_QUOTES);
			if (preg_match('!\((req):(\d+):(\d+):?([^)]*)\)!', self::$ruby_fields[3], self::$request_identifier))
			{
				array_shift(self::$request_identifier);
				if (! self::$request_identifier[3])
				{
					array_pop(self::$request_identifier);
				}
				array_splice(self::$ruby_fields, 3, 1);
			}
			else
			{
				self::$request_identifier = null;
			}
			return true;
		}
		else
		{
			return false;
		}
	}
}


class LineOutput
{
	public static $displayed_lines = 0;

	public static function display($line, $line_start_position, $line_end_position, $extra_classes = '')
	{
		self::$displayed_lines++;

		echo "<div id='" . $line_start_position . "' class='log_line " . $extra_classes . "'>";
		echo "[<a href='?log=" . $_GET['log'] . "&offset=" . max(($line_start_position - 8192), 0) . "&length=12288#" . $line_start_position . "' name='" . $line_start_position . "'>" . $line_start_position .  "</a>] ";

		if (preg_match('/ \d+\.[a-z]+\.[a-z]+\W/', $line))
		{
			RubyLogLine::parse($line);
			echo RubyLogLine::display();
		}
		elseif (preg_match('/ PHP error: /', $line))
		{
			PHPLogLine::parse($line);
			echo PHPLogLine::display();
		}
		else
		{
			LogLine::parse($line);
			echo LogLine::display();
		}
		echo "</div>\n";
	}
}


/******************************************************************************
 * Utility Functions
 ******************************************************************************/
function filter_form_string($filter = null, $negate_filter = 0, $logic_filter = 'OR')
{
	$s = '';
	$s .= '<input type="button" style="margin-right: 10px;" value="-" onclick="this.parentNode.parentNode.removeChild(this.parentNode);" />';
	$s .= 'Filter: <input type="text" name="filter[]" size="80" style="margin-right: 10px;" maxlength="100" value="' . htmlspecialchars($filter, ENT_QUOTES) . '" />';
	$s .= '<select name="negate_filter[]" style="margin-right: 10px;"><option value="0" ' . ($negate_filter ? '' : 'selected') . '>Normal Match</option><option value="1" ' . ($negate_filter ? 'selected' : '') . '>Inverted Match</option></select>';
	$s .= '<select name="logic_filter[]"><option ' . ($logic_filter == 'AND' ? 'selected' : '') . '>AND</option><option ' . ($logic_filter == 'OR' ? 'selected' : '') . '>OR</option></select>';
	return $s;
}


function filter_match($line)
{
	$filter_results = array();

	for ($i = 0; $i < count($_GET['filter']); $i++)
	{
		if (! $_GET['filter'][$i])
		{
			$filter_results[$i] = (1 XOR $_GET['negate_filter'][$i]);
		}
		elseif (preg_match($_GET['filter'][$i], $line) XOR $_GET['negate_filter'][$i])
		{
			$filter_results[$i] = 1;
		}
		else
		{
			$filter_results[$i] = 0;
		}
	}

	$k = count($filter_results);
	for ($i = 0; $i < $k; $i++)
	{
		if ($_GET['logic_filter'][$i] == 'AND')
		{
			$filter_results[$i + 1] = $filter_results[$i] && $filter_results[$i + 1];
			unset($filter_results[$i]);
		}
	}

	return (array_sum($filter_results) > 0);
}


function sanitize_filter($errno = null, $errstr = null)
{
	global $errors;
	$regular_expression_errors = 0;
	static $regular_expression_errstr = '';
	global $warnings;

	if ($errstr)
	{
		$regular_expression_errstr = $errstr;
		return;
	}
	if (! is_array($_GET['filter']))
	{
		if (isset($_GET['filter']))
		{
			$_GET['filter'] = array($_GET['filter']);
		}
		else
		{
			$_GET['filter'] = array();
		}
	}
	for ($i = 0; $i < count($_GET['filter']); $i++)
	{
		if (! strlen($_GET['filter'][$i]))
		{
			$warnings[] = 'Regular expression #' . ($i + 1) . ' is empty.  Empty regular expressions will match any string.';
			continue;
		}
		if (! is_string($_GET['filter'][$i]))
		{
			$errors[] = 'Regular expression #' . ($i + 1) . ' is not a string.';
			$regular_expression_errors++;
			continue;
		}
		if (preg_match('/^[ :;A-Z0-9_-]+$/i', $_GET['filter'][$i]))
		{
			// Assume that this is a plaintext match to be carried out, transform
			// it into a regular expression.
			$_GET['filter'][$i] = '/' . $_GET['filter'][$i] . '/';
		}
		set_error_handler('sanitize_filter');
		preg_match($_GET['filter'][$i], '');
		restore_error_handler();
		if ($regular_expression_errstr)
		{
			$regular_expression_errstr = explode(':', $regular_expression_errstr);
			$errors[] = 'Regular expression #' . ($i + 1) . ' ("' . $_GET['filter'][$i] . '") is not valid: ' . $regular_expression_errstr[1] . '.';
			$regular_expression_errstr = '';
			$regular_expression_errors++;
		}
	}
	return ($regular_expression_errors == 0);
}


function sanitize_filter_context()
{
	global $errors;
	global $warnings;

	if ((! is_string($_GET['filter_context'])) || (! strlen($_GET['filter_context'])))
	{
		$_GET['filter_context'] = 0;
	}
	if (! is_numeric($_GET['filter_context']))
	{
		$errors[] = 'Filter context "' . $_GET['filter_context'] . '" is not a numeric value.  Context must be numeric.';
		return 0;
	}
	$_GET['filter_context'] = intval($_GET['filter_context']);
	if ($_GET['filter_context'] < 0)
	{
		$warnings[] = 'Filter context is symmetrical, treating context ' . number_format($_GET['filter_context']) . ' as ' . number_format($_GET['filter_context'] * -1) . '.';
		$_GET['filter_context'] = $_GET['filter_context'] * -1;
	}
	if ($_GET['filter_context'] > MAX_CONTEXT)
	{
		$warnings[] = 'Filter context is limited to ' . number_format(MAX_CONTEXT) . ' lines.';
		$_GET['filter_context'] = MAX_CONTEXT;
	}
	return 1;
}


function sanitize_length()
{
	global $errors;

	if ((! is_string($_GET['length'])) || (! strlen($_GET['length'])))
	{
		$_GET['length'] = DEFAULT_LENGTH;
	}
	if (! is_numeric($_GET['length']))
	{
		$errors[] = 'Length "' . $_GET['length'] . '" is not a numeric value.  Length must be numeric.';
		return 0;
	}
	$_GET['length'] = intval($_GET['length']);
	if (! $_GET['length'])
	{
		$errors[] = 'Length of 0 does not make a whole lot of sense.  Try a positive or negative integer.';
		return 0;
	}
	return 1;
}


function sanitize_log()
{
	global $errors;
	global $log_size;
	global $log_sources;

	if ((! is_string($_GET['log'])) || (! $log_sources[$_GET['log']]))
	{
		$errors[] = 'Log name "' . $_GET['log'] . '" is not known to this interface.';
		return 0;
	}
	if (! file_exists($log_sources[$_GET['log']]))
	{
		$errors[] = $log_sources[$_GET['log']] . ' does not exist.';
		return 0;
	}
	$log_size = filesize($log_sources[$_GET['log']]);
	if (! $log_size)
	{
		$errors[] = $log_sources[$_GET['log']] . ' is empty.';
		return 0;
	}
	return 1;
}


function sanitize_logic_filter()
{
	global $errors;
	$error_count = 0;

	if (! is_array($_GET['logic_filter']))
	{
		if (isset($_GET['logic_filter']))
		{
			$_GET['logic_filter'] = array($_GET['logic_filter']);
		}
		else
		{
			$_GET['logic_filter'] = array();
		}
	}
	for ($i = 0; $i < count($_GET['filter']); $i++)
	{
		if (is_string($_GET['logic_filter'][$i]))
		{
			$_GET['logic_filter'][$i] = strtoupper($_GET['logic_filter'][$i]);
		}
		if (($_GET['logic_filter'][$i] != 'AND') && ($_GET['logic_filter'][$i] != 'OR'))
		{
			$error_count++;
			$errors[] = 'Filter #' . ($i + 1) . ' logic operator must be either "AND" or "OR".';
		}
	}
	return ($error_count == 0);
}


function sanitize_negate_filter()
{
	global $warnings;

	if (! is_array($_GET['negate_filter']))
	{
		if (isset($_GET['negate_filter']))
		{
			$_GET['negate_filter'] = array($_GET['negate_filter']);
		}
		else
		{
			$_GET['negate_filter'] = array();
		}

	}
	for ($i = 0; $i < count($_GET['filter']); $i++)
	{
		if (! $_GET['negate_filter'][$i])
		{
			$_GET['negate_filter'][$i] = 0;
		}
		else
		{
			$_GET['negate_filter'][$i] = 1;
		}
	}
	return 1;
}


function sanitize_offset()
{
	global $errors;
	global $log_size;

	if ((! is_string($_GET['offset'])) || (! strlen($_GET['offset'])))
	{
		// Default offset is the bottom of the file
		$_GET['offset'] = $log_size;
		return 1;
	}
	if (! is_numeric($_GET['offset']))
	{
		$errors[] = 'Offset "' . $_GET['offset'] . '" is not a numeric value.  Offset must be numeric.';
		return 0;
	}
	$_GET['offset'] = intval($_GET['offset']);
	if ($_GET['offset'] < 0)
	{
		$errors[] = 'Negative offsets are not supported.  Offset must be larger than or equal to zero.';
		return 0;
	}
	if ($_GET['offset'] > $log_size)
	{
		$errors[] = 'Offset ' . number_format($_GET['offset']) . ' is past the end of the file, which is ' . number_format($log_size) . '.';
		return 0;
	}
	return 1;
}


function sanitize_position()
{
	global $errors;
	global $warnings;

	if ($_GET['length'] > 0)
	{
		return 1;
	}

	// Negative length.
	$_GET['length'] = $_GET['length'] * -1;
	if ($_GET['length'] > $_GET['offset'])
	{
		if ($_GET['offset'] > 0)
		{
			$warnings[] = 'Impossible to read ' . number_format($_GET['length']) . ' bytes before offset ' . number_format($_GET['offset']) . '.  Adjusting length to ' . number_format($_GET['offset'] * -1) . '.';
			$_GET['length'] = $_GET['offset'];
			$_GET['offset'] = 0;
		}
		else
		{
			$errors[] = 'Impossible to read ' . number_format($_GET['length']) . ' bytes before offset 0.';
			$_GET['length'] = $_GET['length'] * -1;
			return 0;
		}
	}
	else
	{
		$_GET['offset'] = $_GET['offset'] - $_GET['length'];
	}
	return 1;
}


function slash_machine(&$data)
{
	if (is_array($data))
	{
		foreach (array_keys($data) as $key)
		{
			slash_machine($data[$key]);
		}
	}
	else if (is_string($data))
	{
		$data = stripslashes($data);
	}
}


/******************************************************************************
 * Global Variables and Executable Code
 ******************************************************************************/
$errors = array();
$log_excerpt = array();
$log_size = -1;
$warnings = array();

set_magic_quotes_runtime(0);
if (get_magic_quotes_gpc())
{
	slash_machine($_GET);
}

$log_handle = 0;
if ($_GET['log'] && sanitize_log() && sanitize_offset() && sanitize_length() && sanitize_position() && sanitize_filter_context() && sanitize_filter() && sanitize_negate_filter() && sanitize_logic_filter())
{
	$log_handle = fopen($log_sources[$_GET['log']], 'r');
	if (! $log_handle)
	{
		$errors[] = $log_sources[$_GET['log']] . ' could not be opened for read.';
	}
}
?>
<html>
	<head>
		<title>Simple Online Log Viewer</title>
		<style type='text/css'>
			/* Global Styling ************************************************/
			body
			{
				background: #000000;
				color: #00ff00;
				font-family: arial, sans-serif;
				font-size: 12px;
			}

			a
			{
				color: #99cccc;
			}

			a:hover
			{
				color: #99ffff;
			}

			a:visited
			{
				color: #9933ff;
			}


			/* Error and Status Message Styling ******************************/
			div.error
			{
				color: #ff0000;
				font-weight: bold;
				margin-bottom: 5px;
				margin-top: 5px;
			}
			div.warning
			{
				color: #ffff00;
				font-weight: bold;
				margin-bottom: 5px;
				margin-top: 5px;
			}


			/* Input Form Styling ********************************************/
			div#filter_list div
			{
				margin-bottom: 5px;
				margin-top: 5px;
			}
			div#filter_template
			{
				display: none;
			}


			/* Documentation Table Styling ***********************************/
			table#documentation_table
			{
				font-size: 0.9em;
				border-spacing: 0px;
			}
			table#documentation_table td
			{
				border-width: 1px 0px 1px 0px;
				border-spacing: 0px;
				border-style: solid none solid none;
				border-collapse: collapse;
			}
			table#documentation_table th
			{
				background: #003300;
				border-width: 1px 0px 1px 0px;
				border-spacing: 0px;
				border-style: solid none solid none;
				border-collapse: collapse;
			}


			/* Log Excerpt Styling *******************************************/
			div.log_context
			{
				background: #222222;
			}
			div#log_excerpt
			{
				margin-top: 30px;
			}
			div.log_line
			{
				font-family: monospace;
				padding-bottom: 5px;
				padding-top: 5px;
			}

			/* LogLine generalized field colouring. */
			div.log_line span.date
			{
				color: #888888;
			}
			div.log_line span.host
			{
				color: #888888;
			}
			div.log_line span.program
			{
				color: #888888;
			}
			div.log_line span.uid
			{
				color: #666666;
			}
			div.log_line span.ip
			{
				color: #666666;
			}

			/* Global error level coloring. */
			div.log_line span.errorlevel_critical
			{
				background: #ff0000;
				color: #ffff00;
			}
			div.log_line span.errorlevel_debug
			{
				color: #ffffff;
			}
			div.log_line span.errorlevel_error
			{
				color: #ff0000;
			}
			div.log_line span.errorlevel_info
			{
				color: #00ff00;
			}
			div.log_line span.errorlevel_spam
			{
				color: #888888;
			}
			div.log_line span.errorlevel_unknown
			{

			}
			div.log_line span.errorlevel_warning
			{
				color: #ffff00;
			}

			/* RubyLogLine field coloring. */
			div.log_line span.pid
			{
				color: #ffffff;
			}
			div.log_line span.ruby_component_files
			{
				color: #ffffff;
			}
			div.log_line span.ruby_component_general
			{
				color: #ffff00;
				font-weight: bold;
			}
			div.log_line span.ruby_component_memcache
			{
				color: #0000ff;
			}
			div.log_line span.ruby_component_pagehandler
			{
				color: #ff00ff;
			}
			div.log_line span.ruby_component_site_module
			{
				color: #ffff00;
			}
			div.log_line span.ruby_component_sql
			{
				color: #0000ff;
				font-weight: bold;
			}
			div.log_line span.ruby_component_template
			{
				color: #00ffff;
			}
			div.log_line span.ruby_component_worker
			{
				color: #00ff00;
			}
		</style>
		<script type="text/javascript">
			function add_filter()
			{
				var filter_list = document.getElementById('filter_list');
				var filter_template = document.getElementById('filter_template');

				if (filter_list && filter_template)
				{
					var new_filter = filter_template.cloneNode(true);
					new_filter.id = '';
					filter_list.appendChild(new_filter);
					return 1;
				}
				return 0;
			}


			function highlight_named_anchor()
			{
				if (window.location.hash.match(/^#\d+$/))
				{
					var div = document.getElementById(window.location.hash.substr(1));
					if (div)
					{
						div.style.fontWeight = "bold";
						div.style.background = "#444444";
					}
				}
			}


			function reset_form()
			{
				var inputs = document.getElementsByTagName('input');

				for (var i = 0; i < inputs.length; i++)
				{
					if (inputs[i].type == 'text' || inputs[i].type == 'hidden')
					{
						inputs[i].value = '';
					}
					else if (inputs[i].type == 'checkbox')
					{
						inputs[i].checked = false;
					}
					else if (inputs[i].type == 'button' || inputs[i].type == 'submit')
					{
						/* Skip this input, it doesn't need to be reset. */
					}
					else
					{
						alert("reset_form(): do not know how to clear an input of type " + inputs[i].type)
					}
				}

				// Remove all filters.
				var filter_list = document.getElementById('filter_list');
				if (filter_list)
				{
					while (filter_list.hasChildNodes())
					{
						filter_list.removeChild(filter_list.firstChild);
					}
				}
			}


			function submit_form()
			{
				var log_file_form = document.getElementById('log_file_form');
				var timestamp_input = document.getElementById('timestamp_input');

				if (timestamp_input)
				{
					var timestamp = new Date();
					timestamp_input.value = timestamp.getTime();
				}

				// Submit the form.
				if (log_file_form)
				{
					log_file_form.submit();
				}
			}


			function tail()
			{
				var length_input = document.getElementById('length_input');
				var log_file_form = document.getElementById('log_file_form');
				var offset_input = document.getElementById('offset_input');

				if (length_input)
				{
					var length = parseInt(length_input.value);
					if (isNaN(length))
					{
						length = '';
					}
					else if (length == 0)
					{
						length = '';
					}
					else
					{
						length = Math.abs(length) * -1;
					}
					length_input.value = length.toString();
				}
				if (offset_input)
				{
					offset_input.value = '';
				}
				if (log_file_form)
				{
					log_file_form.action = log_file_form.action + '#tail';
				}
				submit_form();
			}


			function toggle_display(id)
			{
				var element = document.getElementById(id);

				if (! element)
				{
					return -1;
				}
				if (element.style.display == 'none')
				{
					element.style.display = 'block';
					return 1;
				}
				element.style.display = 'none';
				return 0;
			}
		</script>
	</head>
	<body onload='highlight_named_anchor();'>
		<div id='filter_template'>
			<!-- This is an invisible div that simply serves as a template for the HTML that defines a filter. -->
			<?php echo filter_form_string(); ?>
		</div>
		<form id='log_file_form' action="<?php echo $_SERVER['PHP_SELF']; ?>" method="get">
			<input type='hidden' id='timestamp_input' name='timestamp' value='' />
			<table width="100%">
				<tr>
					<td>
						Log File:
						<select name='log'>
							<option></option>
							<?php
								foreach (array_keys($log_sources) as $log)
								{
									if ($_GET['log'] == $log)
									{
										echo '<option selected>';
									}
									else
									{
										echo '<option>';
									}
									echo "$log</option>\n";
								}
							?>
						</select>
					</td>
					<td style="text-align: center;">
						Offset: <input type="text" id="offset_input" name="offset" size="11" maxlength="10" value='<?php echo htmlspecialchars($_GET['offset'], ENT_QUOTES); ?>' />
					</td>
					<td style="text-align: center;">
						Length: <input type="text" id="length_input" name="length" size="9" maxlength="8" value='<?php echo htmlspecialchars($_GET['length'], ENT_QUOTES); ?>' />
					</td>
					<td style="text-align: center;">
						Filter Context: <input type='text' name='filter_context' size='4' maxlength='3' value='<?php echo htmlspecialchars($_GET['filter_context'], ENT_QUOTES); ?>' />
					</td>
					<td style="text-align: right;">
						<input type='button' value='Add Filter' onclick='add_filter();' />
						<input type='button' value='?' onclick='toggle_display("documentation_table");' />
					</td>
				</tr>
			</table>
			<div id="filter_list">
				<?php
					for ($i = 0; $i < count($_GET['filter']); $i++)
					{
						echo '<div>';
						echo filter_form_string($_GET['filter'][$i], $_GET['negate_filter'][$i], $_GET['logic_filter'][$i]);
						echo '</div>';
					}
				?>
			</div>
			<table width="100%">
				<tr>
					<td>
						<input type='button' value='Reset' onclick='reset_form();' />
					</td>
					<td style="text-align: right;">
						<input style="margin-right: 10px;" type='button' value='Tail' onclick='tail();' />
						<input type='button' value='Submit' onclick='submit_form();' />
					</td>
				</tr>
			</table>
		</form>
		<?php
			foreach ($errors as $error_message)
			{
				echo '<div class="error">' . htmlspecialchars($error_message, ENT_QUOTES) . "</div>\n";
			}
			foreach ($warnings as $warning_message)
			{
				echo '<div class="warning">' . htmlspecialchars($warning_message, ENT_QUOTES) . "</div>\n";
			}
		?>
		<div>
			<table id='documentation_table' style="display: none" width="100%">
				<thead>
					<tr>
						<th colspan="3">General Controls</th>
					</tr>
					<tr>
						<th width='125px'>Field</th>
						<th>Description</th>
						<th width='125px'>Default</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><i>Log File</i></td>
						<td>Which log file to retrieve data from.</td>
						<td>&nbsp;</td>
					</tr>
					<tr>
						<td><i>Offset</i></td>
						<td>
							The byte position to begin fetching lines from within the log file.  If <i>offset</i> is a position in the
							middle of a log line, the partial line is not displayed.
						</td>
						<td>The last byte position in the log file.</td>
					</tr>
					<tr>
						<td><i>Length</i></td>
						<td>
							The amount of data to read from the log file.  If <i>length</i> is positive, read forward from <i>offset</i>.  If <i>length</i>
							is negative, read backward from <i>offset</i>.  If (<i>offset</i> + <i>length</i>) terminates in the middle of a log line,
							the entirety of that log line is displayed.
						</td>
						<td><?php echo number_format(DEFAULT_LENGTH); ?></td>
					</tr>
					<tr>
						<td><i>Filter Context</i></td>
						<td>How many lines of context you would like to be displayed around the lines that match the given filters.</td>
						<td>0</td>
					</tr>
					<tr>
						<td><i>Add Filter</i></td>
						<td>Use this button to add a filter to the current list of filters.</td>
						<td>&nbsp;</td>
					</tr>
				</tbody>
				<thead>
					<tr>
						<th colspan="3">Per-Filter Controls</th>
					</tr>
					<tr>
						<th width='125px'>Field</th>
						<th>Description</th>
						<th width='125px'>Default</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><i>-</i></td>
						<td>Use this button to remove the filter from the current list of filters.</td>
						<td>&nbsp;</td>
					</tr>
					<tr>
						<td><i>Filter</i></td>
						<td>
							A regular expression to match against the data retrieved from the log file.
							Only log lines matching satisfying the list of filters (or their context,
							see <i>filter context</i>) will be displayed.
						</td>
						<td>&nbsp;</td>
					</tr>
					<tr>
						<td><i>Match Type</i></td>
						<td>
							A flag stating whether to match normally or to reverse the effect of <i>filter</i>.
							<dl>
								<dt>Normal Match
								<dd>Match normally -- lines that match the <i>filter</i> regular expression will
									satisfy it.
								<dt>Inverted Match
								<dd>Invert the match -- only lines that do not match the <i>filter</i>
									regular expression will satisfy it.
							</dl>
						</td>
						<td>Normal Match</td>
					</tr>
					<tr>
						<td><i>Logical Operator</i></td>
						<td>
							A boolean operator that controls how this <i>filter</i> will relate to the
							others within the filter list.  All 'AND' logical operations are executed
							first, after which the results are 'OR'ed.
							<dl>
								<dt>OR
								<dd>If the left-hand-side or the right-hand-side is true, consider
									the filter list satisfied.  Note that 'OR' is considered to
									be lower precedence than 'AND'.
								<dt>AND
								<dd>If the left-hand-side and the right-hand-side are both true,
									consider the filter list satisfied.  Note that 'AND' is
									considered to be a higher precedence than 'OR'.
							</dl>
						</td>
						<td>OR</td>
					</tr>
				</tbody>
			</table>
		</div>
		<div id="log_excerpt">
			<?php
				if ($log_handle)
				{
					$current_position = $_GET['offset'];

					// We ignore the first line if it is a partial line.  It is a partial line
					// unless we started reading at the beginning of the log file or the
					// character immediately before our read was a newline.
					//
					// Thus we ignore the line if we have $_GET['offset'] and if the character
					// at $_GET['offset'] - 1 != "\n".
					if ($current_position)
					{
						fseek($log_handle, $current_position - 1);
						if (strcmp(fread($log_handle, 1), "\n"))
						{
							$current_position += strlen(fgets($log_handle));
						}
					}
					else
					{
						fseek($log_handle, $current_position);
					}

					LineArchive::reset();
					$contextual_lines = $_GET['filter_context'] + 1;
					while ((! feof($log_handle)) && ($current_position <= ($_GET['offset'] + $_GET['length'])) && (LineOutput::$displayed_lines < MAX_LINES))
					{
						$current_line = fgets($log_handle);
						if ($current_line === FALSE) // Check if we are at EOF.
						{
							continue;
						}

						$line_start_position = $current_position;
						$current_position += strlen($current_line);
						$line_end_position = $current_position - 1;

						// Remove any trailing newline character.
						$current_line = rtrim($current_line, "\n");

						// If we have filters, skip lines that do not match them.
						if ($_GET['filter'])
						{
							if (filter_match($current_line))
							{
								$context_lines = LineArchive::pop_last($_GET['filter_context']);
								echo LineArchive::skip_warning();
								foreach ($context_lines as $skipped_line)
								{
									LineOutput::display($skipped_line['line'], $skipped_line['start_position'], $skipped_line['end_position'], 'log_context');
								}
								$contextual_lines = 0;
								LineArchive::reset();
							}
							else
							{
								if ($contextual_lines >= $_GET['filter_context'])
								{
									LineArchive::add(array('line' => $current_line, 'start_position' => $line_start_position, 'end_position' => $line_end_position));
									continue;
								}
								$contextual_lines++;
							}
						}

						if ((0 < $contextual_lines) && ($contextual_lines <= $_GET['filter_context']))
						{
							LineOutput::display($current_line, $line_start_position, $line_end_position, 'log_context');
						}
						else
						{
							LineOutput::display($current_line, $line_start_position, $line_end_position);
						}
					}
					echo LineArchive::skip_warning();
					LineArchive::reset();

					if (LineOutput::$displayed_lines >= MAX_LINES)
					{
						echo "<div class='warning'>Encountered maximum line display limit of " . number_format(MAX_LINES) . " lines.  End of file is " . number_format($log_size - $current_position) . " bytes further.</div>\n";
					}

					if ($current_position > ($_GET['offset'] + $_GET['length']))
					{
						echo "<div class='warning'>Encountered end of requested data at offset " . number_format($current_position) . ".  End of file is " . number_format($log_size - $current_position) . " bytes further.</div>\n";
					}

					if (feof($log_handle))
					{
						echo "<div class='warning'>Encountered end of file at offset " . number_format($current_position) . ".</div>\n";
					}

					fclose($log_handle);
				}
			?>
			<a name="tail"></a>
		</div>
		<div style="text-align: right;">
			<input style="margin-right: 10px;" type='button' value='Tail' onclick='tail();' />
		</div>
	</body>
</html>
