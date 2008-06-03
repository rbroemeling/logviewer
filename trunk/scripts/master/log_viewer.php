<?php
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

$log_sources = array
(
	'php-beta' => '/var/log/php-beta.log',
	'php-live' => '/var/log/php-live.log',
	'php-stage' => '/var/log/php-stage.log',
	'ruby-beta' => '/var/log/ruby-beta.log',
	'ruby-live' => '/var/log/ruby-live.log',
	'ruby-stage' => '/var/log/ruby-stage.log'
);

$tail_sizes = array();
for ($i = 1; $i <= 11; $i++)
{
	$tail_sizes[pow(2, $i)] = pow(2, $i) * 1024;
}
if (! ($_GET['tail_size'] && $tail_sizes[$_GET['tail_size']]))
{
	$_GET['tail_size'] = pow(2, 3);
}

$log_excerpt = '';
$tail_size = $tail_sizes[$_GET['tail_size']];
if ($_GET['log'] && $log_sources[$_GET['log']])
{
	if (file_exists($log_sources[$_GET['log']]))
	{
		$file_size = filesize($log_sources[$_GET['log']]);
		$tail_size = min($tail_size, $file_size);
		if ($log_handle = fopen($log_sources[$_GET['log']], 'r'))
		{
			if ($tail_size)
			{
				fseek($log_handle, $file_size - $tail_size);
				$log_excerpt = fread($log_handle, $tail_size);
				$log_excerpt = preg_split("/\r?\n/", $log_excerpt);
				if ($tail_size != $file_size)
				{
					array_shift($log_excerpt);
				}

				foreach (array_keys($log_excerpt) as $i)
				{
					if ($_GET['filter'] && (stristr($log_excerpt[$i], $_GET['filter']) == FALSE))
					{
						unset($log_excerpt[$i]);
						continue;
					}
					$log_excerpt[$i] = str_split($log_excerpt[$i], 5);
					foreach (array_keys($log_excerpt[$i]) as $j)
					{
						$log_excerpt[$i][$j] = htmlspecialchars($log_excerpt[$i][$j], ENT_QUOTES);
					}
					$log_excerpt[$i] = implode('<wbr>', $log_excerpt[$i]);
					$log_excerpt[$i] = "<div id='line" . $i . "' class='log_line'>" . $log_excerpt[$i] . "</div>";
				}
				$log_excerpt = implode("\n", array_reverse($log_excerpt));
			}
			else
			{
				$log_excerpt = '<div class="error">' . $log_sources[$_GET['log']] . ' is empty.</div>';
			}
			fclose($log_handle);
		}
		else
		{
			$log_excerpt = '<div class="error">' . $log_sources[$_GET['log']] . ' could not be read.</div>';
		}
	}
	else
	{
		$log_excerpt = '<div class="error">' . $log_sources[$_GET['log']] . ' does not exist.</div>';
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
			}

			div.error
			{
				color: #660000;
				font-weight: bold;
			}
			div.log_line
			{
				margin-bottom: 15px;
				text-align: justify;
			}
		</style>
	</head>
	<body>
		<form>
			Show last
			<select name='tail_size'>
				<?php
					foreach (array_keys($tail_sizes) as $tail_size)
					{
						if ($_GET['tail_size'] == $tail_size)
						{
							echo '<option selected>';
						}
						else
						{
							echo '<option>';
						}
						echo "$tail_size</option>\n";
					}
				?>
			</select>
			kilobytes of
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
			</select>,
			filtered for the text <input type='text' name='filter' size='20' maxlength='100' value='<?php echo htmlspecialchars($_GET['filter'], ENT_QUOTES); ?>' />.
			<input type='submit' value='Refresh' />
		</form>
		<div id="log_excerpt"><?php echo $log_excerpt; ?></div>
	</body>
</html>
