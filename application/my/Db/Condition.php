<?php
namespace Db;

class Condition extends \Ufw\Db\Condition
{
    public function __toString() {
        return $this->getField() . ' ' . $this->getOp() . ' (' . $this->getValueQuoted(\Db\Db::class) . ')';
    }
}