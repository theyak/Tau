<?php
/**
 * Database Module For TAU
 *
 * This is a replacement for TauDatabase. It's not quite as functional - yet
 *
 * @Author          theyak
 * @Copyright       2012
 * @Project Page    None!
 * @Dependencies    TauError, TauFS
 * @Documentation   None!
 *
 * @example
 *
 * $server = new TauDbServer('databasetouse', 'username', 'password');
 * $server->host = '127.0.0.1'; // Optional, defaults to 127.0.0.1
 * $server->port = 3306; // Optional, defaults the DB's default port
 * $db = TauDB::init('mysql', $server);
 *
 * $db->insert('table', array('field1' => 'value1', 'field2' => 'value2');
 * $db->update('table', array('field1' => 'newValue'), array('field1' => 'value1'));
 * Tau::dump($db->fetchAll('SELECT * FROM table'));
 *
 * changelog:
 *   1.0.0  Sep  8, 20122  Created
 *
 * ::init($engine, TauDbServer $server)
 *   Initialize a database connection
 *
 * setCache(TauCache $cache)
 *   Set the cache to use for SELECT statements
 *
 * nullValue()
 *   Return SQL for NULL value
 *
 * now()
 *   Return SQL for current time
 *
 * emptyValue()
 *   Return SQL for an empty string or value
 *
 * stringify($string)
 *   Encode a string into an SQL string, including proper escaping and
 *   quotations.
 *
 * fieldName($fieldName)
 *   Return a field name in SQL format

 * insertSql($table, $insert)
 *   Create SQL for an INSERT statement
 *
 * updateSql($table, $update, $where)
 *   Create SQL for an UPDATE statement
 *
 * inSetSql($field, $set, $negate)
 *   Create SQL for IN SET
 *
 * escape($value)
 *   Convert PHP datatypes to those appropriate for SQL statements
 *
 * select($sql, $ttl = 0)
 *   Perform a SELECT query on the database, including cache if supplied
 *
 * query($sql, $ttl = 0)
 *   Perform an SQL query (anything other than SELECT) on the database
 *
 * fetch($resultSet = null)
 *   Fetch a row from a result set
 *
 * fetchOne($sql, $ttl = 0)
 *   Retrieve exactly one row from SQL query
 *
 * fetchAll($sql, $ttl = 0)
 *   Retrieve all rows from an SQL query
 *
 * fetchAllWithId($sql, $ttl = 0)
 *   Retrieve all rows from an SQL query indexed by an ID
 *
 * fetchPairs($sql, $ttl = 0)
 *   Fetch pairs from the database. First value in result set is used as array key
 *
 * fetchColumn($sql, $ttl = 0)
 *   Fetch a column from a database SELECT query
 *
 * fetchValue($sql, $ttl = 0)
 *   Fetch a single value from the database. Very useful for things like
 *   SELECT COUNT(*) FROM ... WHERE ...
 *
 * insert($table, $values)
 *   Insert data in to a table
 *
 * update($table, $values, $where)
 *   Update data in a table
 *
 * inSet($field, $set, $negate = false)
 *   Retrieve SQL for finding data in a set
 *
 * insertId()
 *   Get the ID from the last insert
 *
 * affectedRows()
 *   Get number of rows affected by last INSERT or UPDATE
 *
 * where($where)
 *   Create a WHERE string for SQL statement. If an array is passed in, the WHERE
 *   is constructed based on the key and value pairs of the array and ANDed together.
 *   If a string is passed in, it is just returned, possibly with WHERE prepended
 *   if needed.
 *
 * isTable($table, $dbName = null)
 *   Determine if table exists in database
 *
 * isField($field, $table, $dbName = null)
 *   Determine if field exists in table
 *
 * freeResult($resultSet)
 *   Release result set from memory
 */

if (!defined('TAU'))
{
	exit;
}

class TauDb
{
	public $server;

	/**
	 * Reference to cache in use
	 * @var TauCache
	 */
	public $cache = false;

	/**
	 * Reference to most recent result set
	 * @var resource
	 */
	private $resultSet = null;


	/**
	 * Quote to surround field names in
	 * @var string
	 */
	public $fieldQuote = '`';


	/**
	 * Flag indicating if script should terminate upon error
	 * @var bool
	 */
	public $terminateOnError = true;


