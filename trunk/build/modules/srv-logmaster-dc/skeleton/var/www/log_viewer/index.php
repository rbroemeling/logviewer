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
if (! defined('DEBUG'))
{
	if ($_GET['debug'])
	{
		define('DEBUG', true);
	}
	else
	{
		define('DEBUG', false);
	}
}

/******************************************************************************
 * Configuration details about the location of log files.  In general, log file
 * paths are expected to follow the below template:
 *
 * $config['log_root']/$log_environment/YYYY-MM-DD-HH.$log_language.log
 *
 ******************************************************************************/
$config = array();

// A list of log facilities that can be filtered on by the user.
$config['log_facilities'] = array('cron', 'fileserving', 'general', 'memcache', 'moderator', 'orwell', 'pagehandler', 'plus', 'sql');

// A list of log levels that can be filtered on by the user.
$config['log_levels'] = array('critical', 'error', 'warning', 'info', 'debug', 'spam');

// The root log directory that contains sub-directories for each environment.
$config['log_root'] = '/var/log/development';

// A list of environments to be exposed to the user.
$config['environments'] = array('beta', 'live', 'stage');

// A list of languages to be exposed to the user.
$config['languages'] = array('dispatcher', 'php', 'ruby');

// A list of source hosts that can be filtered on by the user.
$config['source_hosts'] = array();
for ($i = 100; $i <= 111; $i++)
{
	$config['source_hosts'][] = '10.0.3.' . $i;
}
for ($i = 200; $i <= 211; $i++)
{
	$config['source_hosts'][] = '10.0.3.' . $i;
}

/******************************************************************************
 * Utility Functions
 ******************************************************************************/
function array_to_hash($arr)
{
	$hash = array();
	foreach ($arr as $key)
	{
		$hash[$key] = true;
	}
	return $hash;
}


function create_numeric_select($name, $tooltip, $min, $max, $default_value = null, $label_formatter = null)
{
	$tooltip = htmlspecialchars($tooltip, ENT_QUOTES);
	$min = intval($min);
	$max = intval($max);
	if (is_null($default_value))
	{
		$default_value = $min;
	}
	else
	{
		$default_value = intval($default_value);
	}
	if (is_null($label_formatter))
	{
		$label_formatter = create_function('$i', 'return $i;');
	}

	$s = array();
	if ($min > $max)
	{
		// If $min > $max, then create two options, one for each of
		// $min and $max; and then call that good.
		$s[$min] = '<option value="' . $min . '">' . call_user_func($label_formatter, $min) . '</option>';
		$s[$max] = '<option value="' . $max . '">' . call_user_func($label_formatter, $max) . '</option>';
	}
	else
	{
		// $min <= $max, so create one option for $i in $min .. $max.
		for ($i = $min; $i <= $max; $i++)
		{
			$s[$i] = '<option value="' . $i . '">' . call_user_func($label_formatter, $i) . '</option>';
		}
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
		$i = $default_value;
	}
	$s[$i] = '<option value="' . $i . '" selected>' . call_user_func($label_formatter, $i) . '</option>';

	return '<select alt="' . $tooltip . '" name="' . $name . '" title="' . $tooltip . '">' . "\n" . implode("\n", $s) . "</select>\n";
}


