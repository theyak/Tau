<?php

/**
 * This is an extremely simple SQL query builder. It can't do anything even
 * moderately complex. This doesn't even support JOINs, mostly because I wrote
 * this in a couple hours one evening in which I was bored. What's even crazier
 * is most of this functionality is already built into TauDb, but it's a bit
 * clunky. That being said, there are much better query builders out in the wild,
 * use those instead.
 *
 * // Get user with user_id 12
 * $sq = new TauDbQuery($db);
 * $user = $sq->table('users')->select('username')->where('user_id', '=', 12)->first();
 *
 * // Fetch all users with user_id less than 10 and created at "2022-01-01"
 * // Limiting to 2 users, skipping the first 5 users.
 * $sq = new TauDbQuery($db)
 *     ->table("users")
 *     ->select("first_name", "last_name")
 *     ->where("user_id", "<", 10)
 *     ->where("created_at", "2022-01-01")
 *     ->limit(2)
 *     ->offset(5)
 *     ->fetch();
 *
 * // Fetch all users with id <= 10 or user_id between 100 and 1000
 * $sq = new TauDbQuery($db)
 *     ->table("users")
 *     ->select("first_name", "last_name")
 *     ->where("user_id", "<=", 10)
 *     ->orWhere(function($t) {
 *         $t->where("user_id", ">=", 100);
 *         $t->where("user_id", "<=", 1000);
 *     })
 *     ->orderBy("user_id")
 *     ->fetch();
 *
 * // Get user ids and usernames for all ids <= 10
 * $sq = new TauDbQuery($db)
 *     ->table("users")
 *     ->select("user_id", "username")
 *     ->where("user_id", "<=", 10)
 *     ->pairs()
 *
 * // Get usernames for all ids <= 10
 * $sq = new TauDbQuery($db)
 *     ->table("users")
 *     ->select("username")
 *     ->where("user_id", "<=", 10)
 *     ->column()
 *
 * When using TauDB, you will often have a single global variable referencing the
 * TauDB object. The following example shows usage of a function to use the
 * query builder.
 *
 * function qb($table = null) {
 *   global $db;
 *   return new TauDbQuery($db, $table);
 * }
 *
 * qb('users')->select('username')->where('user_id', 12)->first();
 */

class TauDbQuery
{
    /**
     * The table
     *
     * @var string
     */
    public $table;

    /**
     * The columns which are returned in a select query
     *
     * @var array
     */
    public $fields = [];

    /**
     * The filtering in the query
     *
     * @var array
     */
    public $wheres = [];

    /**
     * Order by clause
     *
     * @var string[]
     */
    public $orderBys = [];

    /**
     * The limit to the number of records returned
     *
     * @var int
     */
    public $limit;

    /**
     * The offset, which is basically number of rows to skip
     *
     * @var int
     */
    public $offset;


    /**
     * The TauDB instance for the builder
     *
     * @var TauDb
     */
    public $db;

    /**
     * All of the available clause operators.
     * Ripped from Laravel which I just looked at for the first time.
     * Their code is really nice!
     *
     * @var string[]
     */
    public static $operators = [
        '=',
        '<',
        '>',
        '<=',
        '>=',
        '<>',
        '!=',
        '<=>',
        'like',
        'like binary',
        'not like',
        'ilike',
        '&',
        '|',
        '^',
        '<<',
        '>>',
        '&~',
        'is',
        'is not',
        'rlike',
        'not rlike',
        'regexp',
        'not regexp',
        '~',
        '~*',
        '!~',
        '!~*',
        'similar to',
        'not similar to',
        'not ilike',
        '~~*',
        '!~~*',
    ];

    /**
     * Create new query builder
     *
     * @param  TauDb $db
     */
    public function __construct($db = null, $table = null, $fields = null, $where = null)
    {
        if ($db) {
            $this->db($db);
        }

        if ($table) {
            $this->table($table);
        }

        if ($fields) {
            $this->select($fields);
        }

        if ($where) {
            $this->where($where);
        }
    }

    /**
     * Set TauDB object to use for queries
     *
     * @param  TauDb $db
     * @return $this
     */
    public function db($db)
    {
        $this->db = $db;
        return $this;
    }

    /**
     * Set table name to select from
     *
     * @param  string
     * @return $this
     */
    public function table($table)
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Insert data in database
     *
     * @param  array $data
     */
    public function insert($data)
    {
        $this->db->insert($this->table, $data);
    }

    /**
     * Update data in database
     *
     * @param  array $data
     */
    public function update($data)
    {
        $this->db->update($this->table, $data, $this->buildWhere($this->wheres));
    }

    /**
     * Perform an upsert operation to database
     *
     * @param  array $insert
     * @param  array $update
     */
    public function upsert($insert, $update = null)
    {
        $this->db->upsert($this->table, $insert, $update);
    }

    /**
     * Delete one or more rows from table
     */
    public function delete()
    {
        $sql = "DELETE FROM " . $this->db->tableName($this->table);
        if ($this->wheres) {
            $sql .= ' ' . $this->buildWhere($this->wheres);
        }
        $this->db->query($sql);
    }

    /**
     * Drop table
     */
    public function drop()
    {
        $sql = 'DROP TABLE ' . $this->db->tableName($this->table);
        $this->db->query($sql);
    }

    /**
     * Truncate table
     */
    public function truncate()
    {
        $sql = 'TRUNCATE TABLE ' . $this->db->tableName($this->table);
        $this->db->query($sql);
    }

