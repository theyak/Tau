<?php

use TauDatabase\TauDbJoin;

/**
 * This is an extremely simple SQL query builder. It can't do anything even
 * moderately complex, mostly because I wrote this in a couple hours one evening
 * in which I was bored. What's even crazier is most of this functionality is
 * already built into TauDb, but it's a bit clunky. That being said, there are
 * much better query builders out in the wild, use those instead.
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
 *     ->order("user_id")
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
 * // You can also cast to objects and use the TauDb cache
 * $sq = new TauDbQuery($db)
 *     ->table("users")
 *     ->select("first_name", "last_name")
 *     ->where("user_id", "<=", 100)
 *     ->order("user_id")
 *     ->cast(User::class)
 *     ->ttl(30)
 *     ->fetch();
 *
 * When using TauDb, you will often have a single global variable referencing the
 * TauDb object. The following example shows usage of a function to use the
 * query builder.
 *
 * function db($table = null) {
 *   global $db;
 *   return new TauDbQuery($db, $table);
 * }
 *
 * db('users')->select('username')->where('user_id', 12)->first();
 */

class TauDbQuery
{
    /**
     * The table name
     *
     * @var string
     */
    public $tableName;

    /**
     * Table alias
     *
     * @var string
     */
    public $tableAlias;

    /**
     * The columns which are returned in a select query
     *
     * @var array
     */
    public $fields = [];

    /**
     * Joins for query
     *
     * @var TauDbJoin[]
     */
    public $joins = [];

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
     * Group by clause
     *
     * @var string[]
     */
    public $groupBys = [];

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
     * Raw SQL query
     *
     * @var string
     */
    public $raw;

    /**
     * ID column
     *
     * @var string
     */
    public $id;

    /**
     * The TauDb instance for the builder
     *
     * @var TauDb
     */
    public $db;

    /**
     * Class name to cast results to
     *
     * @var string
     */
    public $cast;

    /**
     * Whether to cast all properties or just those that exist in destination class
     *
     * @var bool
     */
    public $allProperties = false;

    /**
     * Time to live for cache
     */
    public $ttl = 0;

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
     * Cast an object of one type (usually stdclass) to another via shallow copy.
     *
     *     ->cast(User::class)
     *
     * A best guess of property type is made based on doc blocks or defined type
     * or docblock. Docblock example can't be added as it terminates this
     * comment block, but it's your standard * @var int block.
     *
     * class User {
     *     public int $user_id;
     *     public string $username;
     * }
     *
     * You can also specify a table name via class attributes in PHP 8.0+
     *
     * #[dbtable("users")]
     * class User {
     *     public int $user_id;
     *     public string $username;
     * }
     *
     * @param  string $class Name of class
     * @param  bool $allPropertiees Whether to cast all properties from source or just those
     *              that are defined in the class.
     * @return $this
     */
    function cast($class, $allProperties = false)
    {
        $this->cast = $class;
        $this->allProperties = $allProperties;

        return $this;
    }

    /**
     * Time to live if cache is in use in TauDb object
     *
     * @param  int $ttl Time to live, in seconds
     * @return $this
     */
    function ttl($ttl)
    {
        $this->ttl = (int) $ttl;
        return $this;
    }

    /**
     * Set TauDb object to use for queries
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
     *     ->table("users");
     *     ->table("users", "u");
     *
     * @param  string $table Table name
     * @param  string $alias optional alias for table
     * @return $this
     */
    public function table($table, $alias = null)
    {
        if (is_null($alias)) {
            $pos = stripos($table, ' as ');
            if ($pos !== false) {
                $alias = substr($table, $pos + 4);
                $table = substr($table, 0, $pos);
            }
        }

        $this->tableName = trim($table);
        $this->tableAlias = is_string($alias) ? trim($alias) : null;

        return $this;
    }

    /**
     * Insert data in database
     *
     *     ->insert(["username" => "bob", "email" => "bob@bob.com"])
     *
     * @param  array $data
     */
    public function insert($data)
    {
        $this->db->insert($this->tableName, $data);
    }

