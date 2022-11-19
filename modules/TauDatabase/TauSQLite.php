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

class TauSQLite extends TauDb
{
	public SQLite3|null $db;
	public SQLite3Result|null $rs;
	public int $numRows = 0;

	function __construct($server)
	{
		$this->connect();
	}

	/**
	 * Connect to the database.
	 */
	function connect($filename = ":memory:")
	{
		$this->db = new SQLite3($filename);
		$this->db->enableExceptions(true);
	}

	/**
	 * Use a particular database
	 *
	 * @param string $database
	 */
	public function dbUseDatabase($database)
	{
		$this->db->close();
		$this->db = new SQLite3($database);
	}

	/**
	 * Close connection to database
	 */
	public function close()
	{
		if ($this->db) {
			$this->db->close();
			$this->db = null;
		}
	}

	/**
	 * Make a query to the database server
	 *
	 * @param string $sql
	 * @return SQLite3Result|bool
	 */
	public function dbQuery($sql)
	{
		$command = strtolower(substr($sql, 0, 6));
		if (in_array($command, ['select', 'explai', 'pragma'])) {
			$this->rs = $this->db->query($sql);
			return $this->rs;
		}

		return $this->db->exec($sql);
	}

	/**
	 * Release a result set from memory
	 *
	 * @param SQLite3Result $rs
	 */
	public function dbFreeResult($rs = null)
	{
		if ($rs) {
			$rs->finalize();
		} else if ($this->rs) {
			$this->rs->finalize();
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
		if ($this->db) {
			return $this->db->lastErrorMsg();
		}

		return "No connection";
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

		$field = trim(trim($field, '"\r\n\t '));
		if ($alias) {
			$alias = trim($alias, '"\r\n\t ');
			$alias = '"' . $this->dbEscape($alias) . '"';
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
			$value = trim($value, '"\r\n\t ');
			if ($value === "*") {
				$parts[$key] = "*";
			} else {
				$parts[$key] = '"' . $this->dbEscape($value) . '"';
			}
		}
		$field = implode(".", $parts);

		if ($alias) {
			return $field . ' as ' . $alias;
		}
		return $field;
	}

	/**
	 * Convert a string into an SQL table name.
	 * Follows the same rules as column names.
	 *
	 * @param string $tableName
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
	 * Escape a string for insertion in to database
	 *
	 * @param string $unescaped_string
	 * @return string
	 */
	public function dbEscape($unescaped_string)
	{
		if ($this->db) {
			return $this->db->escapeString($unescaped_string);
		} else {
			$search = ["'", '"', chr(0), "\n", "\r", chr(26), '\\'];
			$replace = ["\\'", "\\0", "\\n", "\\r", "\\Z", "\\\\"];
			return str_replace($search, $replace, $unescaped_string);
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
	 * Fetch a row from the database
	 *
	 * @param SQLite3Result $resultSet
	 * @return array|false
	 */
	protected function dbFetch($rs = null)
	{
		if ($rs) {
			$rs->fetchArray(SQLITE3_ASSOC);
		} else if ($this->rs) {
			$this->rs->fetchArray(SQLITE3_ASSOC);
		}
		return false;
	}

	/**
	 * Fetch a row from the database as on object
	 *
	 * @param SQLite3Result $resultSet
	 * @return stdClass|false
	 */
	protected function dbFetchObject($rs = null)
	{
		$row = $this->dbFetch($rs);
		if ($row) {
			return (object)$row;
		}

		return $row;
	}

	/**
	 * Fetch all rows from the database
	 *
	 * @param SQLite3Result $rs
	 * @return array
	 */
	protected function dbFetchAll($rs = null)
	{
		if (!$rs) {
			$rs = $this->rs;
		}

		$rows = [];
		while ($row = $rs->fetchArray(SQLITE3_ASSOC)) {
			$rows[] = $row;
		}

		$this->numRows = count($rows);

		return $rows;
	}

	/**
	 * Retrieve all rows, each row as an object, from an SQL query
	 *
	 * @param SQLite3Result $rs
	 * @return array An array of records retrieved, each record as an object.
	 */
	protected function dbFetchAllObject($rs = null)
	{
		if (!$rs) {
			$rs = $this->rs;
		}

		$rows = [];
		while ($row = $rs->fetchArray(SQLITE3_ASSOC)) {
			$rows[] = (object)$row;
		}

		$this->numRows = count($rows);

		return $rows;
	}



	/**
	 * Fetch all rows from the database storing data in an associative array
	 *
	 * @param SQLite3Result $rs
	 * @param string $id Name of field to use as ID. If left blank, the first field is used.
	 * @return array
	 */
	protected function dbFetchAllWithId($rs, $id = '')
	{
		if (!$rs) {
			$rs = $this->rs;
		}

		$rows = [];
		while ($row = $rs->fetchArray(SQLITE3_ASSOC)) {
			if ($id && isset($row[$id])) {
				$rows[$row[$id]] = $row;
			} else {
				$rows[reset($row)] = $row;
			}
		}

		$this->numRows = count($rows);

		return $rows;
	}

	/**
	 * Fetch all rows from the database storing data in an associative array
	 *
	 * @param SQLite3Result $rs
	 * @param string $id Name of field to use as ID. If left blank, the first field is used.
	 * @return array
	 */
	protected function dbFetchAllObjectWithId($rs, $id = '')
	{
		if (!$rs) {
			$rs = $this->rs;
		}

		$rows = [];
		while ($row = $rs->fetchArray(SQLITE3_ASSOC)) {
			if ($id && isset($row[$id])) {
				$rows[$row[$id]] = (object)$row;
			} else {
				$rows[reset($row)] = (object)$row;
			}
		}

		$this->numRows = count($rows);

		return $rows;
	}

	/**
	 * Get the ID from the last insert
	 *
	 * @return int
	 */
	public function insertId()
	{
		return $this->db->lastInsertRowID();
	}

	/**
	 * Get number of rows affected by last INSERT or UPDATE
	 *
	 * @return int
	 */
	public function affectedRows()
	{
		return $this->db->changes();
	}

	/**
	 * Get number of rows returned in last query
	 *
	 * @return int
	 */
	public function numRows()
	{
		return $this->numRows;
	}

	/**
	 * Determine if table exists in database
	 *
	 * @param string $table
	 * @param string $dbName Unused
	 * @return bool
	 */
	public function dbIsTable($table, $dbName = null)
	{
		$query = 'SELECT "name"
			FROM "sqlite_schema"
			WHERE "type"=\'table\'
				AND name=\'{$table}\'
			COLLATE NOCASE';
		return (bool)$this->db->querySingle($query);
	}

	/**
	 * Determine if field exists in table
	 *
	 * @param string $field
	 * @param string $table
	 * @param string $dbname Unused
	 * @return bool
	 */
	public function dbIsField($field, $table, $dbName = null)
	{
		$field = trim($field, '"\r\n\t ');
		$table = $this->dbTableName($table);

		$query = "PRAGMA table_info({$table})";
		$rows = $this->fetchAll($query);

		foreach ($rows as $row) {
			if ($row['name'] === $field) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Perform an Insert/Update operation based on a WHERE condition.
	 * Caution, this is subject to race conditions.
	 *
	 * @param string $table Name of table
	 * @param array $insert Data to insert
	 * @param array $update Data to update if insert fails
	 * @param mixed $where Unused in MySQL
	 */
	public function dbInsertUpdate($table, $insert, $update, $where)
	{
		$sql = "SELECT * FROM " . $this->dbTableName($table) . $this->whereSql($where);
		$row = $this->db->querySingle($sql);

		if ($row) {
			$this->update($table, $update, $where);
		} else {
			$this->insert($table, $insert);
		}
	}

	/**
	 * Perform an Insert/Update operation when there is a duplicate
	 * unique or primary key.
	 *
	 * @param string $table Name of table
	 * @param array $insert Data to insert
	 * @param array $update Data to update if insert fails
     * @param array $conflict List of columns to use to determine conflict. Ignored by MySQL
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

			$this->db->exec($sql);
		} else {
			try {
				$sql = $this->insertSql($table, $insert);
				$this->db->exec($sql);
			} catch (\Exception $ex) {
				$message = $ex->getMessage();

				// Super duper sketchy unique key conflict hack
				// There's got to be a better way.
				// Can other languages be used? If so, what are those errors?
				if (strpos($message, "UNIQUE") !== false) {
					$pos = strpos($message, ':');
					if ($pos > 0) {
						$conflict = [substr($message, $pos + 2)];
						$this->dbUpsert($table, $insert, $update, $conflict);
					} else {
						throw $ex;
					}
				} else {
					throw $ex;
				}
			}
		}
	}
}