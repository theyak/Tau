<?php
/**
 * MySQL driver for TAU Database module
 *
 * @Author          theyak
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

class TauDbMysql
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
	
	
	function __construct($db, $dbuser, $dbpass, $dbname, $server = '127.0.0.1', $dbport = 3306)
	{
		$this->db = $db;

		@$this->dbLink = mysql_connect($server . ':' . $dbport, $dbuser, $dbpass, true);
		if ($this->dbLink)
		{
			if (@mysql_select_db($dbname))
			{
				return $this->dbLink;
			}
			TauError::fatal("Invalid database");
		}

		if ($server != '127.0.0.1' && $server != 'localhost')
		{
			$message = array(
				'Unable to connect to database. The database server is either down ',
				'or an invalid username and password combination was supplied.<br><br>',
				'You will need to grant access to the database for user ' . $dbuser,
				' with something like:<br><br>',
				'&nbsp;&nbsp;&nbsp;&nbsp;GRANT ALL ON ' . $dbname . '.* TO ' . $dbuser,
				'@\'%\' IDENTIFIED BY \'PASSWORD\'',
				'<br><br>Please see <a href="http://www.cyberciti.biz/tips/',
				'how-do-i-enable-remote-access-to-mysql-database-server.html">',
				'How Do I Enable Remote Access To MySQL Database Server?</a> ',
				'for more information.',
			);
			TauError::fatal(implode('', $message));
		} else {
			TauError::fatal("Unable to connect to database.");
		}
	}

	public function close()
	{
		mysql_close($this->dbLink);
		$this->dbLink = null;
	}

	public function select($sql, $ttl = 0)
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
				$resultSet = mysql_query($sql, $this->dbLink);	
				if ($resultSet) {
					$this->db->lastResultSet = $this->db->cache->querySave($this, $resultSet, $sql, $ttl);
					$this->freeResult($resultSet);
				}
			}
		}
		else
		{
			$this->db->lastResultSet = mysql_query($sql, $this->dbLink);		
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

		mysql_query($sql, $this->dbLink);
		
		$query['end'] = microtime(true);
		$query['time'] = $query['end'] - $query['start'];
		$query['cached'] = false;

		$this->db->queries[] = $query;
	}
	
	public function fetch($resultSet = null) {
		if ($resultSet == null) {
			$resultSet = $this->db->lastResultSet;
		}
				
		return @mysql_fetch_assoc($resultSet);
	}
	
	public function fetchAll($resultSet = null) {
		if ($resultSet == null) {
			$resultSet = $this->db->lastResultSet;
		}

		$results = array();
		while ($row = @mysql_fetch_assoc($resultSet)) {
			$results[] = $row;
		}
		return $results;
	}
	
	public function fetchAllObject($resultSet = null) {
		if ($resultSet == null) {
			$resultSet = $this->db->lastResultSet;
		}

		$results = array();
		while ($row = @mysql_fetch_assoc($resultSet)) {
			$results[] = $row;
		}
		return $results;
	}
	
	public function freeResult($resultSet = null) {
		if ($resultSet == null) {
			if ($this->db->lastResultSet != null) {
				mysql_free_result($this->lastResultSet);
				$this->db->lastResultSet = null;
			}
		} else {
			mysql_free_result($resultSet);
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

	public function insertId()
	{
		return mysql_insert_id();
	}

	public function affectedRows()
	{
		return mysql_affected_rows();
	}
	
	public function fieldName($field)
	{
		return $this->fieldTick . $field . $this->fieldTick;
	}
	
	public function now() {
		return 'NOW()';
	}

	public function nullValue() {
		return 'NULL';
	}
	
	public function stringify($value, $quote = true) {
		if ( $quote )
		{
			return mysql_real_escape_string((string)$value);
		}
		else
		{
			return $this->stringTick . mysql_real_escape_string((string)$value) . $this->stringTick;
		}
	}
	
	public function escape($value) {
		return $this->db->escape($value);
	}
	
	public function emptyField() {
		return $this->stringTick . $this->stringTick;
	}
	
	public function setCache($cache)
	{
		$this->cache = $cache;
	}
	
	/**
	 * Determine if table exists in database
	 * 
	 * @param string $table Name of table
	 * @param string $dbname Name of database
	 * @return boolean 
	 */
	public function isTable($table, $dbname)
	{
		$sql = 'SELECT table_name
			FROM information_schema.tables
			WHERE `table_schema` = ' . $this->stringify($dbname) . '
				AND table_name = ' . $this->strinify($table);
		$resultSet = $this->select($sql);
		if ($row = $this->fetch($resultSet)) {
			$this->freeResult($resultSet);
			return true;
		}
		return false;
	}
	
	/**
	 * Determine if a field/column exists in a database table
	 * 
	 * @param type $field Name of field or column
	 * @param type $table Name of table
	 * @param type $dbname Name of database
	 * @return type
	 */
	public function isField($field, $table, $dbname)
	{
		$sql = 'SHOW COLUMNS FROM ' . $this->fieldName($dbname) . '.' . $this->fieldName($table) . '
			WHERE `field` = ' . $this->stringify($field);
		$resultSet = $this->select($sql);
		if ($row = $this->fetch($resultSet)) {
			$this->freeResult($resultSet);
			return true;
		}
		return false;		
	}
	
	public function dropField($fieldName, $table, $dbname)
	{
		$sql = 'ALTER TABLE ' . $this->fieldName($dbname) . '.' . $this->fieldName($table) . '
			DROP COLUMN ' . $this->stringify($fieldName);
	}
	
	public function addField($column, $table, $dbname)
	{
		$sql = 'ALTER TABLE ' . $this->fieldName($dbname) . '.' . $this->fieldName($table) . '
			ADD ';
		// Generate column definition
		
	}

	public function addFieldFirst($column, $table, $dbname)
	{
		$sql = 'ALTER TABLE ' . $this->fieldName($dbname) . '.' . $this->fieldName($table) . '
			ADD ';
		// Generate column definition
		
	}
	
	public function addFieldAfter($column, $after, $table, $dbname)
	{
		$sql = 'ALTER TABLE ' . $this->fieldName($dbname) . '.' . $this->fieldName($table) . '
			ADD ';
		// Generate column definition
		
	}
	
	/**
	 *
	 * @param TauDbColumn $column 
	 */
	public function columnDefinitionToSql($column)
	{
		$definition = array();
		$type = strtoupper($column->type);
		
		switch ($type)
		{
			case 'BIT':
			case 'BINARY':
			case 'VARBINARY':
				$def = $type;
				if ($column->length) {
					$def .= '(' . $column->length . ')';
				} else if ($type == 'VARBINARY') {
					$def .= '(255)';
				}
				$definition[] = $def;
			break;
		
			case 'SMALLINT':
			case 'MEDIUMINT':
			case 'INT':
			case 'INTEGER':
			case 'BIGINT':
			case 'TINYINT':
				$def = $type;
				if ($column->length) {
					$def .= '(' . $column->length . ')';
				}
				$definition[] = $def;
				if ($column->unsigned) {
					$definition[] = 'UNSIGNED';
				}
				if ($column->zerofill) {
					$definition[] = 'ZEROFILL';
				}
			break;
		
			case 'REAL':
			case 'DOUBLE':
			case 'FLOAT':
				$def = $type;
				$def .= '(' . $column->length . ', ' . $column->decimals . ')';
				$definition[] = $def;
				if ($column->unsigned) {
					$definition[] = 'UNSIGNED';
				}
				if ($column->zerofill) {
					$definition[] = 'ZEROFILL';
				}
			break;
	
			case 'DECIMAL':
			case 'NUMERIC':
				$def = $type;
				if ($column->length) {
					$def .= '(' . $column->length;
					if ($column->decimals) {
						$def .= ', ' . $column->decimals;
					}
					$def .= ')';
				}
				$definition[] = $def;
				if ($column->unsigned) {
					$definition[] = 'UNSIGNED';
				}
				if ($column->zerofill) {
					$definition[] = 'ZEROFILL';
				}
			break;
						
			case 'CHAR':
			case 'VARCHAR':
				$def = $type;
				if ($column->length) {
					$def .= '(' . $column->length . ')';
				} else if ($type == 'VARCHAR') {
					$def .= '(255)';
				}
				$definition[] = $def;
				if ($column->charsetName) {
					$definition[] = 'CHARACTER SET ' . $column->charsetName;
				}
				if ($column->collationName) {
					$definition[] = 'COLLATE ' . $column->collationName;
				}
			break;
			
			case 'TINYTEXT':
			case 'TEXT':
			case 'MEDIUMTEXT':
			case 'LONGTEXT':
				$defintion[] = $type;
				if ($column->binary) {
					$definition[] = 'BINARY';
				}
				if ($column->charsetName) {
					$definition[] = 'CHARACTER SET ' . $column->charsetName;
				}
				if ($column->collationName) {
					$definition[] = 'COLLATE ' . $column->collationName;
				}
			break;
			
			case 'DATE':
			case 'TIME':
			case 'TIMESTAMP':
			case 'DATETIME':
			case 'YEAR':
			case 'TINYBLOB':
			case 'BLOB':
			case 'MEDIUMBLOB':
			case 'LONGBLOB':
				$defintion[] = $type;
			break;
		
			case 'ENUM':
			case 'SET':
				$definition[] = $type . '(' . implode(', ', array_map(array($this, 'escape'), $column->values)) . ')';
			break;
		}
		if (!sizeof($definition)) {
			$defintion[] = 'VARCHAR(255)';
		}

		$defintion[] = $column->null ? 'NULL' : 'NOT NULL';
		if (!is_null($column->default)) {
			$definition[] = 'DEFAULT ' . $this->escape($column->default);
		}
		if ($column->autoIncrement) {
			$definition[] = 'AUTO_INCREMENT';
		}
		if ($column->uniqueKey) {
			$definition[] = 'UNIQUE';
			if (is_string($column->uniqueKey)) {
				$definition[] = $column->uniqueKey;
			}
		} else if ($column->primaryKey) {
			$definition[] = 'PRIMARY';
			$definition[] = $column->primaryKey;
		}
		if ($column->comment) {
			$definition[] = 'COMMENT ' . $this->stringify($column->comment);
		}
		if ($column->columnFormat) {
			$definition[] = 'COLUMN_FORMAT ' . $column->columnFormat;
		}
		if ($column->storage) {
			$definition[] = 'STORAGE ' . $column->storage;
		}
		$dataType = implode(' ', $definition);
		return $dataType;
	}
}