    /**
     * Update data in database
     *
     *     ->where("username", "bob")->update(["email" => "bob@bob.com"])
     *
     * @param  array $data
     */
    public function update($data)
    {
        $this->db->update($this->tableName, $data, $this->buildWhere($this->wheres));
    }

    /**
     * Perform an upsert operation to database
     *
     *     ->upsert(['username' => 'bob', 'email' => 'bob@bob.com'], ['email' => 'bob@bob.com])
     *
     * @param  array $insert
     * @param  array $update
     * @param  array $conflict List of columns to use to determine conflict. Ignored by MySQL.
     */
    public function upsert($insert, $update = null, $conflict = [])
    {
        $this->db->upsert($this->tableName, $insert, $update, $conflict);
    }

    /**
     * Delete one or more rows from table
     *
     *     ->delete()
     */
    public function delete()
    {
        $sql = "DELETE FROM " . $this->db->tableName($this->tableName);
        if ($this->wheres) {
            $sql .= ' ' . $this->buildWhere($this->wheres);
        }
        $this->db->query($sql);
    }

    /**
     * Drop table
     *
     *     ->drop
     */
    public function drop()
    {
        $sql = 'DROP TABLE ' . $this->db->tableName($this->tableName);
        $this->db->query($sql);
    }

    /**
     * Truncate table
     *
     *     ->truncate()
     */
    public function truncate()
    {
        $sql = 'TRUNCATE TABLE ' . $this->db->tableName($this->tableName);
        $this->db->query($sql);
    }

    /**
     * Set field or fields to retrieve from table
     *
     *     ->select("user_id", "username", "email")
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
     * Add a join statement to the query
     *
     *     ->join('posts', 'users.id', '=', 'posts.user_id')
     *
     * @param  string $table The table to join - As a string, it can contain an "as" alias
     * @param  string|Closure $srcField Field to join on or a closure with one or more "on()" clauses.
     * @param  string $operator The operator to compare fields against
     * @param  string $destField Field to comapare with
     * @param  string $type The type of join. One of left, right, inner, outer. Defaults to LEFT
     * @return $this
     */
    public function join($table, $srcField, $operator = null, $destField = null, $type = "LEFT")
    {
        $join = new TauDbJoin();
        $join->type = strtoupper($type);
        $join->table = $table;

        if ($srcField instanceof Closure) {
            // The $operator parameter may actually hold the $type
            $op = strtoupper($operator);
            if (in_array($op, ["LEFT", "RIGHT", "INNER", "OUTER"])) {
                $join->type = $op;
            }
            call_user_func_array($srcField, [&$join]);
        } else {
            $join->ons[] = [$srcField, $operator, $destField, false];
        }
        $this->joins[] = $join;

        return $this;
    }

    /**
     * Add a left join statement to the query
     *
     *     ->leftJoin('posts', 'users.id', '=', 'posts.user_id')
     *
     * @param  string $table The table to join - As a string, it can contain an "as" alias
     * @param  string|Closure $srcField Field to join on or a closure with one or more "on()" clauses.
     * @param  string $operator The operator to compare fields against
     * @param  string $destField Field to comapare with
     * @return $this
     */
    public function leftJoin($table, $srcField, $operator, $destField)
    {
        return $this->join($table, $srcField, $operator, $destField, "LEFT");
    }

    /**
     * Add a right join statement to the query
     *
     *     ->rightJoin('posts', 'users.id', '=', 'posts.user_id')
     *
     * @param  string $table The table to join - As a string, it can contain an "as" alias
     * @param  string|Closure $srcField Field to join on or a closure with one or more "on()" clauses.
     * @param  string $operator The operator to compare fields against
     * @param  string $destField Field to comapare with
     * @return $this
     */
    public function rightJoin($table, $srcField, $operator, $destField)
    {
        return $this->join($table, $srcField, $operator, $destField, "RIGHT");
    }

