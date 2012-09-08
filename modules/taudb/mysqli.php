<?php
/**
 * MySQL Improved driver for TAU Database module
 *
 * @Author          levans
 * @Copyright       2011
 * @Project Page    None!
 * @Dependencies    TauError
 *                  TauDatabase
 *                  TauCache (Optional)
 * @Documentation   None!
 *
 */

if (!defined('TAU'))
{
	exit;
}

class TauDbMysqli
{
	/**
	 * Reference to database container
	 */
	private $db;
	
	/**
	 * The character to surround field names with
	 */
	public $fieldTick = '`';
	
	/**
	 * The character to surround string values with
	 */
	public $stringTick = "'";
	

	/**
	 * Connection link resource to database
	 */
	private $dbLink;
	
	/**
	 *
	 * @param type $db
	 * @param type $dbuser
	 * @param type $dbpass
	 * @param type $server
	 * @param type $dbport 
	 */

	function __construct($db, $dbuser, $dbpass, $dbname, $server = '127.0.0.1', $dbport = 3306)
	{
		$this->db = $db;

		$this->dbLink = @mysqli_connect($server, $dbuser, $dbpass, $dbname, $dbport);
		if ($this->dbLink)
		{
			return $this->dbLink;
		}
		
		TauError::fatal("Unable to connect to database");
	}
	
	public function select($sql)
	{
		$query = array();
		$query['sql'] = $sql;
		$query['start'] = microtime(true);
		
		if ($this->db->cache && $ttl > 0)
		{
			$query['cached'] = true;
			$this->db->lastResultSet = $this->db->cache->queryLoad($sql);
			if ($this->db->lastResultSet === false)
			{
				$query['cached'] = false;
				$resultSet = mysqli_query($sql, $this->dbLink);	
				if ($resultSet) {
					$this->db->lastResultSet = $this->db->cache->querySave($this, $resultSet, $sql, $ttl);
					$this->freeResult($resultSet);
				}
			}
		}
		else
		{
			$this->db->lastResultSet = mysqli_query($sql, $this->dbLink);		
			$query['cached'] = false;
		}
		
		$query['end'] = microtime(true);
		$query['time'] = $query['end'] - $query['start'];
		
		$this->db->queries[] = $query;
		
		return $this->db->lastResultSet;
	}
	
	public function query($sql)
	{
		$query = array();
		$query['sql'] = $sql;
		$query['start'] = microtime(true);

		mysqli_query($this->dbLink, $sql);
		
		$query['end'] = microtime(true);
		$query['time'] = $query['end'] - $query['start'];
		$query['cached'] = false;

		$this->db->queries[] = $query;
	}
	
	public function fetch($resultSet = null) {
		if ($resultSet == null) {
			$resultSet = $this->db->lastResultSet;
		}
		
		return @mysqli_fetch_assoc($resultSet);
	}
	
	public function fetchAll($resultSet = null) {
		if ($resultSet == null) {
			$resultSet = $this->db->lastResultSet;
		}

		if (function_exists('mysqli_fetch_all')) {
			return @mysqli_fetch_all($resultSet);
		}
		
		$results = array();
		while ($row = @mysqli_fetch_assoc($resultSet)) {
			$results[] = $row;
		}
		return $results;
	}
	
	public function freeResult($resultSet = null) {
		if ($resultSet == null) {
			if ($this->db->lastResultSet != null) {
				mysqli_free_result($this->lastResultSet);
				$this->db->lastResultSet = null;
			}
		} else {
			mysqli_free_result($resultSet);
		}
	} 

	public function insert($table, $array)
	{
		if (!is_array($array) || sizeof($array) < 1) {
			return;
		}
		
		$fieldNames = $values = array();
		foreach ($array AS $fieldName => $value) {
			$fieldNames[] = $this->fieldName($fieldName);
			$values[] = $this->db->escape($value);
		}
		$sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $fieldNames) . ') ';
		$sql .= 'VALUES (' . implode(', ', $values) . ')';
		$this->query($sql);
	}
	
	public function update($table, $array, $where) 
	{
		if (!is_array($array) || sizeof($array) < 1) {
			return;
		}
		
		$values = array();
		foreach ($array AS $fieldName => $value) {
			$values[] = $this->fieldName($fieldName) . ' = ' . $this->db->escape($value);
		}
		
		$sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $values) . $this->db->where($where);
		$this->query($sql);
	}

	public function fieldName($field)
	{
		return $this->fieldTick . $field . $this->fieldTick;
	}	
	
	public function now()
	{
		return 'NOW()';
	}
	
	public function stringify($value)
	{
		return $this->stringTick . mysqli_real_escape_string((string)$value) . $this->stringTick;
	}
	
	public function emptyFiled()
	{
		return $this->stringTick . $this->stringTick;
	}
}
