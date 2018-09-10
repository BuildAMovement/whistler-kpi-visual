<?php
namespace Model\Record;

class Submission extends Base
{

    protected static $tableName = 'logger_instance';

    public function behaviours()
    {
        return [
            'json' => [
                'class' => \Ufw\Model\Behaviour\Json::class,
                'serialAttributes' => array(
                    'json'
                )
            ]
        ];
    }
}
    