	/**
	 * Flag indicating extended debug on error. You will probably
	 * want to set this during development and for admin users
	 * @var bool
	 */
	public $extendedDebug = false;


	/**
	 * Pointer to writable database. Used in master/slave setups
	 * and setup with setWriteDb().
	 * @var TauDb
	 */
	private $writeDb = null;

	/**
	 * Initializes a database connection
	 *
	 * @param string $engine
	 * @param TauDbServer $server
	 * @return TauDb $engine
	 */

	public static function init( $engine, TauDbServer $server )
	{
		$engine = 'Tau' . ucfirst( $engine );

		// Load class as needed
		if ( ! class_exists( $engine ) )
		{
			$file = dirname( __FILE__ ) . Tau::DS . 'TauDatabase' . Tau::DS . $engine . '.php';
			if ( is_file( $file ) )
			{
				include $file;
			}
			else
			{
				// If no engine, retrieve list of available engines and display error
				$engines = TauFS::dir( dirname( __FILE__ ) . Tau::DS . 'TauDatabase' );
				$engines = array_map(
					function( $name )
					{
						return str_replace( array( 'Tau', '.php' ), '', $name );
					},
					$engines
				);
				TauError::fatal( 'Invalid database engine. Supported engines are ' . implode( ', ', $engines ) );
			}
		}

		return new $engine( $server );
	}



	public function __construct($engine, $user, $pass, $database, $host = '127.0.0.1', $port = 0)
	{
		$this->server = new TauDbServer($database, $user, $pass, $host, $port);
		return self::init($engine, $this->server);
	}

	/**
	 * @abstract
	 */
	public function connect()
	{
		TauError::fatal('connect() method not defined.');
	}

	/**
	 * @abstract
	 */
	public function close()
	{
		TauError::fatal('dbClose() method not defined.');
	}

	/**
	 * @abstract
	 */
	public function numRows()
	{
		TauError::fatal('nowRows() method not defined.');
	}

	/**
	 * @abstract
	 */
	public function affectedRows()
	{
		TauError::fatal('affectedRows() method not defined.');
	}

	/**
	 * @abstract
	 */
	public function insertId()
	{
		TauError::fatal('insertId() method not defined.');
	}

	/**
	 * @abstract
	 */
	public function dbQuery($sql)
	{
		TauError::fatal('dbQuery() method not defined.');
	}

	/**
	 * @abstract
	 */
	public function dbError()
	{
		TauError::fatal('dbError() method not defined.');
	}

	/**
	 * @abstract
	 */
	protected function dbFetch($resultSet)
	{
		TauError::fatal('dbFetch() method not defined.');
	}

	/**
	 * @abstract
	 */
	protected function dbFetchAll($resultSet)
	{
		TauError::fatal('dbFetchAll() method not defined.');
	}

	/**
	 * @abstract
	 */
	public function dbFreeResult($resultSet)
	{
		TauError::fatal('dbFreeResult() method not defined.');
	}

	/**
	 * @abstract
	 */
	public function dbFieldName($fieldName)
	{
		TauError::fatal('dbFieldName() method not defined.');
	}

	/**
	 * @abstract
	 */
	public function dbStringify($string)
	{
		TauError::fatal('dbStringify() method not defined.');
	}

	/**
	 * @abstract
	 */
	public function dbInsertId()
	{
		TauError::fatal('dbInsertId() method not defined.');
	}

	/**
	 * @abstract
	 */
	public function dbAffectedRows()
	{
		TauError::fatal('dbAffectedRows() method not defined.');
	}

	/**
	 * @abstract
	 */
	public function dbIsTable($table, $dbName)
	{
		TauError::fatal('dbIsTable() method not defined.');
	}

	/**
	 * @abstract
	 */
	public function dbIsField($field, $table, $dbName)
	{
		TauError::fatal('dbIsField() method not defined.');
	}



	/**
	 * Set the cache to use for SELECT statements
	 *
	 * @param TauCache $cache
	 */
	public function setCache(TauCache $cache)
	{
		$this->cache = $cache;
	}



	/**
	 * Return SQL for NULL value
	 *
	 * @return string
	 */
	public function nullValue()
	{
		return 'NULL';
	}



