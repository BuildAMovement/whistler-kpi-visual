<?php
namespace Model\Record;

class Session extends Base
{
    protected static $primaryKeyField = 'session_key';
    
    protected static $tableName = 'django_session';

    public function behaviours()
    {
        return [
            'djs' => [
                'class' => \Model\Behaviour\DjangoSession::class,
                'serialAttributes' => array(
                    'session_data'
                )
            ]
        ];
    }
    
    public function isValid() {
        return !$this->hasExpired();
    }
    
    public function hasExpired() {
        return $this->expire_date < gmdate('Y-m-d H:i:s\.u\+\0\0', time());
    }
    
    public function getUserId() {
        $x = reset($this->session_data);
        return $x['_auth_user_id'];
    }
}
    