    /**
     * Set field or fields to retrieve from table
     *
     * @param  array|string|TauSqlExpression $fields
     * @return $this
     */
    public function select(...$fields)
    {
        // Check if an array of fields, rather than multiple values, was passed.
        if (is_array($fields[0])) {
            $fields = $fields[0];
        }

        $this->fields = array_merge($this->fields, $fields);

        return $this;
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param  string|array|Closure $field
     * @param  mixed $comparison
     * @param  mixed $value
     * @return $this
     */
    public function where($field, $comparison = null, $value = null, $type = 'and')
    {
        if (is_string($comparison)) {
            $comparison = strtolower($comparison);
        }

        if ($field instanceof Closure) {
            $b = new static ();
            call_user_func_array($field, [&$b]);
            $this->wheres[] = [$type, $b];
            return $this;
        }

        // Allow short circuiting by defaulting the an "=", "is", or "in" comparison
        if (is_null($value) && !in_array($comparison, static::$operators)) {
            $value = $comparison;
            if ($value === null) {
                $comparison = "is";
            } else if (is_array($value)) {
                $comparison = "in";
            } else {
                $comparison = "=";
            }
        }

        if (!$this->wheres) {
            $type = 'where';
        }

        $this->wheres[] = [$type, compact("field", "comparison", "value")];

        return $this;
    }

    /**
     * @param string|Closure $callable
     */
    public function orWhere($callable, $comparison = null, $value = null)
    {
        if (is_string($callable)) {
            $this->where($callable, $comparison, $value, 'or');
        } else if ($callable instanceof Closure) {
            $this->where($callable, null, null, 'or');
        }
        return $this;
    }

    /**
     * Set ORDER BY condition
     *
     * @param  string|TauSqlExpression $field
     * @param  bool|"desc" $desc
     * @return $this
     */
    public function orderBy($field, $desc = false)
    {
        $this->orderBys[] = [$field, $desc === true || $desc === "desc"];
        return $this;
    }

    /**
     * Set the maximum number of rows to return
     *
     * @param  int $limit
     * @return $this
     */
    public function limit($limit)
    {
        $this->limit = (int) $limit;
        return $this;
    }

    /**
     * Set number of rows to skip before selecting rows
     *
     * @param  int $offset
     * @return $this
     */
    public function offset($offset)
    {
        $this->offset = (int) $offset;
        return $this;
    }

    /**
     * Fetch a single value from the database. Basically returns the first
     * field of the first row returned from a query.
     *
     * @return string
     */
    public function value()
    {
        $sql = $this->buildSelectQuery();
        return $this->db->fetchValue($sql);
    }

    /**
     * Fetch a column from a database SELECT query
     *
     * @return array
     */
    public function column()
    {
        $sql = $this->buildSelectQuery();
        return $this->db->fetchColumn($sql);
    }

    /**
     * Fetch pairs from the database. First value in result set is used as
     * array key and second value in result set is set as value.
     *
     * @return array
     */
    public function pairs()
    {
        $sql = $this->buildSelectQuery();
        return $this->db->fetchPairs($sql);
    }

    /**
     * Fetch first row from query
     *
     * @return stdClass|bool
     */
    public function first()
    {
        $sql = $this->buildSelectQuery();
        return $this->db->fetchOneObject($sql);
    }

    /**
     * Fetch all rows from query
     *
     * @param  bool|string $id Indicates that result should be indexed by ID instead of 0...n.
     *   Specify a string to indicate column name to index by. Specify true to index by first column.
     * @return stdClass[]
     */
    public function fetch($id = false)
    {
        $sql = $this->buildSelectQuery();

        if ($id) {
            if (is_string($id)) {
                $rows = $this->db->fetchAllWithId($sql, $id);
            } else {
                $rows = $this->db->fetchAllWithId($sql);
            }
        } else {
            $rows = $this->db->fetchAll($sql);
        }

        $rows = array_map(fn($row) => (object) $row, $rows);

        return $rows;
    }

    /**
     * Construct the WHERE clause
     *
     * @param  array $wheres
     * @return string
     */
    public function buildWhere($wheres)
    {
        $sql = '';

        if (!$wheres) {
            return $sql;
        }

        foreach ($wheres as $where) {
            if (isset($where[1]) && $where[1] instanceof static ) {
                $sql .= ' ' . $where[0] . ' (' . substr($this->buildWhere($where[1]->wheres), 6) . ')';
                continue;
            }

            $sql .= ' ' . strtoupper($where[0]) . ' ';
            $sql .= $this->db->fieldName($where[1]['field']);
            $sql .= ' ' . $where[1]['comparison'] . ' ';
            $sql .= $this->db->escape($where[1]['value']);
        }

        return $sql;
    }

    /**
     * Build the SQL query
     *
     * @return string
     */
    public function buildSelectQuery()
    {
        if (!$this->table) {
            throw new RuntimeException("Missing table");
        }

        if ($this->fields) {
            $fields = array_map(fn($f) => $this->db->fieldName($f), $this->fields);
            $fields = implode(", ", $fields);
        } else {
            $fields = "*";
        }

        $sql = "SELECT {$fields} FROM " . $this->db->tableName($this->table);

        if ($this->wheres) {
            $sql .= ' ' . $this->buildWhere($this->wheres);
        }

        if ($this->orderBys) {
            $sql .= ' ORDER BY ';

            $conditions = [];
            foreach ($this->orderBys as $orderBy) {
                $conditions[] = $this->db->fieldName($orderBy[0]) . ($orderBy[1] ? ' DESC' : '');
            }
            $sql .= implode(', ', $conditions);
        }

        if (is_int($this->limit)) {
            if (is_int($this->offset)) {
                $sql .= " LIMIT {$this->offset}, {$this->limit}";
            } else {
                $sql .= " LIMIT {$this->limit}";
            }
        }

        return $sql;
    }

    public function __toString()
    {
        try {
            return $this->buildSelectQuery();
        } catch (Exception $ex) {
            return $ex->getMessage();
        }
    }
}