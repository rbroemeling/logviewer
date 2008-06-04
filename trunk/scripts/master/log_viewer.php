<?php
// Default length is -1 * 64 Kb
define('DEFAULT_LENGTH', -1 * 64 * 1024);


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
		for ($i = $i; $i < count(self::$fields); $i++)
		{
			$string .= htmlspecialchars(self::$fields[$i], ENT_QUOTES);
		}
		return substr($string, 0, -1);
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
		return substr($string, 0, -1);
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
		
		$string .= htmlspecialchars(self::$ruby_fields[$i++], ENT_QUOTES) . ".";
		
		$string .= "<span class='errorlevel_" . parent::$error_level . "'>" . htmlspecialchars(self::$ruby_fields[$i++], ENT_QUOTES) . "</span>:";
		
		for ($i = $i; $i < count(self::$ruby_fields); $i++)
		{
			$string .= htmlspecialchars(self::$ruby_fields[$i], ENT_QUOTES);
		}
		return substr($string, 0, -1);
	}
	
	
	public static function parse($line)
	{
		if (! parent::parse($line))
		{
			return false;
		}
		if (preg_match('!(\d+)\.([^.]+)\.([^:]+):(.*)!i', parent::$fields[count(LogLine::$fields) - 1], self::$ruby_fields))
		{
			array_pop(parent::$fields);
			array_shift(self::$ruby_fields);
			
			parent::$error_level = self::$ruby_fields[2];
			return true;
		}
		else
		{
			return false;
		}
	}
}


function format_line($line)
{
	if (preg_match('/ PHP error: /', $line))
	{
		PHPLogLine::parse($line);
		return PHPLogLine::display();
	}
	if (preg_match('/nexopia(-child|-parent|\.rb:)/', $line))
	{
		RubyLogLine::parse($line);
		return RubyLogLine::display();
	}
	LogLine::parse($line);
	return LogLine::display();
}


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
			
			/* General log line field coloring. */
			div.log_line span.date
			{
				color: #888888;
			}
			div.log_line span.host
			{
				color: #888888;
			}
			div.log_line span.pid
			{
				color: #ffffff;
			}
			div.log_line span.program
			{
				color: #888888;
			}
			
			/* Error level log line coloring. */
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
		</style>
		<script type="text/javascript">
			function highlight_named_anchor()
			{
				if (window.location.hash.match(/^#\d+$/))
				{
					div = document.getElementById(window.location.hash.substr(1));
					if (div)
					{
						div.style.fontWeight = "bold";
						div.style.background = "#444444";
					}
				}
			}
			
			
			function tail()
			{
				log = document.getElementById("log");
				if (log)
				{
					log = log.value;
				}
				if (log)
				{
					window.location = "?log=" + log + "&timestamp=" + (new Date()).getTime() + "#tail";
				}
				else
				{
					alert('Invalid log selected, could not execute tail operation.')
				}
			}
			
			
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
	<body onload="highlight_named_anchor()">
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
						<input type='button' value='Tail' onclick='tail()' />
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
			<input type='button' value='Documentation' onclick='toggle_documentation()' />
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

						$line_display_count++;
						echo "<div id='" . $line_start_position . "' class='log_line'>";
						echo "[<a href='?log=" . $_GET['log'] . "&offset=" . max(($line_start_position - 8192), 0) . "&length=12288#" . $line_start_position . "' name='" . $line_start_position . "'>" . $line_start_position .  "</a>] ";
						echo format_line($current_line);
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
