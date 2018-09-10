<?php
namespace Model;

class Submission extends Base
{

    protected $recordClassName = \Model\Record\Submission::class;

    public function read($where, $orderby = null, $limit = null)
    {
        $items = $this->getDb()->simple_select($this->getRecordClassName(), [
            'where' => $where,
            'orderby' => $orderby,
            'limit' => $limit
        ], \Ufw\InfoHash::class, 'id', 'id', 'default', __FILE__, __LINE__);
        
        return $items;
    }
}