<?php
namespace Model;

class User extends Base
{

    protected $recordClassName = \Model\Record\User::class;

    public function read($where, $orderby = null, $limit = 50)
    {
        $items = $this->getDb()->simple_select($this->getRecordClassName(), [
            'where' => $where,
            'orderby' => $orderby,
            'limit' => $limit
        ], \Ufw\InfoHash::class, 'id', 'id_string', 'default', __FILE__, __LINE__);
        
        return $items;
    }

    public function one($id)
    {
        $whereClause = [
            'id' => $id
        ];
        $items = $this->getDb()->simple_select($this->getRecordClassName(), [
            'where' => $whereClause
        ], \Ufw\InfoHash::class, 'id', 'id', 'default', __FILE__, __LINE__);
        return is_array($id) ? $items : reset($items->info);
    }
}