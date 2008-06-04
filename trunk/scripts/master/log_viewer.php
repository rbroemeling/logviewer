<?php
// Default length is -1 * 16 Kb
define('DEFAULT_LENGTH', -1 * 16 * 1024);

$errors = array();
$log_excerpt = array();
$log_size = -1;
$log_sources = array
(
	'php-beta' => '/var/log/php-beta.log',
	'php-live' => '/var/log/php-live.log',
	'php-stage' => '/var/log/php-stage.log',
	'ruby-beta' => '/var/log/ruby-beta.log',
	'ruby-live' => '/var/log/ruby-live.log',
	'ruby-stage' => '/var/log/ruby-stage.log'
);
$warnings = array();


function sanitize_length()
{
	global $errors;

	if (! strlen($_GET['length']))
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

	if (! $log_sources[$_GET['log']])
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


function sanitize_offset()
{
	global $errors;
	global $log_size;

	if (! strlen($_GET['offset']))
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
	global $log_size;
	global $warnings;

	if ($_GET['length'] > 0)
	{
		if (($_GET['offset'] + $_GET['length'] - 1) > $log_size)
		{
			$warnings[] = 'Impossible to read ' . number_format($_GET['length']) . ' bytes from offset ' . number_format($_GET['offset']) . '.  Log file size is only ' . number_format($log_size) . ' bytes.  Adjusting length to be ' . number_format($log_size - $_GET['offset'] + 1) . ' bytes.';
			$_GET['length'] = $log_size - $_GET['offset'] + 1;
		}
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


set_magic_quotes_runtime(0);
if (get_magic_quotes_gpc())
{
	slash_machine($_GET);
}

if ($_GET['log'] && sanitize_log() && sanitize_offset() && sanitize_length() && sanitize_position())
{
	if ($log_handle = fopen($log_sources[$_GET['log']], 'r'))
	{
		fseek($log_handle, $_GET['offset']);
		$log_excerpt = fread($log_handle, $_GET['length']);
		$log_excerpt = explode("\n", $log_excerpt);
		$current_offset = $_GET['offset'];
		foreach (array_keys($log_excerpt) as $i)
		{
			// Store the index of the first character of the line, for ease of use.
			$line_start = $current_offset;
			
			// Store the index of the last character of the line, for ease of use.
			$line_end = $current_offset + strlen($log_excerpt[$i]);
			
			// Advance our offset pointer to the beginning of the next line.
			$current_offset += strlen($log_excerpt[$i]);
			if ($i != (count($log_excerpt) - 1))
			{
				// +1 if we skipped a newline (i.e. if there is another element after this one)
				$current_offset++;
			}
			
			// Skip empty lines.  Can happen if our data stream started or ended with '\n'.
			if (! $log_excerpt[$i])
			{
				unset($log_excerpt[$i]);
				continue;
			}
			
			// If we have a filter, skip lines that do not match it.
			if ($_GET['filter'] && (stristr($log_excerpt[$i], $_GET['filter']) == FALSE))
			{
				unset($log_excerpt[$i]);
				continue;
			}
			
			// Quote the line and add <wbr> elements so that the line will wrap nicely.
			$log_excerpt[$i] = str_split($log_excerpt[$i], 5);
			foreach (array_keys($log_excerpt[$i]) as $j)
			{
				$log_excerpt[$i][$j] = htmlspecialchars($log_excerpt[$i][$j], ENT_QUOTES);
			}
			$log_excerpt[$i] = implode('<wbr>', $log_excerpt[$i]);
			
			$log_excerpt[$i] = "<div class='log_line'>[<a href='?log=" . $_GET['log'] . "&offset=" . $line_end . "&length=-8192'>" . $line_start .  "</a>] " . $log_excerpt[$i] . "</div>";
		}
	}
	else
	{
		$errors[] = $log_sources[$_GET['log']] . ' could not be opened for read.';
	}
}
?>
<html>
	<head>
		<title>Simple Online Log Viewer</title>
		<style type='text/css'>
			div#log_excerpt
			{
				font-family: monospace;
				font-size: 12px;
				margin-top: 30px;
			}

			div.error
			{
				color: #660000;
				font-weight: bold;
				margin-bottom: 10px;
			}
			div.warning
			{
				color: #c24e00;
				font-weight: bold;
				margin-bottom: 10px;
			}
			div.log_line
			{
				margin-bottom: 10px;
				text-align: justify;
			}
		</style>
	</head>
	<body>
		<form>
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
						Offset: <input type="text" name="offset" size="11" maxlength="10" value='<?php echo htmlspecialchars($_GET['offset'], ENT_QUOTES); ?>' />
					</td>
					<td style="text-align: center;">
						Length: <input type="text" name="length" size="9" maxlength="8" value='<?php echo htmlspecialchars($_GET['length'], ENT_QUOTES); ?>' />
					</td>
					<td style="text-align: center;">
						Filter: <input type='text' name='filter' size='20' maxlength='100' value='<?php echo htmlspecialchars($_GET['filter'], ENT_QUOTES); ?>' />
					</td>
					<td style="text-align: right;">
						<input type='submit' value='Refresh' />
					</td>
				</tr>
			</table>
		</form>
		<table width="100%">
			<thead>
				<tr>
					<th>Field Name</th>
					<th>Description</th>
					<th>Default Value</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>Log File</td>
					<td>Which log file to retrieve data from.</td>
					<td></td>
				</tr>
				<tr>
					<td>Offset</td>
					<td>The byte position to begin fetching data from within the log file.</td>
					<td>The last byte position in the log file.</td>
				</tr>
				<tr>
					<td>Length</td>
					<td>The amount of data to read from the log file.  Reads forwards from the offset if positive and reads backwards from the offset if negative.</td>
					<td><?php echo number_format(DEFAULT_LENGTH); ?></td>
				</tr>
				<tr>
					<td>Filter</td>
					<td>A string to look for within the data retrieved from the log file.  Only log lines containing the string will be displayed.</td>
					<td></td>
				</tr>
			</tbody>
		</table>
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
			<?php echo implode("\n", $log_excerpt); ?>
		</div>
	</body>
</html>