    /**
     * Add an inner join statement to the query
     *
     *     ->innerJoin('posts', 'users.id', '=', 'posts.user_id')
     *
     * @param  string $table The table to join - As a string, it can contain an "as" alias
     * @param  string|Closure $srcField Field to join on or a closure with one or more "on()" clauses.
     * @param  string $operator The operator to compare fields against
     * @param  string $destField Field to comapare with
     * @return $this
     */
    public function innerJoin($table, $srcField, $operator, $destField)
    {
        return $this->join($table, $srcField, $operator, $destField, "INNER");
    }

    /**
     * Add an outer join statement to the query
     *
     *     ->outerJoin('posts', 'users.id', '=', 'posts.user_id')
     *
     * @param  string $table The table to join - As a string, it can contain an "as" alias
     * @param  string|Closure $srcField Field to join on or a closure with one or more "on()" clauses.
     * @param  string $operator The operator to compare fields against
     * @param  string $destField Field to comapare with
     * @return $this
     */
    public function outerJoin($table, $srcField, $operator, $destField)
    {
        return $this->join($table, $srcField, $operator, $destField, "OUTER");
    }

    /**
     * Add a basic where clause to the query. This can take parameters in
     * a variety of formats, such as:
     *
     *     ->where(["username" => "bob", "email" => "bob@bob.com"])
     *     ->where("username", "bob")->where("email", "bob@bob.com")
     *     ->where("username", ["John", "Paul", "George", "Ringo"])
     *     ->where("user_id", "<", 10)
     *     ->where("username", "like", "bob%")
     *     ->where("deleted_at", null)
     *     ->where("creatad_at", ">", new DateTime("-1 week"))
     *     ->where(function($qb) {
     *         $qb->where("username", "bob");
     *         $qb->orWhere("email", "bob@bob.com");
     *     })
     *
     * @param  string|array|Closure $field
     * @param  mixed $operation
     * @param  mixed $value
     * @return $this
     */
    public function where($field, $operation = null, $value = null, $type = "and")
    {
        if (is_string($operation)) {
            $operation = strtolower($operation);
        }

        // A key/value array is often used as a shortcut for multiple
        // where() calls. This may look like ["col1" => $val1, "col2" => $val2]
        if (is_array($field)) {
            if (in_array($operation, ["where", "and", "or"])) {
                $this->whereArray($field, $operation);
            } else {
                $this->whereArray($field, $type);
            }
            return $this;
        }

        if ($field instanceof Closure) {
            if (in_array($operation, ["and", "or", "where"])) {
                $type = $operation;
            }
            $b = new self();
            call_user_func_array($field, [&$b]);
            $this->wheres[] = [$type, $b];
            return $this;
        }

        // Allow short circuiting by defaulting the an "=", "is", or "in" comparison
        if (is_null($value) && !in_array($operation, static::$operators)) {
            $value = $operation;
            if ($value === null) {
                $operation = "is";
            } else if (is_array($value)) {
                $operation = "in";
            } else {
                $operation = "=";
            }
        }

        if (!$this->wheres) {
            $type = 'where';
        }

        $this->wheres[] = [$type, compact("field", "operation", "value")];

        return $this;
    }

    /**
     * Add a basic where clause to the query based on a key/value array.
     *
     *     ->whereArray(["username" => "bob", "email" => "bob@bob.com"])
     *     ->whereArray(["username" => "bob", "email" => "bob@bob.com"], "or")
     *
     * @param  array $keyval
     * @param  string $type One of "and" or "or"
     * @return $this
     */
    public function whereArray($keyval, $type = "and")
    {
        $b = new self();
        foreach ($keyval as $key => $value) {
            $b->where($key, "=", $value);
        }

        if (!$this->wheres) {
            $type = 'where';
        }
        $this->wheres[] = [$type, $b];

        return $this;
    }

