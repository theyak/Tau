<?php
/**
 * File driver for TAU Cache module
 *
 * @Author          theyak
 * @Copyright       2011
 * @Project Page    None!
 * @Dependencies    TauError
 * @Documentation   None!
 *
 */

if (!defined('TAU'))
{
	exit;
}

class TauCacheFile
{
	/**
	 * Reference to cache container
	 */
	private $cache;

	/**
	 * Path of cache data files
	 */
	private $path;
	
	/**
	 * Note to store with data
	 */
	private $note;

	function __construct($cache)
	{
		$this->cache = $cache;
		$this->setPath(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'cachedata');
	}
	
	function connect($args)
	{
		if (is_array($args) && isset($args[0])) {
			$this->setPath($args[0]);
		}
	}
	
	function setPath($path)
	{
		if (!in_array(substr($path, -1), array('/', '\\'))) {
			$path .= DIRECTORY_SEPARATOR;
		}
		
		$this->path = $path;		
	}
	

	function set($key, $value, $expires = 3600)
	{
		$dirname = dirname($key);
		if ($dirname != '.' && !empty($dirname))
		{
			$path = $this->path . $dirname . DIRECTORY_SEPARATOR;
			if (!is_dir($path)) {
				mkdir($path, 0744, true);
			}
		}

		$file = $this->path . $key . '.php';
		$data = serialize($value);
		$lines = array(
			"<?php die('This is an automatically generated file from TauCache. Do not edit'); ?>",
			'1.0',
			time() + $expires,
			$this->note,
			strlen($data),
			$data,
		);
		file_put_contents($file, implode("\n", $lines));
		$this->note = '';
	}
	
	function get($key)
	{
		if (is_file($this->path . $key . '.php')) 
		{
			$f = fopen($this->path . $key . '.php', 'rt');
			if ($f)
			{
				// Read header line
				$header = fgets($f);
				
				$version = trim(fgets($f));
				
				$expires = intval(fgets($f));

				$data = false;
				if ($expires >= time())
				{
					$note = trim(fgets($f));
					$length = intval(fgets($f));
					$data = trim(fgets($f));
					if (strlen($data) == $length) 
					{
						$data = unserialize($data);
						fclose($f);
						return $data;
					}
				}
				
				fclose($f);
			}
		}
		return false;
	}
	
	function exists($key)
	{
		if (is_file($this->path . $key . '.php')) 
		{
			$f = fopen($this->path . $key . '.php', 'rt');
			if ($f)
			{
				// Read header line
				$header = fgets($f);
				
				$version = trim(fgets($f));
				
				$expires = intval(fgets($f));

				fclose($f);
				
				if ($expires >= time())
				{
					return true;
				}
			}
		}
		return false;
	}
	
	function remove($key)
	{
		if (is_file($this->path . $key . '.php')) 
		{
			unlink($this->path . $key . '.php');
		}
	}

	function setNote($note) {
		$this->note = str_replace(array("\r", "\n"), '', $note);
	}
}
