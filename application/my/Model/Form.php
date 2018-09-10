<?php
namespace Model;

class Form extends Base
{

    protected $recordClassName = \Model\Record\Form::class;

    public function read($where, $orderby = null, $limit = 50)
    {
        $items = $this->getDb()->simple_select($this->getRecordClassName(), [
            'where' => $where,
            'orderby' => $orderby,
            'limit' => $limit
        ], \Ufw\InfoHash::class, 'id', 'id_string', 'default', __FILE__, __LINE__);
        
        return $items;
    }
}