<?php
/**
 * Postgres Database Module For TAU
 * This definitely does not work completely and I probably
 * shouldn't even include it in Tau.
 *
 * @Author          theyak
 * @Copyright       2022
 *
 * changelog:
 *   0.0.1  Jan 18, 2022  Created
 */

if (!defined('TAU'))
{
	exit;
}

class TauPostgres extends TauDb
{
	/**
	 * Reference to most recent result set
	 * @var resource
	 */
	private $result_set;

	/**
	 * Last query
	 * @param string $query
	 */
	private $query = '';

	function __construct($server) {
		$this->server = clone $server;
	}

	function connect($retries = 0) {
		if ($this->server->connection) {
			return $this->server->connection;
		}

		try {
			$host = $this->server->host;
			$dbname = $this->server->database;
			$user = $this->server->user;
			$password = $this->server->password;
			$port = (int)$this->server->port;

			$connection = [];
			if ($host) {
				$connection[] = 'host=' . $host;
			}

			if ($port) {
				$connection[] = 'port=' . $port;
			}

			if ($dbname) {
				$connection[] = 'dbname=' . $dbname;
			}

			if ($user) {
				$connection[] = 'user=' . $user;
			}

			if ($password) {
				$connection[] = 'password=' . $password;
			}

			$connection_string = implode(' ', $connection);
			$this->server->connection = pg_connect($connection_string);
		} catch (Exception $ex) {
			if ($retries < 3) {
				usleep(500000);
				$this->connect(++$retries);
				return;
			}
		}

		if (!$this->server->connection) {
			if ($retries < 3) {
				usleep(500000);
				$this->connect(++$retries);
				return;
			}

			die("Unable to connect to database.");
		}

		return $this->server->connection;
	}


	/**
	 * Use a particular database
	 *
	 * @param string $database
	 */
	public function dbUseDatabase($database) {
		$this->close();
		$this->server->dbname = $database;
		$this->connect();
	}


	/**
	 * Close connection to database
	 */
	public function close() {
		if ($this->server->connection) {
			pg_close($this->server_connection);
			$this->server->connection = null;
		}
	}



	/**
	 * Make a query to the database server
	 * @param string $sql
	 * @return handle
	 */
	public function dbQuery($sql) {
		$connection = $this->connect();
		$this->query = $sql;
		$this->result_set = pg_query($connection, $sql);
		return $this->result_set;
	}


	/**
	 * Release a result set from memory
	 *
	 * @param handle $result_set
	 */
	public function dbFreeResult($result_set = null) {
		if ($result_set) {
			pg_free_result($result_set);
		} else if ($this->result_set) {
			pg_free_result($this->result_set);
			$this->result_set = null;
		}
	}


	/**
	 * Retrieve last error
	 * @return string
	 */
	public function dbError() {
		return pg_errormessage($this->server->connection);
	}


	/**
	 * Convert a PHP string into an SQL field name. This will properly handle
	 * cases of field names prefixed with table names.
	 *
	 * @param string $field_name
	 * @return string
	 */
	public function dbFieldName($field_name) {
		$connection = $this->connect();

		$parts = explode('.', $field_name);
		foreach($parts AS $key => $value) {
			$value = trim('"');
			$parts[$key] = '"' . pg_escape_string($connection, $value) . '"';
		}

		return implode('.', $parts);
	}


	/**
	 * Convert a PHP string into an SQL table name
	 *
	 * @param string $table_name
	 * @return string
	 */
	public function dbTableName($table_name) {
		return $this->dbFieldName($table_name);
	}


	/**
	 * Convert a PHP string value to a string value suitable for insertion in to SQL query.
	 *
	 * @param string $unescaped_string
	 * @return string
	 */
	public function dbStringify($unescaped_string, $quote = true) {
		$connection = $this->connect();

		if ($quote) {
			return "'" . pg_escape_string($connection, $unescaped_string) . "'";
		} else {
			return pg_escape_string($connection, $unescaped_string);
		}
	}


	/**
	 * Encode a timestamp in any of the standard PHP formats accepted
	 * by DateTime() and strtotime() to database format
	 *
	 * @param DateTime|string $time
	 * @return string
	 */
	public function dbDateTime($time) {
		if ($time instanceof DateTime) {
			return $time->format('Y-m-d H:i:s.v');
		} else {
			$datetime = new DateTime($time);
			return $datetime->format('Y-m-d H:i:s.v');
		}
	}


	/**
	 * Escape a string for insertion in to database. Effectively to same as dbStringify.
	 *
	 * @param type $unescaped_string
	 * @return string
	 */
	public function dbEscape($unescaped_string) {
		return pg_escape_string($this->server->connection, $unescaped_string);
	}


