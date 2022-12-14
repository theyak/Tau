<?php

class TauPgsql extends TauDb
{
    /**
     * Reference to most recent result set
     * @var PDOStatement|false
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

    public function connect($retries = 0)
    {
        if ($this->server->connection) {
            return $this->server->connection;
        }

        $host = $this->server->host ?: 'localhost';
        $dbname = $this->server->database ?: 'postgres';
        $user = $this->server->username;
        $password = $this->server->password;
        $port = (int) ($this->server->port ?: 5432);

        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
        if ($user) {
            $dsn .= ";user={$user}";
        }
        if ($password) {
            $dsn .= ";password={$password}";
        }

        $this->server->connection = new PDO($dsn);

        return $this->server->connection;
    }

    /**
     * Use a particular database
     *
     * @param  string $database
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
            $this->server->connection = null;
        }
    }

    /**
     * Make a query, such as SELECT or EXPLAIN, to the database server
     *
     * @param  string $sql
     * @return PDOStatement|false
     */
    public function dbQuery($sql)
    {
        $connection = $this->connect();
        $this->query = $sql;
        $this->rs = $connection->query($sql);

        return $this->rs;
    }

    /**
     * Release a result set from memory
     *
     * @param PDOStatement $rs
     */
    public function dbFreeResult($rs = null)
    {
        if ($rs) {
            $rs->closeCursor();
        } else if ($this->rs) {
            $this->rs->closeCursor();
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
        if ($this->server->connection) {
            return ($this->server->connection->errorInfo())[2];
        }
        return "Not connected";
    }

    /**
     * Convert a PHP string into an SQL field name.
     *
     * @param  string|TauSqlExpression $fieldName
     * @return string
     */
    public function dbFieldName($fieldName)
    {
        $connection = $this->connect();

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
            $alias = $this->dbQuoteIdentifier($alias);
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
                $parts[$key] = $this->dbQuoteIdentifier($value);
            }
        }
        $field = implode(".", $parts);

        if ($alias) {
            return $field . ' as ' . $alias;
        }
        return $field;
    }

    /**
     * Convert a PHP string into an SQL identifer (table or column name)
     *
     * @param  string $value
     * @return string
     */
    public function dbQuoteIdentifier($value)
    {
        return '"' . $this->dbStringify($value, false) . '"';
    }

    /**
     * Convert a PHP string into an SQL table name
     *
     * @param  string $table_name
     * @return string
     */
    public function dbTableName($table_name)
    {
        return $this->dbQuoteIdentifier($table_name);
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
            return $connection->quote($unescaped_string);
        } else {
            return trim($connection->quote($unescaped_string), "'");
        }
    }

    /**
     * Encode a timestamp in any of the standard PHP formats accepted
     * by DateTime() and strtotime() to database format
     *
     * @param  DateTime|string $time
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
     * Escape a string for insertion in to database. This does not include quotes.
     *
     * @param string $unescaped_string
     * @return string
     */
    public function dbEscape($unescaped_string)
    {
        return $this->dbStringify($unescaped_string, false);
    }

    /**
     * Fetch a row from the database
     *
     * @param  PDOStatement $rs
     * @return array|false
     */
    protected function dbFetch($rs = null)
    {
        if ($rs) {
            return $rs->fetch(PDO::FETCH_ASSOC);
        } else {
            return $this->rs->fetch(PDO::FETCH_ASSOC);
        }
    }

    /**
     * Fetch a row from the database as on object
     *
     * @param  PDOStatement $rs
     * @return array|false
     */
    protected function dbFetchObject($rs = null)
    {
        if ($rs) {
            return $rs->fetch(PDO::FETCH_OBJ);
        } else {
            return $this->rs->fetch(PDO::FETCH_OBJ);
        }
    }

    /**
     * Fetch all rows from the database
     *
     * @param  PDOStatement $rs
     * @return array
     */
    protected function dbFetchAll($rs)
    {
        if (!$rs) {
            $rs = $this->rs;
        }

        return $rs->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieve all rows, each row as an object, from an SQL query
     *
     * @param  PDOStatement $rs
     * @param  string|boolean $id
     * @return array An array of records retrieved, each record as an object.
     */
    public function dbFetchAllObject($rs, $id = null)
    {
        if (!$rs) {
            $rs = $this->rs;
        }

        if ($id === false) {
            $rows = $rs->fetchAll(PDO::FETCH_CLASS);
            return $rows ? $rows : false;
        }

        $rows = [];
        while ($row = $this->dbFetch()) {
            if ($id === true || $id === "") {
                $rows[reset($row)] = (object) $row;
            } else if (isset($row[$id])) {
                $rows[$row[$id]] = (object) $row;
            } else {
                $rows[] = (object) $row;
            }
        }

        return $rows;
    }

    /**
     * Fetch all rows from the database storing data in an associative array.
     *
     * @param  PDOStatement $rs
     * @param  string|boolean $id Name of field to use as ID. If left blank or true, the first field is used.
     * @return array
     */
    public function dbFetchAllWithId($rs, $id = false)
    {
        if (!$rs) {
            $rs = $this->rs;
        }

        if ($id === false) {
            $rows = $rs->fetchAll(PDO::FETCH_CLASS);
            return $rows ? $rows : false;
        }

        $rows = [];
        while ($row = $this->dbFetch()) {
            if ($id === true || $id === "") {
                $rows[reset($row)] = $row;
            } else if (isset($row[$id])) {
                $rows[$row[$id]] = $row;
            } else {
                $rows[] = $row;
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
        return $this->rs->rowCount();
    }

    /**
     * Get number of rows returned in last query
     *
     * @param  PDOStatement $rs
     * @return int
     */
    public function numRows()
    {
        return $this->rs->rowCount();
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
     * @param  string $table Name of table
     * @param  array $insert Data to insert
     * @param  array $update Data to update if insert fails
     * @param  array $conflict List of columns to use to determine conflict
     */
    public function dbUpsert($table, $insert, $update, $conflict = [])
    {
        try {
            $this->insert($table, $insert);
        } catch (Exception $ex) {
            $key = false;
            $errstr = $ex->getMessage();
            $pos = strpos($errstr, 'DETAIL:  Key (');
            if ($pos !== false) {
                $key = substr($errstr, $pos + 14);
                $pos = strpos($key, ')');
                if ($pos !== false) {
                    $key = substr($key, 0, $pos);
                }
            }

            if ($key) {
                $key = $this->dbTableName($key);
                $values = [];
                foreach ($update as $fieldName => $value) {
                    $values[] = $this->fieldName($fieldName) . ' = ' . $this->escape($value);
                }

                $sql = $this->insertSql($table, $insert);
                $sql .= " ON CONFLICT({$key}) DO UPDATE SET " . implode(", ", $values);
                $this->query($sql);
            }
        }
    }
}