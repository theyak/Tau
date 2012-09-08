<?php
/**
 * Database Module For TAU
 *
 * @Author          levans
 * @Copyright       2011
 * @Project Page    None!
 * @Dependencies    TauError
 * @Documentation   None!
 */

if (!defined('TAU'))
{
	exit;
}

class TauDatabase
{
	/**
	 * Name of database
	 */
	private $dbname;

	/**
	 * Referecnce to database engine driver
	 */
	private $driver;

	/**
	 * List of queries
	 */
	public $queries = array();

	/**
	 * Reference to cache to use
	 */
	public $cache = null;

	/**
	 * Handle for last queried result set.
	 */
	public $lastResultSet = null;



	public static function init($engine, $user, $pass, $database, $server = '127.0.0.1', $port = 3306)
	{

	}


	/**
	 *
	 * @param type $engine
	 * @param type $server
	 * @param type $dbport
	 * @param type $dbuser
	 * @param type $dbpass
	 */

	function __construct($engine, $dbuser, $dbpass, $dbname, $server = '127.0.0.1', $dbport = 3306)
	{
		// Check for valid engine
		$engines = array('mysqli', 'mysql', 'sqlite');
		$engine = strtolower($engine);
		if (!in_array($engine, $engines))
		{
			TauError::fatal('Invalid database engine. Supported engines are ' . implode(', ', $engines));
		}

		// Load driver for engine if needed
		$className = 'TauDb' . ucfirst($engine);
		$file = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'taudb' . DIRECTORY_SEPARATOR . $engine . '.php';

		if (!class_exists($className))
		{
			if (is_file($file))
			{
				require $file;
			}
			else
			{
				TauError::fatal('Invalid database engine. Supported engines are ' . implode(', ', $engines));
			}
		}

		$this->dbname = $dbname;

		// Instantiate driver
		$this->driver = new $className($this, $dbuser, $dbpass, $dbname, $server, $dbport);
	}



	function close()
	{
		$this->driver->close();
	}



	public function stringify($string)
	{
		return $this->driver->stringify($string);
	}



	public function escape($value)
	{
		if (is_null($value))                       return $this->driver->nullValue();
		if ($value === 'NOW()')                    return $this->driver->now();
		if ($value instanceof TauSqlExpression)    return $value->get();
		if (is_string($value))                     return $this->driver->stringify($value);
		if (is_float($value))					   return $value;
		if (is_integer($value) || is_bool($value)) return intval($value);
		return $this->driver->emptyField();
	}



	public function select($sql, $ttl = 0, $fields = null)
	{
		if (is_array($fields)) {
			$sql = $this->prepare($sql, $fields);
		}
		return $this->driver->select($sql, $ttl);
	}



	public function query($sql, $ttl = 0, $fields = null)
	{
		if (is_array($fields)) {
			$sql = $this->prepare($sql, $fields);
		}

		if (strtolower(substr(trim($sql), 0, 6)) == 'select') {
			return $this->select($sql, $ttl);
		}
		$this->driver->query($sql, $ttl);
	}



	public function fetch($resultSet = null)
	{
		if (is_null($resultSet)) {
			$resultSet = $this->lastResultSet;
		}

		// Check if this is a cached object
		if (is_int($resultSet)) {
			return $this->db->cache->queryFetch($resultSet);
		}

		return $this->driver->fetch($resultSet);
	}

	public function fetchOne($sql, $arg1 = null, $arg2 = null)
	{
		$ttl = 0; $fields = null;
		if (is_int($arg1))     $ttl = $arg1;
		if (is_int($arg2))     $ttl = $arg2;
		if (is_array($arg1))   $fields = $arg1;
		if (is_array($arg2))   $fields = $arg2;

		if (is_string($sql)) {
			if (stripos('limit', substr($sql, -12)) === false) {
				$resultSet = $this->driver->select($sql . ' LIMIT 1');
			} else {
				$resultSet = $this->driver->select($sql);
			}
		} else {
			$resultSet = $sql;
		}

		$row = $this->driver->fetch($resultSet);
		$this->driver->freeResult($resultSet);
		return $row;
	}

	public function fetchAll($sql, $arg1 = null, $arg2 = null)
	{
		$ttl = 0; $fields = null;
		if (is_int($arg1))     $ttl = $arg1;
		if (is_int($arg2))     $ttl = $arg2;
		if (is_array($arg1))   $fields = $arg1;
		if (is_array($arg2))   $fields = $arg2;

		if (is_string($sql)) {
			$sql = $this->prepare($sql, $fields);
			$resultSet = $this->driver->select($sql, $ttl);
		} else {
			$resultSet = $sql;
		}

		// Check if this is a cached object
		if (is_int($resultSet)) {
			$rows = $this->cache->getResults($resultSet);
			$this->cache->freeResult($resultSet);
		} else {
			$rows = $this->driver->fetchAll($resultSet);
			$this->driver->freeResult($resultSet);
		}
		return $rows;
	}

	public function fetchColumn($sql, $arg1 = null, $arg2 = null)
	{
		$ttl = 0; $fields = null;
		if (is_int($arg1))     $ttl = $arg1;
		if (is_int($arg2))     $ttl = $arg2;
		if (is_array($arg1))   $fields = $arg1;
		if (is_array($arg2))   $fields = $arg2;

		if (is_string($sql)) {
			$sql = $this->prepare($sql, $fields);
			$resultSet = $this->driver->select($sql, $ttl);
		} else {
			$resultSet = $sql;
		}

		$results = array();
		while ($row = $this->driver->fetch($result))
		{
			$results[] = reset($row);
		}
		$this->driver->freeResult($resultSet);

		return $results;
	}