function filter_form_string($filter = null, $negate_filter = 0, $logic_filter = 'OR')
{
	$remove_tooltip = htmlspecialchars('Remove this filter.', ENT_QUOTES);
	$filter_tooltip = htmlspecialchars('Pattern to match against each line.  This is treated as a plain-text search unless it begins and ends with the "/" character, which forces it to be treated as a regular expression pattern.', ENT_QUOTES);
	$negation_tooltip = htmlspecialchars('"Normal Match" means that log lines matching this pattern will be INCLUDED in the results, "Inverted Match" means that log lines matching this pattern will be EXCLUDED from the results.', ENT_QUOTES);
	$logical_tooltip = htmlspecialchars('Logical operation that relates this filter to the rest of the filters.  "AND" binds more tightly than "OR", so "a AND b AND c OR d AND e" equates to "((a AND b AND c) OR (d AND e))".', ENT_QUOTES);
	
	$s = '';
	$s .= '<input alt="' . $remove_tooltip . '" title="' . $remove_tooltip . '" type="button" style="margin-right: 10px;" value="-" onclick="this.parentNode.parentNode.removeChild(this.parentNode);" />';
	$s .= 'Filter: <input alt="' . $filter_tooltip . '" title="' . $filter_tooltip. '" type="text" name="filter[]" size="80" style="margin-right: 10px;" maxlength="100" value="' . htmlspecialchars($filter, ENT_QUOTES) . '">';
	$s .= '<select alt="' . $negation_tooltip . '" title="' . $negation_tooltip . '" name="negate_filter[]" style="margin-right: 10px;"><option value="0" ' . ($negate_filter ? '' : 'selected') . '>Normal Match</option><option value="1" ' . ($negate_filter ? 'selected' : '') . '>Inverted Match</option></select>';
	$s .= '<select alt="' . $logical_tooltip . '" title="' . $logical_tooltip . '" name="logic_filter[]"><option ' . ($logic_filter == 'AND' ? 'selected' : '') . '>AND</option><option ' . ($logic_filter == 'OR' ? 'selected' : '') . '>OR</option></select>';
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
			$warnings[] = 'Filter #' . ($i + 1) . ' is empty.  Empty filters will match any string.';
			continue;
		}
		if (! is_string($_GET['filter'][$i]))
		{
			$errors[] = 'Filter #' . ($i + 1) . ' is not a string.';
			$regular_expression_errors++;
			continue;
		}
		if (preg_match('!^/.*/$!', $_GET['filter'][$i]))
		{
			// This filter is a regular expression, and thus needs to be tested for validity.
			set_error_handler('sanitize_filter');
			preg_match($_GET['filter'][$i], '');
			restore_error_handler();
			if ($regular_expression_errstr)
			{
				$regular_expression_errstr = explode(':', $regular_expression_errstr);
				$errors[] = 'Filter #' . ($i + 1) . ' ("' . $_GET['filter'][$i] . '") is not a valid regular expression: ' . $regular_expression_errstr[1] . '.';
				$regular_expression_errstr = '';
				$regular_expression_errors++;
			}
		}
		else
		{
			// This filter is a plaintext match... there really isn't any sort of testing to be done.
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

/**
 * Ensure that our display_extended_filters get variable is populated correctly
 * with an obviously boolean value.
 **/
if (! isset($_GET['display_extended_filters']))
{
	$_GET['display_extended_filters'] = 1;
}
$_GET['display_extended_filters'] = $_GET['display_extended_filters'] ? 1 : 0;

/**
 * Sanitize our environment and language get variables and unset them if they
 * are not valid.
 **/
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
}

/**
 * If our environment and language variables are valid, check the rest of our
 * filters and assuming that they all check out populate $end_timestamp,
 * $log_timestamp, and $start_timestamp with the range that this query to the
 * log viewer will iterate through.
 **/