	/**
	 * Return SQL for current time
	 *
	 * @return string
	 */
	public function now()
	{
		return 'NOW()';
	}



	/**
	 * Return SQL for an empty string or value
	 *
	 * @return string
	 */
	public function emptyValue()
	{
		return "''";
	}



	/**
	 * Encode a string into an SQL string, including proper escaping and
	 * quotations.
	 *
	 * @param type $string
	 * @return string
	 */
	public function stringify($string)
	{
        $this->connect();
		return $this->dbStringify($string);
	}



	/**
	 * Return a field name in SQL format
	 *
	 * @param type $fieldName
	 * @return type
	 */
	public function fieldName($fieldName)
	{
        $this->connect();
		return $this->dbFieldName($fieldName);
	}



	/**
	 * Create SQL for an INSERT statement
	 *
	 * @param string $table
	 * @param array $insert
	 * @return string
	 */
	public function insertSql($table, $insert)
	{
		if (!is_array($insert) || sizeof($insert) < 1)
		{
			return;
		}

		$fieldNames = $values = array();
		foreach ($insert AS $fieldName => $value)
		{
			$fieldNames[] = $this->fieldName($fieldName);
			$values[] = $this->escape($value);
		}
		$sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $fieldNames) . ') ';
		$sql .= 'VALUES (' . implode(', ', $values) . ')';