	public function fetchValue($sql, $arg1 = null, $arg2 = null)
	{
		$row = $this->fetchOne($sql, $arg1, $arg2);
		if ($row) {
			return reset($row);
		}
		return false;
	}

	public function fetchPairs($sql, $arg1 = null, $arg2 = null)
	{
		$ttl = 0; $fields = null;
		if (is_int($arg1))     $ttl = $arg1;
		if (is_int($arg2))     $ttl = $arg2;
		if (is_array($arg1))   $fields = $arg1;
		if (is_array($arg2))   $fields = $arg2;

		if (is_string($sql)) {
			$sql = $this->prepare($sql, $fields);
			$resultSet = $this->driver->select($sql, $ttl);
		} else {
			$resultSet = $sql;
		}

		$results = array();
		while ($row = $this->driver->fetch($resultSet))
		{
			$key = reset($row);
			$results[$key] = next($row);
		}
		$this->driver->freeResult($resultSet);

		return $results;
	}

	public function insert($table, $values)
	{
		$this->driver->insert($table, $values);
	}

	public function update($table, $values, $where, $fields = null)
	{
		if (is_string($where)) {
			$where = $this->prepare($where, $fields);
		}
		$this->driver->update($table, $values, $where);
	}

	public function insertId()
	{
		return $this->driver->insertId();
	}

	public function affectedRows()
	{
		return $this->driver->affectedRows();
	}

	public function freeResult($resultSet = null)
	{
		if (is_null($resultSet)) {
			$resultSet = $this->lastResultSet;
		}

		// Check if this is a cached object
		if (is_int($resultSet)) {
			return $this->cache->freeResult($resultSet);
		}

		$this->driver->freeResult($resultSet);
	}

	public function where($where)
	{
		if (is_string($where)) {
			return ' WHERE ' . $where;
		}

		$string = array();
		if (is_array($where)) {
			foreach ($where AS $key => $value) {
				$string[] = $this->driver->fieldName($key) . ' = ' . $this->escape($value);
			}
		}
		return ' WHERE ' . implode(' AND ' , $string);
	}

	public function inSet($field, $set, $negate = false)
	{
		$sql = $this->driver->fieldName($field) . ' ';
		$sql .= ($negate ? 'NOT IN (' : 'IN (');
		$sql .= implode(', ', array_map(array($this, 'escape'), $set)) . ')';

		return $sql;
	}

	public function setCache($cache)
	{
		$this->cache = $cache;
	}

	private function prepare($sql, $fields)
	{
		if (!is_array($fields)) {
			return $sql;
		}

		foreach ($fields AS $key => $value) {
			$sql = str_replace(':' . $key, $this->escape($value), $sql);
		}

		return $sql;
	}

	public function isTable($table, $dbname = null)
	{
		if (is_null($dbname)) {
			$dbname = $this->dbname;
		}

		return $this->driver->isTable($table, $dbname);
	}

	public function isField($field, $table, $dbname = null)
	{
		if (is_null($dbname)) {
			$dbname = $this->dbname;
		}

		return $this->driver->isField($field, $table, $dbname);
	}
}

// MySQL: http://dev.mysql.com/doc/refman/5.1/en/create-table.html
if (!class_exists('TauDbColumn'))
{
	class TauDbColumn
	{
		public $name;
		public $type;

		// Column settings
		public $null = false;
		public $default = null;
		public $autoIncrement = false;
		public $uniqueKey = false;
		public $primaryKey = false;
		public $comment = null;
		public $columnFormat = null;
		public $storage = null;

		// Datatype settings
		public $length = 0;
		public $unsigned = false;  // Used with numeric types
		public $zerofill = false;  // Used with numeric types
		public $decimals = 0;  // NUMERIC, DECIMAL, FLOAT, DOUBLE, REAL
		public $charsetName = '';
		public $collationName = '';
		public $binary = false;    // TEXT types
		public $values = array();  // Used with ENUM and SET

		static public $types = array(
			'BIT',
			'TINYINT',
			'SMALLINT',
			'MEDIUMINT',
			'INT',
			'INTEGER',
			'BIGINT',
			'REAL',
			'DOUBLE',
			'FLOAT',
			'DECIMAL',
			'NUMERIC',
			'DATE',
			'TIME',
			'TIMESTAMP',
			'DATETIME',
			'YEAR',
			'CHAR',
			'VARCHAR',
			'BINARY',
			'VARBINARY',
			'TINYBLOB',
			'BLOB',
			'MEDIUMBLOB',
			'LONGBLOB',
			'TINYTEXT',
			'TEXT',
			'MEDIUMTEXT',
			'LONGTEXT',
			'ENUM',
			'SET',
		);

		public function setType($type)
		{
			if (in_array(strtoupper($type), self::$types)) {
				$this->type = strtoupper($type);
			}
		}

		public function setColumnFormat($format)
		{
			if (in_array(strtoupper($format), array('FIXED', 'DYNAMIC', 'DEFAULT'))) {
				$this->columnFormat = strtoupper($format);
			}
		}

		public function setStorage($storage)
		{
			if (in_array(strtoupper($storage, array('DISK', 'MEMORY', 'DEFAULT')))) {
				$this->storage = strtoupper($storage);
			}
		}
	}
}

if (!class_exists('TauSqlExpression'))
{
	class TauSqlExpression
	{
		private $expression;

		function __construct($expression = null) {
			if (!is_null($expression)) {
				$this->set($expression);
			}
		}

		public function set($expression) {
			$this->expression = $expression;
		}

		public function get() {
			return $this->expression;
		}

		public function toString() {
			return $this->expression;
		}
	}
}