	/**
	 * Fetch a row from the database
	 *
	 * @param handle $result_set
	 * @return assoc|false
	 */
	protected function dbFetch($result_set) {
		return @pg_fetch_assoc($result_set);
	}


	/**
	 * Fetch a row from the database as on object
	 *
	 * @param handle $result_set
	 * @return assoc|false
	 */
	protected function dbFetchObject($result_set) {
		return pg_fetch_object($result_set);
	}


	/**
	 * Fetch all rows from the database
	 *
	 * @param handle $result_set
	 * @return assoc
	 */
	protected function dbFetchAll($result_set) {
		$rows = array();
		while ($row = pg_fetch_assoc($result_set)) {
			$rows[] = $row;
		}

		return $rows;
	}


	/**
	 * Retrieve all rows, each row as an object, from an SQL query
	 *
	 * @param handle $result_set
	 * @return assoc An array of records retrieved, each record as an object.
	 */
	protected function dbFetchAllObject($result_set, $id = null) {
		$rows = [];
		while ($row = pg_fetch_object($result_set)) {
			if (!$id) {
				$rows[] = $row;
			} else if ($id === true) {
				$rows[reset($row)] = $row;
			} else if (property_exists($row, $id)) {
				$rows[$row->{$id}] = $row;
			} else {
				$rows[] = $row;
			}
		}
		return $rows;
	}


	/**
	 * Fetch all rows from the database storing data in an associative array.
	 *
	 * @param handle $result_set
	 * @param $id Name of field to use as ID. If left blank, the first field is used.
	 * @return assoc
	 */
	protected function dbFetchAllWithId($result_set, $id = false) {
		$rows = array();
		while ($row = pg_fetch_assoc($result_set)) {
			if ($id && isset($row[$id])) {
				$rows[$row[$id]] = $row;
			} else {
				$rows[reset($row)] = $row;
			}
		}
		return $rows;
	}


	/**
	 * Get the ID from the last insert
	 * @return int|string
	 */
	public function insertId() {
		// Erm, postgres doesn't really suppport this. Not sure what to do.
		return 0;
	}


	/**
	 * Get number of rows affected by last INSERT or UPDATE
	 *
	 * @return int
	 */
	public function affectedRows() {
		return pg_affected_rows($this->server->connection);
	}


	/**
	 * Get number of rows returned in last query
	 *
	 * @return int
	 */
	public function numRows($result_set = null) {
		return pg_num_rows($result_set ? $result_set : $this->result_set);
	}


	/**
	 * Determine if table exists in database
	 *
	 * @param string $table
	 * @param string $dbName
	 * @return bool
	 */
	public function dbIsTable($table, $dbName = null) {
		if (!$dbName) {
			$dbName = $this->server->database;
		}

		$dbName = $this->tableName($dbName);
		$table = $this->tableName($table);

		$sql = "SELECT to_regclass($dbName . '.' . $table)";
		$result = $this->dbQuery($sql);
		$row = $this->dbFetch($result);
		print_r($row);
		$this->dbFreeResult($result);
	}


	/**
	 * Determine if field exists in table
	 * @param string $field
	 * @param string $table
	 * @param string $dbname
	 * @return bool
	 */
	public function dbIsField($field, $table, $dbName)
	{
		$sql = 'SHOW COLUMNS FROM ' . $this->dbFieldName($dbName) . '.' . $this->dbFieldName($table) . '
			WHERE `field` = ' . $this->dbStringify($field);
		$result_set = $this->select($sql);
		if ($row = $this->fetch($result_set))
		{
			$this->freeResult($result_set);
			return true;
		}
		return false;
	}



	/**
	 * Perform an Insert/Update operation when there is a duplicate
	 * unique or primary key.
	 *
	 * @param string $table Name of table
	 * @param array $insert Data to insert
	 * @param array $update Data to update if insert fails
     * @param array $conflict List of columns to use to determine conflict
	 */
	public function dbUpsert( $table, $insert, $update, $conflict = [] )
	{
		if (!is_array($conflict)) {
			$conflict = [$conflict];
		}

		$sql = $this->insertSql($table, $insert);
		$sql .= " ON CONFLICT(" . implode(", ", $conflict) . ") DO UPDATE SET";

		$values = [];
		foreach ($update as $fieldName => $value) {
			$values[] = $this->fieldName($fieldName) . ' = ' . $this->escape($value);
		}
		$sql .= implode(', ', $values);

		$this->query($sql);
	}
}