    /**
     * Adds a WHERE condition that should be OR'd with the previous condition
     *
     * username = "bob" OR email = "bob@bob.com"
     *
     *     ->where("username", "bob")->orWhere("email", "bob@bob.com")
     *
     * user_id = 10 OR (user_id = 12 AND subject like "%help%"):
     *
     *     ->where("user_id", 10")->orWhere(function($qb) {
     *         $qb->where("user_id", 12);
     *         $qb->where("subject", "like", "%help%");
     *     })
     *
     * @param  string|array|Closure $field
     * @param  mixed $operation
     * @param  mixed $value
     * @return $this
     */
    public function orWhere($callable, $operation = null, $value = null)
    {
        if (is_string($callable)) {
            $this->where($callable, $operation, $value, 'or');
        } else if ($callable instanceof Closure) {
            $this->where($callable, null, null, 'or');
        }
        return $this;
    }

    /**
     * Set GROUP BY condition
     *
     *    ->group("age")
     *
     * @param  string|TauSqlExpression $field
     * @return $this
     */
    public function group($field)
    {
        $this->groupBys[] = $field;
        return $this;
    }

    /**
     * Set ORDER BY condition
     *
     *    ->order("username")
     *
     * Order descending:
     *
     *    ->order("username", true)
     *    ->order("username", "desc")
     *
     * @param  string|TauSqlExpression $field
     * @param  bool|"desc" $desc
     * @return $this
     */
    public function order($field, $desc = false)
    {
        $this->orderBys[] = [$field, $desc === true || $desc === "desc"];
        return $this;
    }

    /**
     * Set the maximum number of rows to return
     *
     *     ->limit(10)
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
     *     ->offset(20)
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
     * Sets the name of the ID column. This column will be used to search
     * with the `find()` method and set the array indexes for `pluck()`,
     * `fetch()` and `findAll()`.
     *
     * Use first column (pluck, fetch, and findAll ony):
     *
     *     ->id()
     *
     * Use specified column:
     *
     *     ->id("username")
     *
     * @param  string|true Name of ID column. Can use `true` to assume first column in fetch() and findAll() calls.
     * @return $this
     */
    public function id($id = true)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * For those brave, set a raw SQL query. Overrides everything else!
     * There is very very dumb logic for escaping named parameters.
     * It's just a str_replace. There is no tokenizing to make sure
     * the same key doesn't exist in a string value or in multiple
     * places in your your query. Use at your own risk.
     *
     * Raw query:
     *
     *     ->raw('SELECT username FROM users WHERE user_id = 12')->first();
     *
     * Raw query with parameters:
     *
     *     ->raw('SELECT username FROM users WHERE user_id < :userId', [':userId' => 12])->fetchAll();
     *
     * @param  string $sql The raw query string
     * @param  array $params Named parameters
     * @return $this
     */
    public function raw($sql, $params = [])
    {
        foreach ($params as $key => $value) {
            $sql = str_replace($key, $this->db->escape($value), $sql);
        }

        $this->raw = $sql;
        return $this;
    }

    /**
     * Fetch a single value from the database. Basically returns the first
     * field of the first row returned from a query.
     *
     *    ->value()
     *
     * @return string
     */
    public function value()
    {
        $sql = $this->buildSelectQuery();
        return $this->db->fetchValue($sql, $this->ttl);
    }

    /**
     * Pluck the values of a column, or key/value pairs
     *
     * Fetch a single column:
     *
     *     ->pluck("username")
     *
     * Fetch key value pairs by specifying ID
     *
     *     ->id("username")->pluck("email")
     *
     * Fetch key value pairs via $key parameter
     *
     *     ->pluck("email", "username")
     *
     * @param  string $column
     * @param  string $key If provided, uses column as a key to result set
     * @return array
     */
    public function pluck($column, $key = null)
    {
        if ($key) {
            $this->fields = [$key, $column];
            return $this->pairs();
        } else if ($this->id) {
            $id = is_string($this->id) ? $this->id : "id";
            $this->fields = [$id, $column];
            return $this->pairs();
        } else {
            $this->fields = [$column];
            return $this->column();
        }
    }