if (isset($_GET['environment']) && isset($_GET['language']))
{
	if (sanitize_filter() && sanitize_negate_filter() && sanitize_logic_filter())
	{
		$end_timestamp = mktime($_GET['end_hour'], $_GET['end_minute'], $_GET['end_second'], $_GET['end_month'], $_GET['end_day'], $_GET['end_year']);
		$log_timestamp = mktime($_GET['start_hour'], 0, 0, $_GET['start_month'], $_GET['start_day'], $_GET['start_year']);
		$start_timestamp = mktime($_GET['start_hour'], $_GET['start_minute'], $_GET['start_second'], $_GET['start_month'], $_GET['start_day'], $_GET['start_year']);

		/**
		 * Convert our filter arrays (source hosts, log levels, and log facilities) into
		 * hashes.  This saves us from needing to search through the array to see if a
		 * value exists and is much more performant.
		 **/
		if ($_GET['log_facilities'])
		{
			$_GET['log_facilities'] = array_to_hash($_GET['log_facilities']);
		}
		if ($_GET['log_levels'])
		{
			$_GET['log_levels'] = array_to_hash($_GET['log_levels']);
		}
		if ($_GET['source_hosts'])
		{
			$_GET['source_hosts'] = array_to_hash($_GET['source_hosts']);
		}

		/**
		 * This script can be quite costly to run; but we specifically do not want it to timeout
		 * on a system administrator or developer who is searching over a large amount of logs.
		 * Therefore we allow the script to run for 1 second for each 10 seconds of the requested
		 * timeframe, with a minimum of 30 seconds.  This could be dangerous, however a conscious
		 * choice has been made to be extravagant with the resources allocated to this script to
		 * make things nicer for the users of it.
		 **/
		set_time_limit(max(30, ceil(($end_timestamp - $start_timestamp) / 10)));
	}
}
?>
<html>
	<head>
		<title>Simple Online Log Viewer</title>
		<link rel="stylesheet" href="log_viewer.css">
		<script src="shortcut.js"></script>
		<script src="log_viewer.js"></script>
		<script>
			shortcut.add("CTRL+ALT+H", hide_selection, { 'disable_in_input': true });
			shortcut.add("CTRL+ALT+R", refresh_view, { 'disable_in_input': true });
			shortcut.add("CTRL+ALT+T", tail, { 'disable_in_input': true });
		</script>
	</head>
	<body onload='highlight_named_anchor();'>
		<div id='filter_template'>
			<!-- This is an invisible div that simply serves as a template for the HTML that defines a filter. -->
			<?php echo filter_form_string(); ?>
		</div>
		<form id="control_form" method="get" onsubmit="document.getElementsByName('display_extended_filters')[0].value = is_visible('extended_filters'); return 1;">
			<input type='hidden' name='display_extended_filters' value=''>
			<div style="margin-bottom: 5px; text-align: right;">
				<div style="float: left; text-align: left; width: 750px;">
					<span style="margin-left: 10px; margin-right: 10px;">
						Environment:
						<select alt="The environment from which to fetch log lines." title="The environment from which to fetch log lines." name="environment" style="width: 200px;">
							<?php
								$selected_environment = (isset($_GET['environment']) ? $_GET['environment'] : 'live');
								foreach ($config['environments'] as $env)
								{
									$selected = '';
									if ($selected_environment == $env)
									{
										$selected = 'selected';
									}
									echo "<option $selected>$env</option>\n";
								}
								unset($selected_environment);
							?>
						</select>
					</span>
					<span alt="The language for which to view log lines." title="The language for which to view log lines." style="margin-left: 10px; margin-right: 10px;">
						Language:
						<select name="language" style="width: 200px;">
							<?php
								$selected_language = (isset($_GET['language']) ? $_GET['language'] : 'ruby');
								foreach ($config['languages'] as $lang)
								{
									$selected = '';
									if ($selected_language == $lang)
									{
										$selected = 'selected';
									}
									echo "<option $selected>$lang</option>\n";
								}
								unset($selected_language);
							?>
						</select>
					</span>
				</div>
				<span onclick="var v = toggle_display('hotkey_list'); this.innerHTML = '[' + (v ? 'Hide' : 'Show') + ' Hotkey List]';" style="cursor: pointer; margin-right: 10px;">
					[Show Hotkey List]
				</span>
				<?php
					if ($_GET['display_extended_filters'])
					{
						$extended_filter_tooltip = 'Hide extended filter options.';
						$extended_filter_icon = '&#9660;';
						$extended_filter_style = '';
					}
					else
					{
						$extended_filter_tooltip = 'Show extended filter options.';
						$extended_filter_icon = '&#9668;';
						$extended_filter_style = 'display: none;';
					}
				?>
				<span alt="<?php echo $extended_filter_tooltip; ?>" title="<?php echo $extended_filter_tooltip; unset($extended_filter_tooltip); ?>" onclick="var v = toggle_display('extended_filters'); this.innerHTML = (v ? '&#9660;' : '&#9668;'); this.alt = (v ? 'Hide' : 'Show') + ' extended filter options.'; this.title = this.alt;" style="cursor: pointer;">
					<?php echo $extended_filter_icon; unset($extended_filter_icon); ?>
				</span>
			</div>
			<div style="clear: both;"></div>
			<table id="hotkey_list" style="display: none;">
				<caption>Annoying-but-necessary table that I don't know where else to put.</caption>
				<thead>
					<tr>
						<th>Hotkey</th>
						<th>Function</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>CTRL+ALT+H</td>
						<td>Generate a filter to hide lines containing the selected text.</td>
					</tr>
					<tr>
						<td>CTRL+ALT+R</td>
						<td>Force a refresh of the current view.</td>
					</tr>
					<tr>
						<td>CTRL+ALT+T</td>
						<td>Force a refresh of the current view by performing a "Tail".</td>
					</tr>
				</tbody>
			</table>
			<table id="extended_filters" style="<?php echo $extended_filter_style; unset($extended_filter_style); ?>" width="100%">
				<tr>
					<td style="text-align: center;">
						Source Host(s):<br>
						<select alt="Filter the displayed log lines by source dynamic server." title="Filter the displayed log lines by source dynamic server." multiple id="source_hosts" name="source_hosts[]" size="6">
							<?php
								foreach ($config['source_hosts'] as $source_host)
								{
									$selected = $_GET['source_hosts'][$source_host] ? 'selected' : '';
									echo "<option $selected>$source_host</option>\n";
								}
							?>
						</select>
						<div style="margin: auto; text-align: left; width: 200px;">
							<div style="float: right; text-align: right; width: 50%;">
								<input alt="Invert selection of source hosts." title="Invert selection of source hosts." type="button" value="Invert" onclick="invert_selection('source_hosts');">	
							</div>
							<input alt="Clear selection of source hosts." title="Clear selection of source hosts." type="button" value="Clear" onclick="clear_selection('source_hosts');">
						</div>
					</td>
					<td style="text-align: center;">
						Log Level(s):<br>
						<select alt="Filter the displayed log lines by error level." title="Filter the displayed log lines by error level." multiple id="log_levels" name="log_levels[]" size="6">
							<?php
								foreach ($config['log_levels'] as $log_level)
								{
									$selected = $_GET['log_levels'][$log_level] ? 'selected' : '';
									echo "<option $selected>$log_level</option>\n";
								}
							?>
						</select>
						<div style="margin: auto; text-align: left; width: 200px;">
							<div style="float: right; text-align: right; width: 50%;">
								<input alt="Invert selection of error levels." title="Invert selection of error levels." type="button" value="Invert" onclick="invert_selection('log_levels');">	
							</div>
							<input alt="Clear selection of error levels." title="Clear selection of error levels." type="button" value="Clear" onclick="clear_selection('log_levels');">
						</div>
					</td>
					<td style="text-align: center;">
						Log Facility(ies):<br>
						<select alt="Filter the displayed log lines by source facility/component." title="Filter the displayed log lines by source facility/component." multiple id="log_facilities" name="log_facilities[]" size="6">
							<?php
								foreach ($config['log_facilities'] as $log_facility)
								{
									$selected = $_GET['log_facilities'][$log_facility] ? 'selected' : '';
									echo "<option $selected>$log_facility</option>\n";
								}
							?>
						</select>
						<div style="margin: auto; text-align: left; width: 200px;">
							<div style="float: right; text-align: right; width: 50%;">
								<input alt="Invert selection of log facilities." title="Invert selection of log facilities." type="button" value="Invert" onclick="invert_selection('log_facilities');">	
							</div>
							<input alt="Clear selection of log facilities." title="Clear selection of log facilities." type="button" value="Clear" onclick="clear_selection('log_facilities');">
						</div>
					</td>
				</tr>
				<tr>
					<td colspan="3" style="border-top: 1px dotted green; padding-top: 5px; text-align: right;">
						<input alt="Add a text filter to match on." title="Add a text filter to match on." type="button" value="Add Filter" onclick="add_filter(null, null, null);">
					</td>
				</tr>
				<tr>
					<td colspan="3" style="border-bottom: 1px dotted green;">
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
					</td>
				</tr>
			</table>
			<table id="basic_filters" width="100%">
				<tr>
					<td style="text-align: center;">
						<?php
							echo create_numeric_select('start_hour', 'Time of first log entry to display (hours).', 0, 23, date('H') - 1, create_function('$hour', 'return sprintf("%02d", $hour);')) . ' : ';
							echo create_numeric_select('start_minute', 'Time of first log entry to display (minutes).', 0, 59, 0, create_function('$min', 'return sprintf("%02d", $min);')) . ' : ';
							echo create_numeric_select('start_second', 'Time of first log entry to display (seconds).', 0, 59, 0, create_function('$sec', 'return sprintf("%02d", $sec);')) . ' on ';
							echo create_numeric_select('start_month', 'Date of first log entry to display (month).', date('m', strtotime('1 month ago')), date('m'), date('m'), create_function('$mon', 'return strftime("%b", mktime(12, 0, 0, $mon, 15));')) . ' ';
							echo create_numeric_select('start_day', 'Date of first log entry to display (day).', 1, 31, date('d'), create_function('$day', 'return sprintf("%02d", $day);')) . ', ';
							echo create_numeric_select('start_year', 'Date of first log entry to display (year).', date('Y', strtotime('1 month ago')), date('Y'), date('Y'), create_function('$year', 'return sprintf("%04d", $year);'));
						?>
						thru
						<?php
							echo create_numeric_select('end_hour', 'Time of last log entry to display (hours).', 0, 23, date('H') + 1, create_function('$hour', 'return sprintf("%02d", $hour);')) . ' : ';
							echo create_numeric_select('end_minute', 'Time of last log entry to display (minutes).', 0, 59, 0, create_function('$min', 'return sprintf("%02d", $min);')) . ' : ';
							echo create_numeric_select('end_second', 'Time of last log entry to display (seconds).', 0, 59, 0, create_function('$sec', 'return sprintf("%02d", $sec);')) . ' on ';
							echo create_numeric_select('end_month', 'Date of last log entry to display (month).', date('m', strtotime('1 month ago')), date('m'), date('m'), create_function('$mon', 'return strftime("%b", mktime(12, 0, 0, $mon, 15));')) . ' ';
							echo create_numeric_select('end_day', 'Date of last log entry to display (day).', 1, 31, date('d'), create_function('$day', 'return sprintf("%02d", $day);')) . ', ';
							echo create_numeric_select('end_year', 'Date of last log entry to display (year).', date('Y', strtotime('1 month ago')), date('Y'), date('Y'), create_function('$year', 'return sprintf("%04d", $year);'));
						?>
					</td>
					<td style="text-align: right;">
						<input alt="Parse a log token and display the associated log line(s) in the log viewer." title="Parse a log token and find the associated log line(s)." style="margin-right: 15px;" type="button" value="Parse Token" onclick="parse_token(null);">
						<input alt="(CTRL+ALT+T) Update the log viewer to display the last 60 seconds of logging data." title="(CTRL+ALT+T) Update the log viewer to display the last 60 seconds of logging data." style="margin-right: 15px;" type="button" value="Tail" onclick="tail();">
						<input alt="(CTRL+ALT+R) Refresh the log viewer results." title="(CTRL+ALT+R) Refresh the log viewer results." type="button" value="Refresh" onclick="refresh_view();">
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
					if (DEBUG)
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
						if ($current_line->syslog_timestamp < $start_timestamp)
						{
							continue;
						}
						if ($current_line->syslog_timestamp > $end_timestamp)
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

						// If we have any filters, skip the lines that do not match them.
						if (
						    (! $current_line->matches_source_hosts()) ||
						    (! $current_line->matches_log_levels()) ||
						    (! $current_line->matches_log_facilities()) ||
						    (! $current_line->matches_filters())
						    )
						{
							SkipWarning::add();
							continue;
						}

						echo SkipWarning::warning(true);
						LineOutput::display($current_line);
					}
					if (DEBUG)
					{
						echo '<div class="debug">Reached end of logfile: ' . $log_path . '</div>';
					}
					$log_timestamp += 3600;
				}
				echo SkipWarning::warning(true);
				if (isset($log_timestamp))
				{
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
					<input alt="(CTRL+ALT+T) Update the log viewer to display the last 60 seconds of logging data." title="(CTRL+ALT+T) Update the log viewer to display the last 60 seconds of logging data." style="margin-right: 15px;" type="button" value="Tail" onclick="tail();" />
				</div>
		<?php
			}
			if (isset($_GET['token']))
			{
				echo '<script>parse_token(' . json_encode($_GET['token']) . ');</script>';
			}
		?>
	</body>
</html>
