<?php
namespace Model\Record;

abstract class Base extends \Ufw\Model\Record\Simple
{

    protected static $primaryKeyField = 'id';

    protected static $djangoContentTypeModel = 'xform';

    public function __construct(array $data = array())
    {
        parent::__construct($data);
        if ($this->{static::$primaryKeyField}) {
            $this->setIsNewRecord(false);
        }
    }

    public function getDb()
    {
        return \Db\Db::instance();
    }

    /**
     *
     * @return string
     */
    public function getDjangoContentTypeModel()
    {
        return static::$djangoContentTypeModel;
    }
}