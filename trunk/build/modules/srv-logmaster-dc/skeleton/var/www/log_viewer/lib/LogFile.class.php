<?php
include_once(dirname(__FILE__) . '/Log.class.php');

class LogFile
{
	protected $handle = null;
	public $path = null;
	protected $statistics = null;


	public function __construct()
	{
		$this->handle = null;
		$this->path = null;
		$this->statistics = array();
	}


	public function __destruct()
	{
		$this->close();
	}


	public function close()
	{
		if ((! is_null($this->handle)) && ($this->handle !== false))
		{
			gzclose($this->handle);
		}
		$this->__construct();
	}
	
	
	public function gets()
	{
		if (is_null($this->handle) || gzeof($this->handle))
		{
			return false;
		}

		$offset = $this->tell();
		$data = gzgets($this->handle);
		if ($data === false)
		{
			return false;
		}
		$data = Log::factory($data);
		$data->line_offset = $offset;
		return $data;
	}


	public function open($path)
	{
		$this->close();
		$this->path = $path;
		$this->statistics = @stat($path);
		$this->handle = @gzopen($path, 'rb');

		if ((! $this->statistics) || (! $this->handle))
		{
			$this->close();
			return false;
		}
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
