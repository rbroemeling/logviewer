<?php
// Default length is -1 * 16 Kb
define('DEFAULT_LENGTH', -1 * 16 * 1024);

// Maximum number of lines to ever display, the script will never display more
// than this number of lines.
define('MAX_LINES', 15000);

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


set_magic_quotes_runtime(0);
if (get_magic_quotes_gpc())
{
	slash_machine($_GET);
}

$log_handle = 0;
if ($_GET['log'] && sanitize_log() && sanitize_offset() && sanitize_length() && sanitize_position())
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
			
			div.error
			{
				color: #ff0000;
				font-weight: bold;
				margin-bottom: 10px;
			}
			div.warning
			{
				color: #ffff00;
				font-weight: bold;
				margin-bottom: 10px;
			}

			div#log_excerpt
			{
				margin-top: 30px;
			}
			div.log_line
			{
				font-family: monospace;
				margin-bottom: 10px;
			}
		</style>
		<script type="text/javascript">
			function toggle_documentation()
			{
				documentation = document.getElementById('documentation');
				
				if (documentation.style.display == 'none')
				{
					documentation.style.display = 'block';	
				}
				else
				{
					documentation.style.display = 'none';	
				}
			}
		</script>
	</head>
	<body>
		<form action="<?php echo $_SERVER['SCRIPT_NAME']; ?>" method="get">
			<table width="100%">
				<tr>
					<td>
						Log File: 
						<select id='log' name='log'>
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
						<input type='button' value='Tail' onclick='window.location = "?log=" + document.getElementById("log").value + "&timestamp=" + (new Date()).getTime() + "#tail";' />
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
			[<a href="javascript:toggle_documentation()">Documentation</a>]
			<table id="documentation" style="display: none" width="100%">
				<thead>
					<tr>
						<th>Field</th>
						<th>Description</th>
						<th>Default</th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><i>Log File</i></td>
						<td>Which log file to retrieve data from.</td>
						<td></td>
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
						<td><i>Filter</i></td>
						<td>A string to look for within the data retrieved from the log file.  Only log lines containing the string will be displayed.</td>
						<td></td>
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

					$filter_count = 0;
					$line_display_count = 0;
					while ((! feof($log_handle)) && ($current_position <= ($_GET['offset'] + $_GET['length'])) && ($line_display_count < MAX_LINES))
					{
						$current_line = fgets($log_handle);

						$line_start_position = $current_position;
						$current_position += strlen($current_line);
						$line_end_position = $current_position - 1;

						// Remove any trailing newline character.
						if ($current_line !== FALSE)
						{
							$current_line = rtrim($current_line, "\n");
						}

						// If we have a filter, skip lines that do not match it.
						if ($_GET['filter'])
						{
							if (($current_line !== FALSE) && (stristr($current_line, $_GET['filter']) == FALSE))
							{
								$filter_count++;
								continue;
							}
							if ($filter_count)
							{
								echo "<div class='warning'>Skipped " . number_format($filter_count) . " log lines based on filters applied.</div>\n";
								$filter_count = 0;
							}
						}

						// Check if we are at EOF.
						if ($current_line === FALSE)
						{
							continue;
						}

						// Quote the line and add <wbr> elements so that the line will wrap nicely.
						$current_line = str_split($current_line, 5);
						foreach (array_keys($current_line) as $i)
						{
							$current_line[$i] = htmlspecialchars($current_line[$i], ENT_QUOTES);
						}
						
						$line_display_count++;
						echo "<div class='log_line'>";
						echo "[<a href='?log=" . $_GET['log'] . "&offset=" . max(($line_start_position - 8192), 0) . "&length=12288#" . $line_start_position . "' name='" . $line_start_position . "'>" . $line_start_position .  "</a>] ";
						echo implode('<wbr>', $current_line);
						echo "</div>\n";
					}
					
					if ($line_display_count >= MAX_LINES)
					{
						echo "<div class='warning'>Encountered maximum line display limit of " . number_format(MAX_LINES) . " lines.</div>\n";
					}
					
					if ($current_position > ($_GET['offset'] + $_GET['length']))
					{
						echo "<div class='warning'>Encountered end of requested data at offset " . number_format($current_position) . ".</div>\n";
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
	</body>
</html>
