<?php
namespace Model;

class Session extends Base
{

    protected $recordClassName = \Model\Record\Session::class;

    public function one($id)
    {
        $whereClause = [
            'session_key' => $id,
        ];
        $items = $this->getDb()->simple_select($this->getRecordClassName(), [
            'where' => $whereClause
        ], \Ufw\InfoHash::class, 'session_key', 'session_key', 'default', __FILE__, __LINE__);
        return is_array($id) ? $items : reset($items->info);
    }
}