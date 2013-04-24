<?php
/**
 * Cache Module For TAU
 *
 * @Author          theyak
 * @Copyright       2011
 * @Project Page    None!
 * @Dependencies    TauError
 * @Documentation   None!
 */

if (!defined('TAU'))
{
	exit;
}

class TauCache
{
	/**
	 * Referecnce to cache engine driver
	 */
	public $driver;
	

	/**
	 * Loaded cached queries
	 */
	private $queries = array();

	/**
	 * Current index to fetch from query
	 */
	private $queryIndex = array();

	/**
	 * Query number to be used as index for $queries
	 */
	private $queryNumber = 0;

	
	function __construct($engine)
	{
		// Check for valid engine
		$engines = array('file');
		$engine = strtolower($engine);
		if (!in_array($engine, $engines))
		{
			TauError::fatal('Invalid cache engine. Supported engines are ' . implode(', ', $engines));
		}

		// Load driver for engine if needed
		$className = 'TauCache' . ucfirst($engine);
		$file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'taucache' . DIRECTORY_SEPARATOR . $engine . '.php';

		if (!class_exists($className))
		{
			require $file;
		}

		// Instantiate driver
		$this->driver = new $className($this);
	}
	
	function connect()
	{
		$this->driver->connect(func_get_args());
	}
	
	function set($key, $value, $expires = 3600)
	{
		$this->driver->set($key, $value, $expires);
	}
	
	function add($key, $value, $expires = 3600)
	{
		if (!$this->driver->exists($key)) {
			$this->driver->set($key, $value, $expires);
		}
	}
	
	function get($key)
	{
		return $this->driver->get($key);
	}
	
	function exists($key)
	{
		return $this->driver->exists($key);
	}
	
	function remove($key)
	{
		return $this->driver->remove($key);
	}
	
	function incr($key)
	{
		$value = $this->driver->get($key);
		if (is_int($value)) {
			$value++;
			$this->driver->set($key, $value);
			return $value;
		}
		return false;
	}
	
	function decr($key)
	{
		$value = $this->driver->get($key);
		if (is_int($value)) {
			$value--;
			$this->driver->set($key, $value);
			return $value;
		}
		return false;
	}




	function queryFetch($queryNumber) 
	{
		if (!isset($this->queries[$queryNumber]) || !isset($this->queryIndex[$queryNumber])) {
			return false;
		}
		
		$index = $this->queryIndex[$queryNumber];
		if (!isset($this->queries[$queryNumber][$index])) {
			return false;
		}
		
		return $this->queries[$queryNumber][$this->queryIndex[$queryNumber]++];
	}



	function queryLoad($query)
	{
		$query = preg_replace('/[\n\r\s\t]+/', ' ', $query);
		$key = 'sql/' . md5($query);
		$rows = $this->driver->get($key);
		if ($rows === false) {
			return false;
		}
		$this->queries[$this->queryNumber] = $rows;
		$this->queryIndex[$this->queryNumber] = 0;
		return $this->queryNumber++;
	}



	function querySave($db, $resultSet, $query, $ttl)
	{
		$this->driver->setNote($query);
		while ($row = $db->fetch($resultSet))
		{
			$this->queries[$this->queryNumber][] = $row;
		}
		
		$query = preg_replace('/[\n\r\s\t]+/', ' ', $query);
		$key = 'sql/' . md5($query);
		$this->driver->set($key, $this->queries[$this->queryNumber], $ttl);
		$this->queryIndex[$this->queryNumber] = 0;
		
		return $this->queryNumber++;
	}



	function queryRemove($query)
	{
		$query = preg_replace('/[\n\r\s\t]+/', ' ', $query);
		$key = 'sql/' . md5($query);
		$this->driver->remove($key);
	}



	function freeResult($resultSet) {
		unset($this->queries[$resultSet]);
		unset($this->queryIndex[$resultSet]);
	}
	
	function getResults($resultSet) {
		if (isset($this->queries[$resultSet])) {
			return $this->queries[$resultSet];
		}
		return false;
	}

	function getResultsWithId($resultSet, $id = '')
	{
		if (isset($this->queries[$resultSet]))
		{
			$result = array();
			foreach ($this->queries[$resultSet] AS $row)
			{
				if ($id && isset($row[$id]))
				{
					$result[$row[$id]] = $row;
				}
				else
				{
					$result[reset($row)] = $row;
				}
			}
		}
		return false;
	}

}