    /**
     * Find a row based on an ID or single equality condition.
     * There are several ways to use this function.
     *
     * 1. Pass a single value, which will search the table based on the "id" column
     *
     *     ->table("users")->find(3)
     *     ->table("users")->id("user_id")->find(3)
     *
     * 2. Pass in two values, the first of which is the name of the column to search and the second is the value
     *
     *     ->table("user_id", 3)
     *
     * 3. Parameters matching a call to ->where()
     *
     *     ->table("user_id", "=", 100)
     *
     * @param  mixed $id Column name, ID value, or where expression
     * @param  mixed If provided, the value to search
     * @return stdClass|boolean false if no row found
     */
    public function find()
    {
        $this->wheres = [];
        $args = func_get_args();
        if (func_num_args() === 1) {
            if (is_array($args[0])) {
                return $this->where(...$args[0])->first();
            } else {
                $id = is_string($this->id) ? $this->id : "id";
                return $this->where($id, "=", $args[0])->first();
            }
        } else if (func_num_args() >= 2) {
            return $this->where(...$args)->first();
        }

        return false;
    }

    /**
     * Find a rows based on an ID or single equality condition.
     * There are a couple ways to use this function.
     *
     * 1. Pass in two values, the first of which is the name of the column to search and the second is the value
     *
     *    ->findAll("user_type", "admin")
     *
     * 2. Parameters matching ->where()
     *
     *    ->findAll("user_type", "in", ["staff", "admin"])
     *
     * You can specify the column to use for the index of each row in the array with ->id()
     *
     *    ->id("username")->findAll("user_type", "in", ["staff", "admin"])
     *
     * Or just use the first column of the result
     *
     *    ->id()->findAll("user_type", "in", ["staff", "admin"])
     *
     * @param  mixed $id Column name, ID value, or where expression
     * @param  mixed If provided, the value to search
     * @return stdClass[] false if no row found
     */
    public function findAll()
    {
        $this->wheres = [];
        $args = func_get_args();
        if (func_num_args() === 1) {
            if (is_array($args[0])) {
                return $this->where(...$args[0])->fetch();
            } else {
                $id = is_string($this->id) ? $this->id : "id";
                return $this->where($id, "=", $args[0])->fetch();
            }
        } else if (func_num_args() >= 2) {
            return $this->where(...$args)->fetch();
        }

        return [];
    }

    /**
     * Fetch a column from a database SELECT query
     *
     *     ->select("username")->column()
     *
     * @return array
     */
    public function column()
    {
        $sql = $this->buildSelectQuery();
        $this->reset();
        return $this->db->fetchColumn($sql, $this->ttl);
    }

    /**
     * Fetch key value pairs from the database. First value in result set is
     * used as array key and second value in result set is set as value.
     *
     *     ->select("username", "email")->pairs()
     *
     * @return array
     */
    public function pairs()
    {
        $sql = $this->buildSelectQuery();
        $this->reset();
        return $this->db->fetchPairs($sql, $this->ttl);
    }

    /**
     * Fetch first row from query
     *
     *     ->where("user_id", 10)->first()
     *
     * @return stdClass|bool
     */
    public function first()
    {
        $sql = $this->buildSelectQuery();
        $this->reset();
        $row = $this->db->fetchOneObject($sql, $this->ttl);

        if ($this->cast) {
            $row = $this->doCast($this->cast, $row, $this->allProperties);
        }

        return $row;
    }

