<?php
namespace Model\Record;

class User extends Base
{

    protected static $tableName = 'auth_user';

    public function behaviours()
    {
        return [
            'pgb' => [
                'class' => \Model\Behaviour\PGBoolean::class,
                'serialAttributes' => array(
                    'is_superuser',
                    'is_staff',
                    'is_active'
                )
            ]
        ];
    }
}
    