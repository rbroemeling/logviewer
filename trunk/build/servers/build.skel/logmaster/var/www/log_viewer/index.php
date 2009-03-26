<?php
/**
 * log_viewer index.php
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
// Maximum number of lines to ever display, the script will never display more
// than this number of lines.  This is a simple sanity check only, to prevent
// run-away log data display.
define('MAX_LINES', 15000);

// Debug-mode: output extra information about the handlers being used.
if ((! defined('DEBUG')) && $_GET['debug'])
{
	define('DEBUG', true);
}


/******************************************************************************
 * Configuration details about the location of log files.  In general, log file
 * paths are expected to follow the below template:
 *
 * $config['log_root']/$log_environment/YYYY-MM-DD-HH.$log_language.log
 *
 ******************************************************************************/
$config = array();

// The root log directory that contains sub-directories for each environment.
$config['log_root'] = '/var/log/development';

// A list of environments to be exposed to the user.
$config['environments'] = array('beta', 'live', 'stage');

// A list of languages to be exposed to the user.
$config['languages'] = array('php', 'ruby');


/******************************************************************************
 * Utility Functions
 ******************************************************************************/
function create_numeric_select($name, $min, $max, $default_value = null, $label_formatter = null)
{
	if (is_null($label_formatter))
	{
		$label_formatter = create_function('$i', 'return $i;');
	}
	if (is_null($default_value))
	{
		$default_value = $min;
	}

	$s = array();
	for ($i = intval($min); $i <= intval($max); $i++)
	{
		$s[$i] = '<option value="' . $i . '">' . call_user_func($label_formatter, $i) . '</option>';
	}

	unset($i);
	if (isset($_GET[$name]))
	{
		$i = intval($_GET[$name]);
		if (! isset($s[$i]))
		{
			unset($i);
		}
	}
	if (! isset($i))
	{
		$i = intval($default_value);
	}
	$s[$i] = '<option value="' . $i . '" selected>' . call_user_func($label_formatter, $i) . '</option>';

	return '<select name="' . $name . '">' . "\n" . implode("\n", $s) . "</select>\n";
}


function filter_form_string($filter = null, $negate_filter = 0, $logic_filter = 'OR')
{
	$s = '';
	$s .= '<input type="button" style="margin-right: 10px;" value="-" onclick="this.parentNode.parentNode.removeChild(this.parentNode);" />';
	$s .= 'Filter: <input type="text" name="filter[]" size="80" style="margin-right: 10px;" maxlength="100" value="' . htmlspecialchars($filter, ENT_QUOTES) . '" />';
	$s .= '<select name="negate_filter[]" style="margin-right: 10px;"><option value="0" ' . ($negate_filter ? '' : 'selected') . '>Normal Match</option><option value="1" ' . ($negate_filter ? 'selected' : '') . '>Inverted Match</option></select>';
	$s .= '<select name="logic_filter[]"><option ' . ($logic_filter == 'AND' ? 'selected' : '') . '>AND</option><option ' . ($logic_filter == 'OR' ? 'selected' : '') . '>OR</option></select>';
	return $s;
}