    /**
     * Fetch all rows from query
     *
     *     ->where("created_at", ">", new DateTime("-1 month"))->fetch()
     *
     * The return result can be indexed by the first column in each row
     *
     *     ->where("created_at", ">", new DateTime("-1 month"))->fetch(true)
     *     ->id(true)->where("created_at", ">", new DateTime("-1 month"))->fetch()
     *
     * Thre return result can be indexed by a specified column
     *
     *     ->where("created_at", ">", new DateTime("-1 month"))->fetch("email")
     *     ->id("email")->where("created_at", ">", new DateTime("-1 month"))->fetch()
     *
     * @param  bool|string $id Indicates that result should be indexed by ID instead of 0...n.
     *   Specify a string to indicate column name to index by. Specify true to index by first column.
     * @return stdClass[]
     */
    public function fetch($id = false)
    {
        $sql = $this->buildSelectQuery();
        $this->reset();

        if ($this->id) {
            $id = is_string($this->id) ? $this->id : "";
            $rows = $this->db->fetchAllWithId($sql, $id, $this->ttl);
        } else if ($id) {
            $id = is_string($id) ? $id : "";
            $rows = $this->db->fetchAllWithId($sql, $id, $this->ttl);
        } else {
            $rows = $this->db->fetchAll($sql, $this->ttl);
        }

        $rows = array_map(fn($row) => (object) $row, $rows);

        if ($this->cast) {
            $rows = array_map(fn($row) => $this->doCast($this->cast, $row, $this->allProperties), $rows);
        }

        return $rows;
    }

    /**
     * Execute a raw query. Be very very careful with this. It's really
     * not something you should be doing with a query builder, but some
     * people insist. Very limited and dumb support for escaping named
     * parameters. There is no tokenization, so if the token exists
     * anywhere in the query, it will be replaced.
     *
     *     ->exec(
     *         'UPDATE users SET username = :username WHERE user_id = :userId',
     *         [
     *             ":username" => "Johnny",
     *             ":userId" => 12
     *         ]
     *     )
     * @param  string $sql The raw query string
     * @param  array $params Named parameters
     * @return void
     */
    public function exec($sql, $params)
    {
        $this->raw($sql, $params);
        $this->db->query($this->raw);
    }

    /**
     * Cast an object of one type (usually stdclass) to another via shallow copy.
     *
     *     ->cast(User::class)
     *
     * A best guess of property type is made based on doc blocks or defined type.
     *
     * class User {
     *     public int $user_id;
     *     public string $username;
     * }
     *
     * class User {
     *     /**
     *      * @var int
     *      *\ <-- Eeps, * / closes this docblock, so can't show correctly
     *     public $user_id;
     *
     *     /**
     *      * @var string
     *      *\ <-- Eeps, * / closes this docblock, so can't show correctly
     *     public $username;
     * }
     *
     * You can also specify a table name via class attributes in PHP 8.0+
     *
     * #[dbtable("users")]
     * class User {
     *     public int $user_id;
     *     public string $username;
     * }
     *
     * @param  string Class name for destination class
     * @param  object Source class to cast from
     * @param  bool   Whether to copy all properties, regardless if they've been defined in class
     * @return object Casted object
     */
    function doCast($dest, $source, $allProperties = false)
    {
        $cast = new $dest;

        $ref = new ReflectionClass($cast);

        // Get source properties and type
        $properties = [];
        foreach ($ref->getProperties() as $property) {
            $type = $property->getType();
            if ($type) {
                $type = $type->getName();
            }
            if (!$type && preg_match('/@var\s+([^\s]+)/', $property->getDocComment(), $matches)) {
                list(, $type) = $matches;
            }
            if (!$type && is_int($cast->{$property->name})) {
                $type = 'int';
            }
            if (!$type && is_bool($cast->{$property->name})) {
                $type = 'bool';
            }
            $properties[$property->name] = $type ?? 'string';
        }

        foreach ($source as $field => $data) {
            if (isset($properties[$field])) {
                $type = $properties[$field];
                if ($type === 'int') {
                    $cast->$field = (int) $data;
                } else if ($type === 'bool' || $type === 'boolean') {
                    $cast->$field = (bool) $data;
                } else if ($type === 'float') {
                    $cast->$field = (float) $data;
                } else {
                    $cast->$field = $data;
                }
            } else if ($allProperties) {
                $cast->$field = $data;
            }
        }

        return $cast;
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
            if (isset($where[1]) && $where[1] instanceof self) {
                $sql .= ' ' . strtoupper($where[0]) . ' (' . substr($this->buildWhere($where[1]->wheres), 7) . ')';
                continue;
            }

            $sql .= ' ' . strtoupper($where[0]) . ' ';
            $sql .= $this->db->fieldName($where[1]['field']);
            $sql .= ' ' . strtoupper($where[1]['operation']) . ' ';
            $sql .= $this->db->escape($where[1]['value']);
        }

