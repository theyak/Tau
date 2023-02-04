<?php

class TauDbQuery_Table {
    public $database;
    public $name;
    public $alias;

    public $schema;

    private $driver;

    public function __construct($driver, $table = null) {
        $this->driver = $driver;

        if ($table) {
            $this->parseTableName($table);
        }
    }

    public function parseTableName($field) {
        if (is_array($field)) {
            foreach ($field as $alias => $name) {
                $field = $name . " as " . $alias;
                break;
            }
        }

        if ($field instanceof TauDbQuery_Table) {
            $this->database = $field->database;
            $this->name = $field->name;
            $this->alias = $field->alias;
        } else if ($field instanceof TauSqlExpression) {
            $this->name = $field->get();
            $this->alias = null;
        } else if (is_string($field)) {
            $pos = stripos($field, " as ");
            if ($pos !== false) {
                $this->name = trim(substr($field, 0, $pos));
                $this->alias = trim(substr($field, $pos + 4));
            } else {
                $this->name = $field;
                $this->alias = null;
            }

            $pos = strpos($this->name, ".");
            if ($pos !== false) {
                $split = explode(".", $this->name, 2);
                $this->database = trim($split[0]);
                $this->name = trim($split[1]);
            }
        }

        if ($this->database) {
            $this->schema = $this->database . "." . $this->name;
        } else {
            $this->schema = $this->name;
        }
    }

    public function insert($data)
    {
        $this->driver->insert($this->schema, $data);
    }

    public function update($update, $where)
    {
        $this->driver->update($this->schema, $update, $where);
    }

    public function upsert($insert, $update, $conflict)
    {
        $this->driver->upsert($this->schema, $insert, $update, $conflict);
    }

    public function drop()
    {
        $sql = 'DROP TABLE ' . $this->driver->tableName($this->schema);
        $this->driver->query($sql);
    }

    public function truncate()
    {
        $sql = 'TRUNCATE TABLE ' . $this->driver->tableName($this->schema);
        $this->driver->query($sql);
    }

    /**
     * Check if table exists
     *
     * @return boolean
     */
    public function exists() {
        return $this->driver->isTable($this->name, $this->database);
    }

    /**
     * Check if table has column.
     *
     * @param mixed $column
     * @return bool
     */
    public function hasColumn($column)
    {
        if ($column instanceof TauDbQuery_Column) {
            return $this->driver->isField($column->name, $this->name, $this->database);
        } else if (is_string($column)) {
            return $this->driver->isField($column, $this->name, $this->database);
        }
        return false;
    }

    public function __toString()
    {
        $table = $this->driver->dbTableName($this->name);

        if ($this->alias) {
            $table .= " as " . $this->driver->dbTableName($this->alias);
        }

        return $table;
    }
}