function sanitize_environment()
{
	global $config;
	global $errors;

	if (! in_array($_GET['environment'], $config['environments']))
	{
		$errors[] = 'Environment "' . $_GET['environment'] . '" is not known to this interface.';
		return 0;
	}
	return 1;
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


function sanitize_language()
{
	global $config;
	global $errors;

	if (! in_array($_GET['language'], $config['languages']))
	{
		$errors[] = 'Language "' . $_GET['language'] . '" is not known to this interface.';
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
 * Classes
 ******************************************************************************/
include_once(dirname(__FILE__) . '/lib/LineOutput.class.php');
include_once(dirname(__FILE__) . '/lib/Log.class.php');
include_once(dirname(__FILE__) . '/lib/LogFile.class.php');
include_once(dirname(__FILE__) . '/lib/SkipWarning.class.php');


/******************************************************************************
 * Global Variables and Executable Code
 ******************************************************************************/
$errors = array();
$warnings = array();

set_magic_quotes_runtime(0);
if (get_magic_quotes_gpc())
{
	slash_machine($_GET);
}

/**
 * Allow a start_timestamp GET parameter to "fill in the blanks" for the start_* set of date and time entries.
 * This allows us to just pass in start_timestamp if we want, and the other start_* set of UI entries will be
 * populated correctly from start_timestamp.
 **/
if (isset($_GET['start_timestamp']))
{
	$_GET['start_timestamp'] = getdate(intval($_GET['start_timestamp']));
	$_GET['start_year'] = (strlen($_GET['start_year']) ? $_GET['start_year'] : $_GET['start_timestamp']['year']);
	$_GET['start_month'] = (strlen($_GET['start_month']) ? $_GET['start_month'] : $_GET['start_timestamp']['mon']);
	$_GET['start_day'] = (strlen($_GET['start_day']) ? $_GET['start_day'] : $_GET['start_timestamp']['mday']);
	$_GET['start_hour'] = (strlen($_GET['start_hour']) ? $_GET['start_hour'] : $_GET['start_timestamp']['hours']);
	$_GET['start_minute'] = (strlen($_GET['start_minute']) ? $_GET['start_minute'] : $_GET['start_timestamp']['minutes']);
	$_GET['start_second'] = (strlen($_GET['start_second']) ? $_GET['start_second'] : $_GET['start_timestamp']['seconds']);
	unset($_GET['start_timestamp']);
}

/**
 * Allow an end_timestamp GET parameter to "fill in the blanks" for the end_* set of date and time entries.
 * This allows us to just pass in end_timestamp if we want, and the other end_* set of UI entries will be
 * populated correctly from end_timestamp.
 **/
if (isset($_GET['end_timestamp']))
{
	$_GET['end_timestamp'] = getdate(intval($_GET['end_timestamp']));
	$_GET['end_year'] = (strlen($_GET['end_year']) ? $_GET['end_year'] : $_GET['end_timestamp']['year']);
	$_GET['end_month'] = (strlen($_GET['end_month']) ? $_GET['end_month'] : $_GET['end_timestamp']['mon']);
	$_GET['end_day'] = (strlen($_GET['end_day']) ? $_GET['end_day'] : $_GET['end_timestamp']['mday']);
	$_GET['end_hour'] = (strlen($_GET['end_hour']) ? $_GET['end_hour'] : $_GET['end_timestamp']['hours']);
	$_GET['end_minute'] = (strlen($_GET['end_minute']) ? $_GET['end_minute'] : $_GET['end_timestamp']['minutes']);
	$_GET['end_second'] = (strlen($_GET['end_second']) ? $_GET['end_second'] : $_GET['end_timestamp']['seconds']);
	unset($_GET['end_timestamp']);
}

if (isset($_GET['environment']) && isset($_GET['language']))
{
	if (! sanitize_environment())
	{
		unset($_GET['environment']);
	}
	if (! sanitize_language())
	{
		unset($_GET['language']);
	}
	sanitize_filter();
	sanitize_negate_filter();
	sanitize_logic_filter();
}

if (isset($_GET['environment']) && isset($_GET['language']))
{
	$end_timestamp = mktime($_GET['end_hour'], $_GET['end_minute'], $_GET['end_second'], $_GET['end_month'], $_GET['end_day'], $_GET['end_year']);
	$log_timestamp = mktime($_GET['start_hour'], 0, 0, $_GET['start_month'], $_GET['start_day'], $_GET['start_year']);
	$start_timestamp = mktime($_GET['start_hour'], $_GET['start_minute'], $_GET['start_second'], $_GET['start_month'], $_GET['start_day'], $_GET['start_year']);

	/**
	 * This script can be quite costly to run; but we specifically do not want it to timeout
	 * on a system administrator or developer who is searching over a large amount of logs.
	 * Therefore we allow the script to run for 1 second for each 10 seconds of the requested timeframe.
	 * This could be dangerous, however a conscious choice has been made to be extravagant with
	 * the resources allocated to this script to make things nicer for the users of it.
	 **/
	set_time_limit(max(10, ceil(($end_timestamp - $start_timestamp) / 10)));
}
?>
<html>
	<head>
		<title>Simple Online Log Viewer</title>
		<link rel="stylesheet" href="log_viewer.css">
		<script src="log_viewer.js"></script>
	</head>
	<body onload='highlight_named_anchor();'>
		<div id='filter_template'>
			<!-- This is an invisible div that simply serves as a template for the HTML that defines a filter. -->
			<?php echo filter_form_string(); ?>
		</div>
		<form id="control_form" method="get">
			<table width="100%">
				<tr>
					<td>
						Env:
						<select name='environment'>
							<option></option>
							<?php
								foreach ($config['environments'] as $env)
								{
									echo '<option' . ($_GET['environment'] == $env ? ' selected' : '') . ">$env</option>\n";
								}
							?>
						</select>
					</td>
					<td>
						Lang:
						<select name='language'>
							<option></option>
							<?php
								foreach ($config['languages'] as $lang)
								{
									echo '<option' . ($_GET['language'] == $lang ? ' selected' : '') . ">$lang</option>\n";
								}
							?>
						</select>
					</td>
					<td style='text-align: center;'>
						<?php
							echo create_numeric_select('start_hour', 0, 23, date('H') - 1, create_function('$hour', 'return sprintf("%02d", $hour);')) . ' : ';
							echo create_numeric_select('start_minute', 0, 59, 0, create_function('$min', 'return sprintf("%02d", $min);')) . ' : ';
							echo create_numeric_select('start_second', 0, 59, 0, create_function('$sec', 'return sprintf("%02d", $sec);')) . ' on ';
							echo create_numeric_select('start_month', date('m', strtotime('1 month ago')), date('m'), date('m'), create_function('$mon', 'return strftime("%b", mktime(12, 0, 0, $mon, 15));')) . ' ';
							echo create_numeric_select('start_day', 1, 31, date('d'), create_function('$day', 'return sprintf("%02d", $day);')) . ', ';
							echo create_numeric_select('start_year', date('Y', strtotime('1 month ago')), date('Y'), null, create_function('$year', 'return sprintf("%04d", $year);'));
						?>
						thru
						<?php
							echo create_numeric_select('end_hour', 0, 23, date('H') + 1, create_function('$hour', 'return sprintf("%02d", $hour);')) . ' : ';
							echo create_numeric_select('end_minute', 0, 59, 0, create_function('$min', 'return sprintf("%02d", $min);')) . ' : ';
							echo create_numeric_select('end_second', 0, 59, 0, create_function('$sec', 'return sprintf("%02d", $sec);')) . ' on ';
							echo create_numeric_select('end_month', date('m', strtotime('1 month ago')), date('m'), date('m'), create_function('$mon', 'return strftime("%b", mktime(12, 0, 0, $mon, 15));')) . ' ';
							echo create_numeric_select('end_day', 1, 31, date('d'), create_function('$day', 'return sprintf("%02d", $day);')) . ', ';
							echo create_numeric_select('end_year', date('Y', strtotime('1 month ago')), date('Y'), null, create_function('$year', 'return sprintf("%04d", $year);'));
						?>
					</td>
					<td style="text-align: right;">
						<input type='button' value='?' onclick='toggle_display("documentation_table");' />
					</td>
				</tr>
				<tr>
					<td>
						<input type='button' value='Add Filter' onclick='add_filter();' />
					</td>
					<td>
						<input type='button' value='Parse Token' onclick='parse_token();' />
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
					<td style="text-align: right;">
						<input style="margin-right: 10px;" type='button' value='Tail' onclick='tail();' />
						<input type='submit' value='Submit' />
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
						<td><i>Environment</i></td>
						<td>Which environment to retrieve log data for.</td>
						<td>&nbsp;</td>
					</tr>
					<tr>
						<td><i>Language</i></td>
						<td>Which language to retrieve log data for.</td>
						<td>&nbsp;</td>
					</tr>
					<tr>
						<td><i>Timeframe</i></td>
						<td>
							These two controls specify the timeframe within which log data should be returned.  Log data outside of this timeframe will
							not be retrieved.
						</td>
						<td>+/- one hour</td>
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
				SkipWarning::reset();
				while (isset($log_timestamp) && ($log_timestamp < $end_timestamp) && (LineOutput::$displayed_lines <= MAX_LINES))
				{
					$log_path = sprintf('%s/%s/%s.%s.log', $config['log_root'], $_GET['environment'], strftime('%Y-%m-%d-%H', $log_timestamp), $_GET['language']);
					if ((! file_exists($log_path)) && file_exists($log_path . '.gz'))
					{
						// We don't have an uncompressed version of the log, but we do have a compressed
						// version, so use that.
						$log_path .= '.gz';
					}
					$log_file = new LogFile();
					if (defined('DEBUG') && DEBUG)
					{
						echo '<div class="debug">Beginning logfile: ' . $log_path . '</div>';
					}
					if (! $log_file->open($log_path))
					{
						echo '<div class="error">' . $log_path . " could not be opened for reading.</div>\n";
						$log_timestamp += 3600;
						continue;
					}

					while ($current_line = $log_file->gets())
					{
						if ($current_line->log_timestamp() < $start_timestamp)
						{
							continue;
						}
						if ($current_line->log_timestamp() > $end_timestamp)
						{
							echo SkipWarning::warning(true);
							echo "<div class='warning'>Reached end of requested timeframe (" . strftime('%Y-%m-%d %H:%M:%S', $end_timestamp) . ").</div>";
							break 2;
						}
						if (LineOutput::$displayed_lines >= MAX_LINES)
						{
							echo SkipWarning::warning(true);
							echo "<div class='warning'>Encountered maximum line display limit of " . number_format(MAX_LINES) . " lines.</div>";
							break 2;
						}

						// If we have filters, skip lines that do not match them.
						if ($_GET['filter'] && (! $current_line->matches_filters()))
						{
							SkipWarning::add();
							continue;
						}

						echo SkipWarning::warning(true);
						LineOutput::display($current_line);
					}
					if (defined('DEBUG') && DEBUG)
					{
						echo '<div class="debug">Reached end of logfile: ' . $log_path . '</div>';
					}
					$log_timestamp += 3600;
				}
				echo SkipWarning::warning(true);
				if (isset($log_timestamp))
				{
					if ($current_line && ($current_line->log_timestamp() <= $end_timestamp))
					{
						echo "<div class='warning'>Reached end of available logging data before reaching end of requested timeframe (" . strftime('%Y-%m-%d %H:%M:%S', $end_timestamp) . ").</div>";
					}
					if ((LineOutput::$displayed_lines == 0) && (SkipWarning::$total_skipped == 0))
					{
						echo "<div class='warning'>No log lines were found for the requested timeframe (" . strftime('%Y-%m-%d %H:%M:%S', $start_timestamp) . " thru " . strftime('%Y-%m-%d %H:%M:%S', $end_timestamp) . ').</div>';
					}
				}
			?>
			<a name="tail"></a>
		</div>
		<?php
			if (LineOutput::$displayed_lines > 0)
			{
		?>
				<div style="text-align: right;">
					<input style="margin-right: 10px;" type='button' value='Tail' onclick='tail();' />
				</div>
		<?php
			}
		?>
	</body>
</html>
