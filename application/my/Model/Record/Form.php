<?php
namespace Model\Record;

class Form extends Base
{

    protected static $tableName = 'logger_xform';

    protected static $djangoContentTypeModel = 'xform';
    
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
    