		return $sql;
	}



	/**
	 * Create SQL for an UPDATE statement
	 *
	 * @param string $table
	 * @param string $update
	 * @param string $where
	 * @return string
	 */
	public function updateSql($table, $update, $where)
	{
		if (!is_array($update) || sizeof($update) < 1)
		{
			return;
		}

		$values = array();
		foreach ($update AS $fieldName => $value)
		{
			$values[] = $this->fieldName($fieldName) . ' = ' . $this->escape($value);
		}

		$sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $values) . $this->where($where);

		return $sql;
	}



	/**
	 * Create SQL for IN SET
	 *
	 * @param string $field
	 * @param array $set
	 * @param bool $negate
	 * @return string
	 */
	public function inSetSql($field, $set, $negate)
	{
		$sql = $this->fieldName($field) . ' ';
		$sql .= ($negate ? 'NOT IN (' : 'IN (');
		$sql .= implode(', ', array_map(array($this, 'escape'), $set)) . ')';

		return $sql;
	}



	/**
	 * Convert PHP datatypes to those appropriate for SQL statements
	 *
	 * @param any $value
	 * @return any
	 */
	public function escape($value)
	{
		if (is_null($value))                       return $this->nullValue();
		if ($value === 'NOW()')                    return $this->now();
		if ($value instanceof TauSqlExpression)    return $value->get();
		if (is_string($value))                     return $this->dbStringify($value);
		if (is_float($value))					   return $value;
		if (is_integer($value) || is_bool($value)) return intval($value);
		return $this->emptyValue();
	}



	/**
	 * Perform a SELECT query on the database, including cache if supplied
	 *
	 * @param string $sql
	 * @param int $ttl Time, in seconds, to keep data in cache
	 * @return handle
	 */
	public function select($sql, $ttl = 0)
	{
		$query = array(
			'sql' => $sql,
			'start' => microtime(true),
		);

		$this->connect();

		if ($this->cache && $ttl > 0)
		{
			// Load from cache. If cache miss, select as regular and store in cache
			$query['cached'] = true;
			$resultSet = $this->cache->queryLoad($sql);
			if (!$this->resultSet)
			{
				$query['cached'] = false;
				$resultSet = $this->dbQuery($sql);
				if ($resultSet)
				{
					// Save query in cache
					$this->resultSet = $this->cache->querySave($this, $resultSet, $sql, $ttl);
					$this->freeResult($resultSet);
				}
			}
		}
		else
		{
			// Load from database
			$this->resultSet = $this->dbQuery($sql);
			$query['cached'] = false;
		}

		$query['end'] = microtime(true);
		$query['time'] = $query['end'] - $query['start'];
		$this->queries[] = $query;

		if ($this->terminateOnError && $this->dbError())
		{
			TauError::fatal($this->dbError(), $this->extendedDebug);
		}

		return $this->resultSet;
	}



	/**
	 * Perform an SQL query (anything other than SELECT) on the database
	 *
	 * @param string $sql
	 */
	public function query($sql, $ttl = 0)
	{
		// Cache calls MUST be select queries
		if ($ttl && strtolower(substr(trim($sql), 0, 6)) == 'select')
		{
			return $this->select($sql, $ttl);
		}

		if (!is_null($this->writeDb) && $this->writeDb != $this)
		{
			$this->writeDb->query($sql, $ttl);
			return;
		}

		$query = array(
			'sql' => $sql,
			'start' => microtime(true),
		);

		$this->connect();
		$this->dbQuery($sql);

		$query['end'] = microtime(true);
		$query['time'] = $query['end'] - $query['start'];
		$query['cached'] = false;

		$this->db->queries[] = $query;

		if ($this->terminateOnError && $this->dbError())
		{
			TauError::fatal($this->dbError(), $this->extendedDebug);
		}
	}



	/**
	 * Fetch a row from a result set
	 *
	 * @param resource $resultSet
	 * @return array|false
	 */
	public function fetch($resultSet = null)
	{
		if (is_null($resultSet))
		{
			$resultSet = $this->resultSet;
		}

		// Check if this is a cached object
		if (is_int($resultSet))
		{
			return $this->cache->queryFetch($resultSet);
		}

		return $this->dbFetch($resultSet);
	}



	/**
	 * Retrieve exactly one row from SQL query
	 *
	 * @param string $sql
	 * @param int $ttl
	 * @return array|false
	 */
	public function fetchOne($sql, $ttl = 0)
	{
		if (is_string($sql))
		{
			if (stripos('limit', substr($sql, -12)) === false)
			{
				$resultSet = $this->select($sql . ' LIMIT 1', $ttl);
			}
			else
			{
				$resultSet = $this->select($sql, $ttl);
			}
		}
		else
		{
			$resultSet = $sql;
		}

		$row = $this->fetch($resultSet);
		$this->freeResult($resultSet);
		return $row;
	}



	/**
	 * Retrieve all rows from an SQL query
	 *
	 * @param string $sql
	 * @param int $ttl
	 * @return array
	 */
	public function fetchAll($sql, $ttl = 0)
	{
		if (is_string($sql))
		{
			$resultSet = $this->select($sql, $ttl);
		}
		else
		{
			$resultSet = $sql;
		}

		if (is_int($resultSet))
		{
			$rows = $this->cache->getResults($resultSet);
		}
		else
		{
			$rows = $this->dbFetchAll($resultSet);
		}
		$this->freeResult($resultSet);

		return $rows;
	}

	/**
	 * Retrieve all rows from an SQL query indexed by ID
	 *
	 * @param string $sql
	 * @param $id Name of field to use as ID. If left blank, the first field is used.
	 * @param int $ttl
	 * @return array
	 */
	public function fetchAllWithId($sql, $id = '', $ttl = 0)
	{
		if (is_string($sql))
		{
			$resultSet = $this->select($sql, $ttl);
		}
		else
		{
			$resultSet = $sql;
		}

		if (is_int($resultSet))
		{
			$rows = $this->cache->getResultsWithId($resultSet, $id);
		}
		else
		{
			$rows = $this->dbFetchAllWithId($resultSet, $id);
		}
		$this->freeResult($resultSet);

		return $rows;
	}



	/**
	 * Fetch pairs from the database. First value in result set is used as array key.
	 *
	 * @param String $sql
	 * @param int $ttl
	 * @return array
	 */
	public function fetchPairs($sql, $ttl = 0)
	{
		if (is_string($sql))
		{
			$resultSet = $this->select($sql, $ttl);
		}
		else
		{
			$resultSet = $sql;
		}

		$results = array();
		while ($row = $this->fetch($resultSet))
		{
			$key = reset($row);
			$results[$key] = next($row);
		}
		$this->freeResult($resultSet);

		return $results;
	}



	/**
	 * Fetch a column from a database SELECT query
	 *
	 * @param String $sql
	 * @param int $ttl
	 * @return array
	 */
	public function fetchColumn($sql, $ttl = 0)
	{
		if (is_string($sql))
		{
			$resultSet = $this->select($sql, $ttl);
		}
		else
		{
			$resultSet = $sql;
		}

		$results = array();
		while ($row = $this->fetch($resultSet))
		{
			$results[] = reset($row);
		}
		$this->freeResult($resultSet);

		return $results;
	}



	/**
	 * Fetch a single value from the database. Very useful for things like
	 * SELECT COUNT(*) FROM ... WHERE ...
	 *
	 * @param string $sql
	 * @param int $ttl
	 * @return mixed
	 */
	public function fetchValue($sql, $ttl = 0)
	{
		$row = $this->fetchOne($sql, $ttl);
		if (is_array($row))
		{
			return reset($row);
		}

		return false;
	}



	/**
	 * Insert data in to a table
	 *
	 * @param string $table
	 * @param array $values
	 */
	public function insert($table, $values)
	{
		$sql = $this->insertSql($table, $values);
		$this->query($sql);
	}



	/**
	 *
	 * @param string $table
	 * @param array $values
	 * @param string $where
	 */
	public function update($table, $values, $where)
	{
		$sql = $this->updateSql($table, $values, $where);
		$this->query($sql);
	}



	/**
	 * Retrieve SQL for finding data in a set
	 *
	 * @param string $field Name of field
	 * @param array $set Data to search
	 * @param bool $negate Change to a not in set
	 * @return string
	 */
	public function inSet($field, $set, $negate = false)
	{
		return $this->inSetSql($field, $set, $negate);
	}



	/**
	 * Create a WHERE string for SQL statement. If an array is passed in, the WHERE
	 * is constructed based on the key and value pairs of the array and ANDed together.
	 * If a string is passed in, it is just returned, possibly with WHERE prepended
	 * if needed.
	 *
	 * @param string|array $where
	 * @return string
	 *
	 * @examples
	 * $db->where('where id = 1 AND class = 4);
	 * $db->where('id = 1 AND class = 4');
	 * $db->where(array('id' => 1, 'class' => 4));
	 */
	public function where($where)
	{
		if (is_string($where))
		{
			if (stripos(trim($where), 'where') !== 0)
			{
				return ' WHERE ' . $where;
			}
			return ' ' . $where;
		}

		$string = array();
		if (is_array($where))
		{
			foreach ($where AS $key => $value)
			{
				$string[] = $this->fieldName($key) . ' = ' . $this->escape($value);
			}
		}
		return ' WHERE ' . implode(' AND ' , $string);
	}



	/**
	 * Determine if table exists in database
	 *
	 * @param string $table
	 * @param string $dbName
	 * @return bool
	 */
	public function isTable($table, $dbName = null)
	{
		if (is_null($dbName))
		{
			$dbName = $this->server->database;
		}

		return $this->dbIsTable($table, $dbName);
	}



	/**
	 * Determine if field exists in table
	 *
	 * @param string $field
	 * @param string $table
	 * @param string $dbname
	 * @return bool
	 */
	public function isField($field, $table, $dbName = null)
	{
		if (is_null($dbName))
		{
			$dbName = $this->server->database;
		}

		return $this->dbIsField($field, $table, $dbName);
	}




	/**
	 * Release result set from memory
	 */
	public function freeResult($resultSet)
	{
		if (is_int($resultSet))
		{
			$this->cache->freeResult($resultSet);
		}
		else
		{
			$this->dbFreeResult($resultSet);
		}
	}



	/**
	 * Sets up a pointer to a writeable db. Should be used in
	 * cases where a master/slave setup is available.
	 *
	 * @param <type> $db
	 */
	public function setWriteDatabase($db)
	{
		$this->writeDb = $db;
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
			if (in_array(strtoupper($type), self::$types))
			{
				$this->type = strtoupper($type);
			}
		}

		public function setColumnFormat($format)
		{
			if (in_array(strtoupper($format), array('FIXED', 'DYNAMIC', 'DEFAULT')))
			{
				$this->columnFormat = strtoupper($format);
			}
		}

		public function setStorage($storage)
		{
			if (in_array(strtoupper($storage, array('DISK', 'MEMORY', 'DEFAULT'))))
			{
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

		function __construct($expression = null)
		{
			if (!is_null($expression))
			{
				$this->set($expression);
			}
		}

		public function set($expression)
		{
			$this->expression = $expression;
		}

		public function get()
		{
			return $this->expression;
		}

		public function toString()
		{
			return $this->expression;
		}
	}
}
