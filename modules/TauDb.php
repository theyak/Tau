<?php
/**
 * Database Module For TAU
 *
 * @Author          theyak
 * @Copyright       2012
 * @Project Page    https://github.com/theyak/Tau
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
 *   1.0.0  Sep  8, 2012  Created
 *
 *   1.1.0  Apr 24, 2013  Added small, very limited, method chaining for SELECTs
 *
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
 * integer($value)
 *   Convert a PHP value to an integer suitable for use in SQL query
 *
 * boolean($value)
 *   Convert a PHP value to a boolean suitable for use in SQL query
 *
 * escape($value)
 *   Convert PHP datatypes to those appropriate for SQL statements
 *
 * stringify($string, $quote=true)
 *   Encode a string into a string suitable for use in a SQL statement,
 *   including proper escaping and quotations around value.
 *
 * fieldName($fieldName)
 *   Return a field name in SQL format
 *
 * insertSql($table, $data)
 *   Create SQL for an INSERT statement
 *
 * insertMultiSql($table, $data)
 *   Create SQL for a multi-row INSERT statement
 *
 * updateSql($table, $update, $where)
 *   Create SQL for an UPDATE statement
 *
 * inSetSql($field, $set, $negate)
 *   Create SQL for IN SET
 *
 * whereSql($where, $includeKeyword = true)
 *   Create a WHERE string for SQL statement. If an array is passed in, the WHERE
 *   is constructed based on the key and value pairs of the array and ANDed together.
 *   If a string is passed in, it is just returned, possibly with WHERE prepended
 *   if needed.
 *
 * limitSql(Start, $limit)
 *   Create a LIMIT string for SQL
 *
 * select($sql, $expires = 0)
 *   Perform a SELECT query on the database, including cache if supplied
 *
 * query($sql, $expires = 0)
 *   Perform an SQL query (anything other than SELECT) on the database
 *
 * fetch($resultSet = null)
 *   Fetch a row from a result set
 *
 * fetchObject($resultSet = null)
 *   Fetch a row from a result set as an object
 *
 * fetchOne($sql, $expires = 0)
 *   Retrieve exactly one row from SQL query
 *
 * fetchOneObject($sql, $expires = 0)
 *   Retrieve exactly one row as an objectfrom SQL query
 *
 * fetchAll($sql, $expires = 0)
 *   Retrieve all rows from an SQL query
 *
 * fetchAllObject($sql, $expires = 0)
 *   Retrieve all rows from an SQL query. Each row is an object.
 *
 * fetchAllWithId($sql, $expires = 0)
 *   Retrieve all rows from an SQL query indexed by an ID
 *
 * fetchPairs($sql, $expires = 0)
 *   Fetch pairs from the database. First value in result set is used as array key
 *
 * fetchColumn($sql, $expires = 0)
 *   Fetch a column from a database SELECT query
 *
 * fetchValue($sql, $expires = 0)
 *   Fetch a single value from the database. Very useful for things like
 *   SELECT COUNT(*) FROM ... WHERE ...
 *
 * insert($table, $data)
 *   Insert data in to a table
 *
 * insertMulti($table, $data)
 *   Insert multiple rows in to a table
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
 * isTable($table, $dbName = null)
 *   Determine if table exists in database
 *
 * isField($field, $table, $dbName = null)
 *   Determine if field exists in table
 *
 * freeResult($resultSet)
 *   Release result set from memory
 *
 * The following are for a small, barely functional, method chaining SELECT builder
 *
 * fields($fields)
 *   List of fields to user for SELECT query
 *
 * from($table)
 *   Table to use for SELECT query
 *
 * where($field, $value)
 *   Where option for SELECT query
 *
 * or_where($field, $value)
 *   OR where option for SELECT query
 *
 * limit($limit, $start)
 *   Limit values for SELECT query
 *
 * sql($table = '', $limit = 0, $start = 0)
 *   Builds the SQL statement for the SELECT builder
 *
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
	 * Convert a PHP value to an integer suitable for use in SQL query
	 *
	 * @param int $value
	 * @return int
	 */
	public function integer($value)
	{
		return intval($value);
	}



	/**
	 * Convert a PHP value to a boolean value suitable for use in SQL query.
	 * @param bool $value
	 * @return
	 */
	public function boolean($value)
	{
		return intval($value);
	}



	/**
	 * Encode a string into a string suitable for use in a SQL statement,
	 * including proper escaping and quotations around value.
	 *
	 * @param type $string
	 * @return string
	 */
	public function stringify($string, $quote = true)
	{
		return $this->dbStringify($string, $quote);
	}



	/**
	 * Return a field name in SQL format
	 *
	 * @param type $fieldName
	 * @return type
	 */
	public function fieldName($fieldName)
	{
		return $this->dbFieldName($fieldName);
	}



	/**
	 * Return a field name in SQL format
	 *
	 * @param type $fieldName
	 * @return type
	 */
	public function tableName($fieldName)
	{
		return $this->dbTableName($fieldName);
	}



	/**
	 * Create SQL for an INSERT statement
	 *
	 * @param string $table
	 * @param array $data
	 * @return string
	 */
	public function insertSql($table, $data)
	{
		if (!is_array($data) || sizeof($data) < 1)
		{
			return;
		}

		if (is_array(reset($data)))
		{
			return $this->insertMultiSql($table, $data);
		}

		$table = $this->tableName($table);
		$fieldNames = $values = array();
		foreach ($data AS $fieldName => $value)
		{
			$fieldNames[] = $this->fieldName($fieldName);
			$values[] = $this->escape($value);
		}
		$sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $fieldNames) . ') ';
		$sql .= 'VALUES (' . implode(', ', $values) . ')';

		return $sql;
	}



	/**
	 * Create SQL for a multi-row INSERT statement
	 *
	 * @param string $table
	 * @param array $data Data to insert. Must be an array of arrays. The first
	 *        element of the array must contain field names for keys.
	 * @return string
	 */
	public function insertMultiSql($table, $data)
	{
		if (!is_array($data) || sizeof($data) < 1)
		{
			return;
		}


		$table = $this->tableName($table);
		$fieldNames = $values = $parts = array();
		$record_number = 0;

		foreach ($data AS $record)
		{
			$values = array();
			$record_number++;
			if ($record_number === 1)
			{
				foreach ($record AS $fieldName => $value)
				{
					$fieldNames[] = $this->fieldName($fieldName);
					$values[] = $this->escape($value);
				}
			}
			else
			{
				foreach ($record AS $fieldName => $value)
				{
					$values[] = $this->escape($value);
				}

			}
			$parts[] = '(' . implode(', ', $values) . ')';
		}

		$sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $fieldNames) . ') ';
		$sql .= 'VALUES ' . implode(',', $parts);

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
	public function updateSql($table, $data, $where = '')
	{
		if (!is_array($data) || sizeof($data) < 1)
		{
			return;
		}

		$table = $this->tableName($table);
		$values = array();
		foreach ($data AS $fieldName => $value)
		{
			$values[] = $this->fieldName($fieldName) . ' = ' . $this->escape($value);
		}

		$sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $values) . $this->whereSql($where);

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
	public function inSetSql($field, $set, $negate = false)
	{
		$sql = $this->fieldName($field) . ' ';
		$sql .= ($negate ? 'NOT IN (' : 'IN (');
		$sql .= implode(', ', array_map(array($this, 'escape'), $set)) . ')';

		return $sql;
	}



	/**
	 * Create a WHERE string for SQL statement. If an array is passed in, the WHERE
	 * is constructed based on the key and value pairs of the array and ANDed together.
	 * If a string is passed in, it is just returned, possibly with WHERE prepended
	 * if needed.
	 *
	 * @param string|array $where
	 * @param bool $includeKeyword Weather or not to include "WHERE" in result, default true
	 * @return string
	 *
	 * @examples
	 * $db->whereSql('where id = 1 AND class = 4);
	 * $db->whereSql('id = 1 AND class = 4');
	 * $db->whereSql(array('id' => 1, 'class' => 4));
	 */
	public function whereSql($where, $includeKeyword = true)
	{
		if (is_string($where))
		{
			if ($includeKeyword && stripos(trim($where), 'where') !== 0)
			{
				return ' WHERE ' . $where;
			}
			return ' ' . $where;
		}

		$string = array();

		if (is_array($where))
		{
			$w = reset($where);

			if ( is_array( $w ) )
			{
				$string = array(
					'(',
					$this->dbFieldName( $w[ 'field' ] ),
					$w[ 'comparison' ],
					$this->escape( $w[ 'value' ] ),
				);

				while ( $w = next($where) )
				{
					if ($w['or']) {
						$string[] = ') OR (';
					} else {
						$string[] = 'AND';
					}

					$string[] = $this->dbFieldName( $w[ 'field' ] );
					$string[] = $w[ 'comparison' ];
					$string[] = $this->escape( $w[ 'value' ] );
				}
				$string[] = ')';
				if ($includeKeyword)
				{
					return ' WHERE ' . implode(' ' , $string);
				}
				return implode(' ', $string);
			}
			else
			{
				foreach ($where AS $key => $value)
				{
					$string[] = $this->fieldName($key) . ' = ' . $this->escape($value);
				}
			}
			if ($includeKeyword)
			{
				return ' WHERE ' . implode(' AND ' , $string);
			}
			return implode(' AND ', $string);
		}
	}



	/**
	 * Create a LIMIT string for SQL
	 *
	 * @param int $start The row number to start retrieval at, or, if $limit === null,
	 *                   the number of rows to retrieve.
	 * @param int $limit Number of rows to retrieve. If null, $start will be used to
	 *                   determine number of rows to retrieve starting with first row.
	 * @return string
	 *
	 * @examples
	 * // SQL query for retrieving first 10 rows
	 * echo $db->limitSql(10);
	 * >>> LIMIT 10
	 *
	 * // Same as above
	 * echo $db->limitSql(0, 10);
	 * >>> LIMIT 10
	 *
	 * // SQL query for retrieving 10 rows starting from row 5
	 * echo $db->limiSql(5, 10);
	 * >>> LIMIT 5, 10
	 */
	public function limitSql($start, $limit = null)
	{
		$start = intval($start);

		if (is_null($limit)) {
			if (empty($start)) {
				return ' ';
			}
			return ' LIMIT ' . $start . ' ';
		}


		$limit = intval($limit);
		if (empty($start)) {
			return ' LIMIT ' . $limit . ' ';
		}

		return ' LIMIT ' . $start . ', ' . $limit . ' ';
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
		if (is_integer($value))                    return $this->integer($value);
		if (is_bool($value))                       return $this->boolean($value);

		if (is_array($value))
		{
			return '(' . implode(', ', array_map(array($this, 'escape'), $value)) . ')';
		}

		return $this->emptyValue();
	}



	/**
	 * Remove a chache entry for a particular SQL statement
	 *
	 * @param string $sql
	 */
	public function uncache($sql)
	{
		if ($this->cache)
		{
			$this->cache->queryRemove($sql);
		}
	}



	/**
	 * Perform a SELECT query on the database, including cache if supplied
	 *
	 * @param string $sql
	 * @param int $expires Time, in seconds, to keep data in cache
	 * @return handle
	 */
	public function select($sql = null, $expires = 0)
	{
		if (is_null($sql)) {
			$sql = $this->sql();
		}

		$query = array(
			'sql' => $sql,
			'start' => microtime(true),
		);

		$this->connect();

		if ($this->cache && $expires > 0)
		{
			// Load from cache. If cache miss, select as regular and store in cache
			$query['cached'] = true;
			$this->resultSet = $this->cache->queryLoad($sql);
			if (!$this->resultSet)
			{
				$query['cached'] = false;
				$resultSet = $this->dbQuery($sql);
				if ($resultSet)
				{
					// Save query in cache
					$this->resultSet = $this->cache->querySave($this, $resultSet, $sql, $expires);
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

		if ($this->resultSet === false && $this->terminateOnError)
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
	public function query($sql, $expires = 0)
	{
		// Cache calls MUST be select queries
		if ($expires && strtolower(substr(trim($sql), 0, 6)) == 'select')
		{
			return $this->select($sql, $expires);
		}

		if (!is_null($this->writeDb) && $this->writeDb != $this)
		{
			$this->writeDb->query($sql);
			return;
		}

		$query = array(
			'sql' => $sql,
			'start' => microtime(true),
		);

		$this->connect();
		$resultSet = $this->dbQuery($sql);

		$query['end'] = microtime(true);
		$query['time'] = $query['end'] - $query['start'];
		$query['cached'] = false;

		$this->db->queries[] = $query;

		if ($resultSet === false && $this->terminateOnError)
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
			 $row = $this->cache->queryFetch($resultSet);
		}
		else
		{
			$row = $this->dbFetch($resultSet);
		}

		if ($row)
		{
			return $row;
		}

		return false;
	}



	/**
	 * Fetch a row from a result set as an object
	 *
	 * @param resource $resultSet
	 * @return array|false
	 */
	public function fetchObject($resultSet = null)
	{
		if (is_null($resultSet))
		{
			$resultSet = $this->resultSet;
		}

		// Check if this is a cached object
		if (is_int($resultSet))
		{
			return (object)$this->cache->queryFetch($resultSet);
		}

		return $this->dbFetchObject($resultSet);
	}



	/**
	 * Retrieve exactly one row from SQL query
	 *
	 * @param string $sql
	 * @param int $expires Time, in seconds, to keep data in cache
	 * @return array|false
	 */
	public function fetchOne($sql = null, $expires = 0)
	{
		if (is_null($sql))
		{
			$this->limit(1);
			$resultSet = $this->select(null, $expires);
		}
		else if (is_string($sql))
		{
			if (stripos('limit', substr($sql, -12)) === false)
			{
				$resultSet = $this->select($sql . ' LIMIT 1', $expires);
			}
			else
			{
				$resultSet = $this->select($sql, $expires);
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
	 * Retrieve exactly one rexcord, as an object, from an SQL SELECT statement
	 *
	 * @param string $sql
	 * @param int $expires Time, in seconds, to keep data in cache
	 * @return array|false
	 */
	public function fetchOneObject($sql = null, $expires = 0)
	{
		if (is_null($sql))
		{
			$this->limit(1);
			$resultSet = $this->select(null, $expires);
		}
		else if (is_string($sql))
		{
			if (stripos('limit', substr($sql, -12)) === false)
			{
				$resultSet = $this->select($sql . ' LIMIT 1', $expires);
			}
			else
			{
				$resultSet = $this->select($sql, $expires);
			}
		}
		else
		{
			$resultSet = $sql;
		}

		$row = $this->fetchObject($resultSet);
		$this->freeResult($resultSet);
		return $row;
	}




	/**
	 * Retrieve all rows, each row as an object, from an SQL query
	 *
	 * @param string $sql
	 * @param int $expires Time, in seconds, to keep data in cache
	 * @return array An array of records retrieved, each record as an object.
	 */
	public function fetchAllObject($sql = null, $expires = 0)
	{
		if (is_string($sql) || is_null($sql))
		{
			$resultSet = $this->select($sql, $expires);
		}
		else
		{
			$resultSet = $sql;
		}

		if (is_int($resultSet))
		{
			$rows = array();
			$cache_rows = $this->cache->getResults($resultSet);
			foreach ($cache_rows AS $row)
			{
				$rows[] = (object)$row;
			}
		}
		else
		{
			$rows = $this->dbFetchAllObject($resultSet);
		}
		$this->freeResult($resultSet);

		return $rows;
	}


	/**
	 * Retrieve all rows from an SQL query
	 *
	 * @param string $sql
	 * @param int $expires Time, in seconds, to keep data in cache
	 * @return array
	 */
	public function fetchAll($sql = null, $expires = 0)
	{
		if (is_string($sql) || is_null($sql))
		{
			$resultSet = $this->select($sql, $expires);
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
	 * @param int $expires Time, in seconds, to keep data in cache
	 * @return array
	 */
	public function fetchAllWithId($sql = null, $id = '', $expires = 0)
	{
		if (is_string($sql) || is_null($sql))
		{
			$resultSet = $this->select($sql, $expires);
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
	 * Fetch pairs from the database. First value in result set is used as
	 * array key and second value in result set is set as value.
	 *
	 * @param String $sql
	 * @param int $expires Time, in seconds, to keep data in cache
	 * @return array
	 */
	public function fetchPairs($sql = null, $expires = 0)
	{
		if (is_string($sql) || is_null($sql))
		{
			$resultSet = $this->select($sql, $expires);
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
	 * @param int $expires Time, in seconds, to keep data in cache
	 * @return array
	 */
	public function fetchColumn($sql = null, $expires = 0)
	{
		if (is_string($sql) || is_null($sql))
		{
			$resultSet = $this->select($sql, $expires);
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
	 * @param int $expires Time, in seconds, to keep data in cache
	 * @return mixed
	 */
	public function fetchValue($sql = null, $expires = 0)
	{
		$row = $this->fetchOne($sql, $expires);
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
	public function insert($table, $data)
	{
		$sql = $this->insertSql($table, $data);
		$this->query($sql);
	}



	/**
	 * Insert multiple rows in to a table
	 *
	 * @param string $table
	 * @param array $values
	 */
	public function insertMulti($table, $data)
	{
		$sql = $this->insertMultiSql($table, $data);
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



	//
	// A little, not very functional, SELECT query builder
	//
	// $db
	//	->fields(array('key', 'name'))
	//	->from('aops_config')
	//	->where('id >', 1)
	//	->where('key', 3)
	//	->or_where('value', 'piggy')
	//	->limit(1);
	// echo $db->sql();
	// >>> SELECT `key`, `name`
	//  FROM aops_config
	//  WHERE ( `id` > 1 AND `key` = 3 ) OR ( `value` = 'piggy' )
	//  LIMIT 1
	//

	private $from = '';
	private $fields = ' * ';
	private $where = array();
	private $limit = '';


	/**
	 * Sets the table name for queries.
	 * @param string $table
	 * @return \TauDb
	 */
	public function from( $table ) {
		$this->from = $table;

		return $this;
	}


	/**
	 * Sets LIMIT statement of SQL query
	 * @param int $limit
	 * @param int $start
	 * @return \TauDb
	 */
	public function limit( $limit, $start = 0 ) {
		$limit = intval($limit);
		$start = intval($start);

		if ( $start ) {
			$this->limit = " LIMIT " . $start . ", " . $limit . " ";
		} else {
			$this->limit = " LIMIT " . $limit . " ";
		}

		return $this;
	}



	/**
	 * Parse a WHERE field name separating it from comparison operator if provided
	 * @param string $field Field name
	 * @return array
	 */
	private function parse_where( $field ) {
		$parts = explode( ' ', $field );
		if ( sizeof( $parts ) == 1 ) {
			return array( $field, '=' );
		}
		return $parts;
	}



	/**
	 * Sets a WHERE parameter
	 * @param string $field Field name. Can also include comparison operator.
	 * @param mixed $value
	 * @return \TauDb
	 */
	public function where( $field, $value ) {
		$parse = $this->parse_where( $field );

		$this->where[] = array(
			'field' => $parse[ 0 ],
			'comparison' => $parse[ 1 ],
			'or' => false,
			'value' => $value
		);

		return $this;
	}


	/**
	 * Sets a WHERE parameter prefixed by OR
	 * @param string $field Field name. Can also include comparison operator.
	 * @param mixed $value
	 * @return \TauDb
	 */
	public function or_where( $field, $value ) {
		$parse = $this->parse_where( $field );

		$this->where[] = array(
			'field' => $parse[ 0 ],
			'comparison' => $parse[ 1 ],
			'or' => sizeof($this->where) > 0,
			'value' => $value
		);

		return $this;
	}



	/**
	 * Set the list of fields for SELECT query. May be string or array. If doing
	 * fancy stuff, i.e., anything other than plain field names, use a string.
	 *
	 * @param string|array $fields List of fields
	 * @return \TauDb
	 */
	public function fields( $fields ) {
		if ( is_array( $fields ) )
		{
			$fields = array_map(array($this, 'dbFieldName'), $fields);
			$this->fields = ' ' . implode(', ', $fields) . ' ';
		}
		else
		{
			$this->fields = " " . $fields . " ";
		}

		return $this;
	}


	/**
	 * Builds the SELECT query
	 *
	 * @param string $table
	 * @param int $limit
	 * @param int $start
	 * @return string
	 */
	public function sql($table = '', $limit = 0, $start = 0)
	{
		$limit = intval($limit);
		$start = intval($start);
		$fields = " * ";
		if ( $this->fields ) {
			$fields = $this->fields;
		}

		if ( empty( $table ) ) {
			if ( empty( $this->from ) ) {
				return '';
			}
			$sql = "SELECT" . $fields . "\n  FROM " . $this->from;
		} else {
			$sql = "SELECT" . $fields . "\n  FROM " . $table;
		}

		if (sizeof($this->where))
		{
			$sql .= "\n WHERE " . $this->whereSql($this->where, false);
		}

		if ($limit) {
			if ($start) {
				$sql .= "\n LIMIT " . $start . ", " . $limit;
			} else {
				$sql .= "\n LIMIT " . $limit;
			}
		} else if ($this->limit) {
			$sql .= "\n" . $this->limit;
		}

		$this->from = '';
		$this->fields = ' * ';
		$this->where = array();
		$this->limit = '';

		return $sql;
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
