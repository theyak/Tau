<?php

class TauDbQuery_Join
{
    public $type = "";
    public $table = null;
    public $ons = [];

    public function on($srcField, $operation, $destField, $or = false)
    {
        $this->ons[] = [$srcField, $operation, $destField, $or];
        return $this;
    }

    public function orOn($srcField, $operation, $destField)
    {
        $this->on($srcField, $operation, $destField, true);
        return $this;
    }
}