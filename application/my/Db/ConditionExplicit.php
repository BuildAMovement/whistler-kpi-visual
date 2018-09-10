<?php
namespace Db;

class ConditionExplicit extends \Ufw\Db\Condition
{

    protected $data = '';

    public function __construct($data = array())
    {
        $this->data = $data;
    }

    public function __toString()
    {
        return (string)$this->data;
    }
}