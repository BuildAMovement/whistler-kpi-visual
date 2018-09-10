<?php
namespace Model;

/**
 * 
 * @property \Component\User $user
 *
 */
abstract class Base extends \Ufw\Model\Base
{

    public function getDb() {
        return \Db\Db::instance();
    }
    
}