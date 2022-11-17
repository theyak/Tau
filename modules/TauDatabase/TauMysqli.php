<?php
/**
 * MySQL Database Module For TAU
 *
 * @Author          theyak
 * @Copyright       2011
 * @Project Page    None!
 * @Dependencies    TauError
 * @Documentation   None!
 *
 * changelog:
 *   1.0.0  Apr 14, 2013  Created
 *   1.0.1  Mar 26, 2014  Add dbDatetime
 */

if (!defined('TAU')) {
	exit;
}

class TauMysqli extends TauDb
{
	/**
	 * Reference to most recent result set
	 * @var resource
	 */
	private $resultSet;

	/**
	 * Last query
	 * @param string $query
	 */
	private $query = '';


	function __construct($server)
	{
		$this->server = $server;

		if (empty($server->host)) {
			$this->server->host = '127.0.0.1';
		}

		if (empty($server->port)) {
			$this->server->port = 3306;
		}
	}

	function connect()
	{
		if (!$this->server->connection) {
			$this->server->connection = mysqli_connect(
				$this->server->host,
				$this->server->username,
				$this->server->password,
				$this->server->database,
				$this->server->port
			);

			if ($this->server->terminate_on_error) {
				if (!$this->server->connection) {
					if ($this->server->host === '127.0.0.1' || $this->server->host === 'localhost') {
						$message = array(
							'Unable to connect to database. The database server is either down ',
							'or an invalid username and password combination was supplied.<br><br>',
							'You will need to grant access to the database for user ' . $this->server->username,
							' with something like:<br><br>',
							'&nbsp;&nbsp;&nbsp;&nbsp;GRANT ALL ON ' . $this->server->database .
							'.* TO ' . $this->server->username,
							'@\'%\' IDENTIFIED BY \'PASSWORD\'',
							'<br><br>Please see <a href="http://www.cyberciti.biz/tips/',
							'how-do-i-enable-remote-access-to-mysql-database-server.html">',
							'How Do I Enable Remote Access To MySQL Database Server?</a> ',
							'for more information.',
						);
						TauError::fatal(implode('', $message));
					} else {
						TauError::fatal('Unable to connect to database.');
					}
				} else {
					mysqli_set_charset($this->server->connection, "utf8");
				}
			}
		}

		return $this->server->connection;
	}



	/**
	 * Use a particular database
	 * @param string $database
	 */
	public function dbUseDatabase($database)
	{
		if ($database) {
			if (!@mysqli_select_db($this->server->connection, $database)) {
				TauError::fatal('Invalid database');
			}
		}
	}



	/**
	 * Close connection to database
	 */
	public function close()
	{
		if ($this->server->connection) {
			@mysqli_close($this->server->connection);
			$this->server->connection = false;
		}
	}



	/**
	 * Make a query to the database server
	 * @param string $sql
	 * @return resource|null
	 */
	public function dbQuery($sql)
	{
		if (!$this->server->connection) {
			$this->connect();
		}
		$this->query = $sql;
		if ($this->server->connection) {
			$this->resultSet = mysqli_query($this->server->connection, $sql);
			return $this->resultSet;
		}
		return null;
	}



	/**
	 * Release a result set from memory
	 * @param mysqli_result $resultSet
	 */
	public function dbFreeResult($resultSet = null)
	{
		if ($resultSet == null) {
			if ($this->resultSet != null) {
				@mysqli_free_result($this->resultSet);
				$this->resultSet = null;
			}
		} else {
			@mysqli_free_result($resultSet);
		}
	}



	/**
	 * Retrieve last error
	 * @return string
	 */
	public function dbError()
	{
		if ($this->server->connection) {
			return mysqli_error($this->server->connection);
		}

		return "No connection";
	}



	/**
	 * Convert a PHP string into an SQL field name
	 *
	 * @param string $unescaped_string
	 * @return string
	 */
	public function dbFieldName($fieldName)
	{
		$parts = explode('.', $fieldName);
		foreach ($parts as $key => $value) {
			$parts[$key] = '`' . $this->dbEscape(trim($value, '`')) . '`';
		}

		return implode('.', $parts);
	}



	/**
	 * Convert a PHP string into an SQL table name
	 *
	 * @param string $unescaped_string
	 * @return string
	 */
	public function dbTableName($tableName)
	{
		return $this->dbFieldName($tableName);
	}


	/**
	 * Convert a PHP string value to a string value suitable for insertion in to SQL query.
	 *
	 * @param string $unescaped_string
	 * @return string
	 */
	public function dbStringify($unescaped_string, $quote = true)
	{
		if ($quote) {
			return "'" . $this->dbEscape($unescaped_string) . "'";
		} else {
			return $this->dbEscape($unescaped_string);
		}
	}



