<?php

class TauPostgres extends TauDb
{
	/**
	 * Reference to most recent result set
	 * @var PgSql\Result
	 */
	private $rs;

	/**
	 * Last query
	 * @param string $query
	 */
	private $query = '';

	function __construct($server)
	{
		$this->server = clone $server;
	}

	function connect($retries = 0)
	{
		if ($this->server->connection) {
			return $this->server->connection;
		}

		try {
			$host = $this->server->host;
			$dbname = $this->server->database;
			$user = $this->server->username;
			$password = $this->server->password;
			$port = (int) $this->server->port;

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
			if ($retries < 1) {
				usleep(500000);
				$this->connect(++$retries);
				return;
			}
		}

		if (!$this->server->connection) {
			if ($retries < 1) {
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
	public function dbUseDatabase($database)
	{
		$this->close();
		$this->server->dbname = $database;
		$this->connect();
	}

	/**
	 * Close connection to database
	 */
	public function close()
	{
		if ($this->server->connection) {
			pg_close($this->server->connection);
			$this->server->connection = null;
		}
	}

	/**
	 * Make a query to the database server
	 *
	 * @param string $sql
	 * @return PgSql\Result|false
	 */
	public function dbQuery($sql)
	{
		$connection = $this->connect();
		$this->query = $sql;
		$this->rs = pg_query($connection, $sql);
		return $this->rs;
	}

	/**
	 * Release a result set from memory
	 *
	 * @param PgSql\Result $rs
	 */
	public function dbFreeResult($rs = null)
	{
		if ($rs) {
			pg_free_result($rs);
		} else if ($this->rs) {
			pg_free_result($this->rs);
			$this->rs = null;
		}
	}

	/**
	 * Retrieve last error
	 *
	 * @return string
	 */
	public function dbError()
	{
		return pg_last_error($this->server->connection);
	}

	/**
	 * Convert a PHP string into an SQL field name
	 *
	 * @param string|TauSqlExpression $fieldName
	 * @return string
	 */
	public function dbFieldName($fieldName)
	{
		// Check if field is a raw expression
		if ($fieldName instanceof TauSqlExpression) {
			return $fieldName->get();
		}

		// Split string at "as," if available
		$pos = stripos($fieldName, " as ");
		if ($pos !== false) {
			$field = substr($fieldName, 0, $pos);
			$alias = substr($fieldName, $pos + 4);
		} else {
			$field = $fieldName;
			$alias = null;
		}

		$field = trim($field, "\"\r\n\t ");
		if ($alias) {
			$alias = trim($alias, "\"\r\n\t ");
			$alias = pg_escape_identifier($this->server->connection, $alias);
		}

		// Look for SQL function. "(" isn't allowed in field names
		if (strpos($field, "(")) {
			if ($alias) {
				return $field . ' as ' . $alias;
			}
			return $field;
		}

		// Maybe it's a SELECT ALL field
		if ($field === "*") {
			return $field;
		}

		// Everyday, ordinary field name
		$parts = explode('.', $field);
		foreach ($parts as $key => $value) {
			$value = trim($value, "\"\r\n\t ");
			if ($value === "*") {
				$parts[$key] = "*";
			} else {
				$parts[$key] = pg_escape_identifier($this->server->connection, $value);
			}
		}
		$field = implode(".", $parts);

		if ($alias) {
			return $field . ' as ' . $alias;
		}
		return $field;
	}

	/**
	 * Convert a PHP string into an SQL table name
	 *
	 * @param string $table_name
	 * @return string
	 */
	public function dbTableName($table_name)
	{
		return $this->dbFieldName($table_name);
	}

	/**
	 * Convert a PHP string value to a string value suitable for insertion in to SQL query.
	 *
	 * @param string $unescaped_string
	 * @return string
	 */
	public function dbStringify($unescaped_string, $quote = true)
	{
		$connection = $this->connect();

		if ($quote) {
			return pg_escape_literal($connection, $unescaped_string);
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
	public function dbDateTime($time)
	{
		if ($time instanceof DateTime) {
			return $time->format('Y-m-d H:i:s.v');
		} else {
			$datetime = new DateTime($time);
			return $datetime->format('Y-m-d H:i:s.v');
		}
	}

	/**
	 * Escape a string for insertion in to database.
	 *
	 * @param string $unescaped_string
	 * @return string
	 */
	public function dbEscape($unescaped_string)
	{
		return pg_escape_string($this->server->connection, $unescaped_string);
	}

	/**
	 * Fetch a row from the database
	 *
	 * @param PgSql\Result $rs
	 * @return array|false
	 */
	protected function dbFetch($rs = null)
	{
		if ($rs) {
			return pg_fetch_assoc($rs);
		} else {
			return pg_fetch_assoc($this->rs);
		}
	}

	/**
	 * Fetch a row from the database as on object
	 *
	 * @param PgSql\Result $rs
	 * @return array|false
	 */
	protected function dbFetchObject($rs = null)
	{
		if ($rs) {
			return pg_fetch_object($rs);
		} else {
			return pg_fetch_object($this->rs);
		}
	}

	/**
	 * Fetch all rows from the database
	 *
	 * @param PgSql\Result $rs
	 * @return array
	 */
	protected function dbFetchAll($rs)
	{
		if (!$rs) {
			$rs = $this->rs;
		}

		$rows = [];
		while ($row = pg_fetch_assoc($rs)) {
			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * Retrieve all rows, each row as an object, from an SQL query
	 *
	 * @param PgSql\Result $rs
	 * @return array An array of records retrieved, each record as an object.
	 */
	protected function dbFetchAllObject($rs, $id = null)
	{
		if (!$rs) {
			$rs = $this->rs;
		}

		$rows = [];
		while ($row = pg_fetch_object($rs)) {
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
	 * @param PgSql\Result $rs
	 * @param string $id Name of field to use as ID. If left blank, the first field is used.
	 * @return array
	 */
	protected function dbFetchAllWithId($rs, $id = false)
	{
		$rows = [];
		while ($row = pg_fetch_assoc($rs)) {
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
	public function insertId($rs = null)
	{
		// Erm, postgres doesn't really suppport this.
		// There is a "RETURNING" thing that can be appended
		// to INSERT statements. This can be used like:

		// $sql = $db->insertSql('users', [
		// 	'username' => 'Bobby',
		// 	'password' => 'pw',
		// 	'email' => 'bobby@example.com',
		// 	'created_on' => new DateTime(),
		// 	'last_login' => new DateTime()
		// ]);

		// $sql .= ' RETURNING ("user_id")';
		// $rs = $db->query($sql);
		// $id = $db->fetchValue($rs);

		return 0;
	}

	/**
	 * Get number of rows affected by last INSERT or UPDATE
	 *
	 * @return int
	 */
	public function affectedRows()
	{
		return pg_affected_rows($this->server->connection);
	}

	/**
	 * Get number of rows returned in last query
	 *
	 * @return int
	 */
	public function numRows($rs = null)
	{
		return pg_num_rows($rs ? $rs : $this->rs);
	}

	/**
	 * Determine if table exists in database
	 *
	 * @param string $table
	 * @param string $schema
	 * @return bool
	 */
	public function dbIsTable($table, $schema = null)
	{
		if (!$schema || $schema === $this->server->database) {
			$schema = 'public';
		}

		$schema = $this->tableName($schema);
		$table = $this->tableName($table);
		$schema = trim($schema, '"');
		$table = trim($table, '"');

		$sql = "SELECT to_regclass('$schema.$table')";
		$result = $this->dbQuery($sql);
		$row = $this->dbFetch($result);
		$this->dbFreeResult($result);
		return !!$row['to_regclass'];
	}

	/**
	 * Determine if field exists in table
	 *
	 * @param string $field
	 * @param string $table
	 * @param string $dbname
	 * @return bool
	 */
	public function dbIsField($field, $table, $dbName = null)
	{
		$field = $this->dbStringify($field);
		$table = $this->dbStringify($table);

		$sql = "SELECT *
			FROM information_schema.columns
			WHERE \"table_name\"={$table} AND \"column_name\"={$field}";

		$rs = $this->dbQuery($sql);
		$row = $this->dbFetch($rs);
		$this->freeResult($rs);
		return !!$row;
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
	public function dbUpsert($table, $insert, $update, $conflict = [])
	{
		if ($conflict) {
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
		} else {
			// This takes a guess at the conflicting column if none is provided.
			// Why does the postgres library not offer an exception handling system?
			// This is awful. I should probably convert this whole class to PDO.
			$old_handler = set_error_handler(function ($errno, $errstr) use ($table, $insert, $update) {
				$pos = strpos($errstr, 'DETAIL:  Key (');
				if ($pos !== false) {
					$key = substr($errstr, $pos + 14);
					$pos = strpos($key, ')');
					if ($pos !== false) {
						$key = substr($key, 0, $pos);
						$this->dbUpsert($table, $insert, $update, [$key]);
					}
				}
			}, E_WARNING);

			$this->insert($table, $insert);

			set_error_handler($old_handler, E_WARNING);
		}
	}
}