<?php
class LogFile
{
	protected $handle = null;
	protected $path = null;
	protected $statistics = array();
	protected $timestamp_lower_bound = null;
	protected $timestamp_upper_bound = null;


	public function __construct()
	{
		$this->handle = null;
		$this->path = null;
		$this->statistics = array();
	}


	public function __destruct()
	{
		if (! is_null($this->handle))
		{
			gzclose($this->handle);
			$this->handle = null;
		}
		$this->path = null;
		$this->statistics = array();
	}


	public function gets()
	{
		if (is_null($this->handle) || gzeof($this->handle))
		{
			return false;
		}

		$offset = gztell($this->handle);
		$data = gzgets($this->handle);
		if ($data === false)
		{
			return false;
		}
		$data = Log::factory($data);
		$data->set_offset($offset);
		$timestamp = $data->log_timestamp();
		if ($timestamp > 0)
		{
			if (is_null($this->timestamp_upper_bound) || ($timestamp > $this->timestamp_upper_bound))
			{
				$this->timestamp_upper_bound = $timestamp;
			}
		}
		return $data;
	}


	public function open($path)
	{
		$this->path = $path;
		$this->statistics = stat($path);
		$this->handle = gzopen($path, 'rb');

		if ((! $this->statistics) || (! $this->handle))
		{
			$this->handle = null;
			$this->path = null;
			$this->statistics = array();
			return false;
		}

		/**
		 * Attempt to read and parse the first line of the log file
		 * so that we can hopefully retrieve a timestamp that will
		 * represent the first date/time that is available in this
		 * log file.
		 */
		if ($data = gzgets($this->handle))
		{
			$data = Log::factory($data);
			$timestamp = $data->log_timestamp();
			if ($timestamp > 0)
			{
				$this->timestamp_lower_bound = $timestamp;
			}
		}

		/**
		 * If we are not a GZIP-compressed file (i.e. if we do not
		 * end in .gz), then attempt to read and parse the last line
		 * of the log file so that we can hopefully retrieve a timestamp
		 * that will represent the last date/time that is available in this
		 * log file.
		 */
		if (strcasecmp(substr($this->path, -3), ".gz"))
		{
			for ($i = 2; $i < $this->statistics['size']; $i++)
			{
				gzseek($this->handle, $this->statistics['size'] - $i);
				$c = gzgetc($this->handle);
				if ($c == "\n")
				{
					$data = gzgets($this->handle);
					if ($data)
					{
						$data = Log::factory($data);
						$timestamp = $data->log_timestamp();
						if ($timestamp > 0)
						{
							$this->timestamp_upper_bound = $timestamp;
						}
					}
					break;
				}
			}
		}

		$this->seek(0);

		return true;
	}


	public function seek($offset)
	{
		if (is_null($this->handle))
		{
			return -1;
		}
		if (gzseek($this->handle, $offset))
		{
			return -1;
		}
		if ($offset > 0)
		{
			/**
			 * If our seek has put us mid-way through the file, we ignore the first line if
			 * it is a partial line.  It is a partial line unless the character immediately before
			 * our new offset was a newline.
			 *
			 * Thus we ignore the line if the character at $offset - 1 != "\n".
			 ***/
			if (gzseek($this->handle, $offset - 1))
			{
				return -1;
			}
			$byte = gzread($this->handle, 1);
			if (strlen($byte) != 1)
			{
				return -1;
			}
			if (strncmp($byte, "\n", 1))
			{
				if (gzgets($this->handle) === false)
				{
					return -1;
				}
			}
		}
		return 0;
	}


	public function tell()
	{
		if (is_null($this->handle))
		{
			return false;
		}
		return gztell($this->handle);
	}
}
