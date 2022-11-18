<?php

namespace TauDatabase;

class TauDbTable
{
    public $name;
    public $alias;

    public function __construct($table, $alias = null)
    {
        if (is_null($alias)) {
            $pos = stripos($table, ' as ');
            if ($pos !== false) {
                $alias = substr($table, $pos + 4);
                $table = substr($table, 0, $pos);
            }
        }

        $this->name = trim($table);
        $this->alias = is_string($alias) ? trim($alias) : null;
    }
}