	/**
	 * Encode a timestamp in any of the standard PHP formats accepted
	 * by DateTime() and strtotime() to database format
	 *
	 * @param mixed $time
	 * @return string
	 */
	public function dbDateTime($time)
	{
		$datetime = new DateTime($time);
		return $datetime->format('Y-m-d H:i:s');
	}



	/**
	 * Escape a string for insertion in to database
	 *
	 * @param string $unescaped_string
	 * @return string
	 */
	public function dbEscape($unescaped_string)
	{
		if ($this->server->connection) {
			return @mysqli_real_escape_string($this->server->connection, $unescaped_string);
		} else {
			$search = ["'", '"', chr(0), "\n", "\r", chr(26), '\\'];
			$replace = ["\\'", "\\0", "\\n", "\\r", "\\Z", "\\\\"];
			return str_replace($search, $replace, $unescaped_string);
		}
	}



	/**
	 * Fetch a row from the database
	 * @param mysqli_result $resultSet
	 * @return array|false
	 */
	protected function dbFetch($resultSet)
	{
		return @mysqli_fetch_assoc($resultSet);
	}


	/**
	 * Fetch a row from the database as on object
	 * @param mysqli_result $resultSet
	 * @return array|false
	 */
	protected function dbFetchObject($resultSet)
	{
		return @mysqli_fetch_object($resultSet);
	}



	/**
	 * Fetch all rows from the database
	 * @param mysqli_result $resultSet
	 * @return array
	 */
	protected function dbFetchAll($resultSet)
	{
		$rows = array();
		while ($row = @mysqli_fetch_assoc($resultSet)) {
			$rows[] = $row;
		}

		return $rows;
	}


	/**
	 * Retrieve all rows, each row as an object, from an SQL query
	 *
	 * @param mysqli_result $resultSet
	 * @return array An array of records retrieved, each record as an object.
	 */
	protected function dbFetchAllObject($resultSet)
	{
		$rows = array();
		while ($row = @mysqli_fetch_object($resultSet)) {
			$rows[] = $row;
		}
		return $rows;
	}



	/**
	 * Fetch all rows from the database storing data in an associative array
	 *
	 * @param mysqli_result $resultSet
	 * @param string $id Name of field to use as ID. If left blank, the first field is used.
	 * @return array
	 */
	protected function dbFetchAllWithId($resultSet, $id = '')
	{
		$rows = array();
		while ($row = @mysqli_fetch_assoc($resultSet)) {
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
	 * @return int
	 */
	public function insertId()
	{
		return @mysqli_insert_id($this->server->connection);
	}



	/**
	 * Get number of rows affected by last INSERT or UPDATE
	 * @return int
	 */
	public function affectedRows()
	{
		return @mysqli_affected_rows($this->server->connection);
	}



	/**
	 * Get number of rows returned in last query
	 * @return int
	 */
	public function numRows()
	{
		return @mysqli_num_rows($this->resultSet);
	}



	/**
	 * Determine if table exists in database
	 * @param string $table
	 * @param string $dbName
	 * @return bool
	 */
	public function dbIsTable($table, $dbName)
	{
		$sql = 'SELECT table_name
			FROM information_schema.tables
			WHERE `table_schema` = ' . $this->dbStringify($dbName) . '
				AND table_name = ' . $this->dbStringify($table);
		$resultSet = $this->select($sql);
		if ($row = $this->fetch($resultSet)) {
			$this->freeResult($resultSet);
			return true;
		}
		return false;
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
		$resultSet = $this->select($sql);
		if ($row = $this->fetch($resultSet)) {
			$this->freeResult($resultSet);
			return true;
		}
		return false;
	}



	/**
	 * Perform an Insert/Update operation
	 *
	 * @param string $table Name of table
	 * @param array $insert Data to insert
	 * @param array $update Data to update if insert fails
	 * @param mixed $where Unused in MySQL
	 */
	public function dbInsertUpdate($table, $insert, $update, $where)
	{
		$sql = $this->insertSql($table, $insert);
		$sql .= ' ON DUPLICATE KEY UPDATE ';

		$values = array();
		foreach ($update as $fieldName => $value) {
			$values[] = $this->fieldName($fieldName) . ' = ' . $this->escape($value);
		}
		$sql .= implode(', ', $values);

		$this->query($sql);
	}



	/**
	 * Perform an Insert/Update operation when there is a duplicate
	 * unique or primary key.
	 *
	 * @param string $table Name of table
	 * @param array $insert Data to insert
	 * @param array $update Data to update if insert fails
	 */
	public function dbUpsert($table, $insert, $update)
	{
		$sql = $this->insertSql($table, $insert);
		$sql .= ' ON DUPLICATE KEY UPDATE ';

		$values = array();
		foreach ($update as $fieldName => $value) {
			$values[] = $this->fieldName($fieldName) . ' = ' . $this->escape($value);
		}
		$sql .= implode(', ', $values);

		$this->query($sql);
	}
}