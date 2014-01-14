<?php
/**
 * Cache Module For TAU
 *
 * @Author          theyak
 * @Copyright       2011
 * @Project Page    https://github.com/theyak/Tau
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
	private $queryNumber = 1;


	function __construct($engine, $opts = array())
	{
		// Check for valid engine
		$engines = array( 'file', 'apc', 'null' );
		$engine = strtolower($engine);
		if (!in_array($engine, $engines))
		{
			throw new Exception('Invalid cache engine. Supported engines are ' . implode(', ', $engines));
		}

		// Load driver for engine if needed
		$className = 'TauCache' . ucfirst($engine);
		$file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'taucache' . DIRECTORY_SEPARATOR . $engine . '.php';

		if (!class_exists($className))
		{
			require $file;
		}

		// Instantiate driver
		$this->driver = new $className($this, $opts);
	}



	public function set($key, $value, $ttl = 3600)
	{
		$this->driver->set($key, $value, $expires);
	}



	public function add($key, $value, $ttl = 3600)
	{
		if (!$this->driver->exists($key))
		{
			$this->driver->set($key, $value, $expires);
		}
	}



	public function get($key)
	{
		return $this->driver->get($key);
	}



	public function exists($key)
	{
		return $this->driver->exists($key);
	}



	public function remove($key)
	{
		return $this->driver->remove($key);
	}



	public function incr( $key, $step = 1 )
	{
		return $this->driver->incr( $key );
	}



	public function decr( $key, $step = 1 )
	{
		return $this->driver->incr( $key, -$step );
	}




	public function queryFetch($queryNumber)
	{
		if (!isset($this->queries[$queryNumber]) || !isset($this->queryIndex[$queryNumber]))
		{
			return false;
		}

		$index = $this->queryIndex[$queryNumber];
		if (!isset($this->queries[$queryNumber][$index]))
		{
			return false;
		}

		return $this->queries[$queryNumber][$this->queryIndex[$queryNumber]++];
	}



	public function queryLoad($query)
	{
		$query = preg_replace('/[\n\r\s\t]+/', ' ', $query);
		$key = 'sql/' . md5($query);
		$rows = $this->driver->get($key);
		if ($rows === false)
		{
			return false;
		}
		$this->queries[$this->queryNumber] = $rows;
		$this->queryIndex[$this->queryNumber] = 0;
		return $this->queryNumber++;
	}



	public function querySave($db, $resultSet, $query, $ttl)
	{
		$this->queries[$this->queryNumber] = array();
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



	public function queryRemove($query)
	{
		$query = preg_replace('/[\n\r\s\t]+/', ' ', $query);
		$key = 'sql/' . md5($query);
		$this->driver->remove($key);
	}



	public function freeResult($resultSet) {
		unset($this->queries[$resultSet]);
		unset($this->queryIndex[$resultSet]);
	}



	public function getResults($resultSet) {
		if (isset($this->queries[$resultSet]))
		{
			return $this->queries[$resultSet];
		}
		return false;
	}



	public function getResultsWithId($resultSet, $id = '') {
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
