<?php

class TauDbQuery_Column {
    public $name;
    public $alias;
    public $function;
    public $raw = false;

    private $driver;

    public function __construct($driver, $field = null) {
        $this->driver = $driver;

        if ($field) {
            $this->parseFieldName($field);
        }
    }

    public function parseFieldName($field) {
        if ($field instanceof TauDbQuery_Column) {
            $this->name = $field->name;
            $this->alias = $field->alias;
            $this->function = $field->function;
            $this->raw = $field->raw;
        } else if ($field instanceof TauSqlExpression) {
            $this->name = $field->get();
            $this->alias = null;
            $this->function = null;
            $this->raw = true;
        } else if (is_string($field)) {
            $pos = stripos($field, " as ");
            if ($pos !== false) {
                $this->name = trim(substr($field, 0, $pos));
                $this->alias = trim(substr($field, $pos + 4));
            } else {
                $this->name = $field;
                $this->alias = null;
            }

            $this->raw = strpos($this->name, "(") !== false;
        }
    }

    public function __toString()
    {
        if ($this->raw) {
            $field = $this->name;
        } else {
            $field = $this->driver->dbFieldName($this->name);
        }

        if ($this->function) {
            $field = strtoupper($this->function) . "(" . $field . ")";
        }

        if ($this->alias) {
            $field .= " as " . $this->driver->dbFieldName($this->alias);
        }

        return $field;
    }
}