        return $sql;
    }

    /**
     * Get table name to use in a query. This is generally specified
     * via the table() method but may also be specified by a
     * #[dbtable('table_name')] attribute in a casted class.
     *
     * @param  string|null $table
     * @return string|bool
     */
    public function getTable($name = null, $alias = null)
    {
        if (is_null($alias)) {
            $pos = stripos($name, ' as ');
            if ($pos !== false) {
                $alias = substr($name, $pos + 4);
                $name = substr($name, 0, $pos);
            }
        }

        if ($name) {
            $t = $this->db->tableName($name);
            if ($alias) {
                $t .= " as " . $this->db->tableName($alias);
            }
            return $t;
        }

        if (!$this->cast) {
            return false;
        }

        if (PHP_VERSION_ID > 80000) {
            $ref = new ReflectionClass($this->cast);
            $attributes = $ref->getAttributes();
            foreach ($attributes as $attr) {
                if ($attr->getName() === 'dbtable') {
                    return $this->db->tableName($attr->getArguments()[0]);
                }
            }
        }

        return false;
    }

    /**
     * Construct JOIN clauses
     *
     * @param  TauDbJoin[] $joins
     * @return string
     */
    public function buildJoins($joins)
    {
        $sql = "";
        foreach ($joins as $join) {
            $sql .= " " . $join->type . ' JOIN ';
            $sql .= $this->getTable($join->table);
            foreach ($join->ons as $idx => $on) {
                $prefix = " ON ";
                if ($idx > 0) {
                    $prefix = $on[3] ? " OR " : " AND ";
                }
                $sql .= $prefix . $this->db->fieldName($on[0]) . ' ' . $on[1] . ' ' . $this->db->fieldName($on[2]);
            }
        }
        return " " . trim($sql);
    }

    /**
     * Construct a list of columns for query
     *
     * @param string|string[] List of columns
     * @return string
     */
    public function columns($columns)
    {
        if (is_string($columns)) {
            $columns = [$columns];
        }

        $fields = array_map(fn($f) => $this->db->fieldName($f), $columns);
        $fields = implode(", ", $fields);

        return $fields;
    }

    /**
     * Build the SQL query
     *
     * @return string
     */
    public function buildSelectQuery()
    {
        // Oh dear.
        if ($this->raw) {
            return $this->raw;
        }

        $table = $this->getTable($this->tableName, $this->tableAlias);
        if (!$table) {
            throw new RuntimeException("Missing table");
        }

        if ($this->fields) {
            $fields = $this->columns($this->fields);
        } else {
            $fields = "*";
        }

        $sql = "SELECT {$fields} FROM {$table}";

        if ($this->joins) {
            $sql .= $this->buildJoins($this->joins);
        }

        if ($this->wheres) {
            $sql .= $this->buildWhere($this->wheres);
        }

        if ($this->groupBys) {
            $sql .= ' GROUP BY ' . $this->columns($this->groupBys);
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

    /**
     * Reset parameters after a select query
     */
    public function reset()
    {
        $this->fields = [];
        $this->wheres = [];
        $this->joins = [];
        $this->orderBys = [];
        $this->groupBys = [];
        $this->limit = null;
        $this->offset = null;
    }

    /**
     * Return the query that would be run as a string
     */
    public function __toString()
    {
        try {
            return $this->buildSelectQuery();
        } catch (Exception $ex) {
            return $ex->getMessage();
        }
    }
}