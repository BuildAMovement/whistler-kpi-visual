<?php
namespace Model\Behaviour;

class PGBoolean extends Base
{

    public $serialAttributes = array();

    public function beforeDataSet($data)
    {
        if (count($this->serialAttributes)) {
            foreach ($this->serialAttributes as $attribute) {
                $_att = $data[$attribute];
                if ($_att) {
                    $_att = !strcmp($_att, 't');
                }
                $data[$attribute] = $_att;
            }
        }
        return $data;
    }

    public function beforeSave()
    {
        if (count($this->serialAttributes)) {
            $data = $this->getOwner()->getAll();
            foreach ($this->serialAttributes as $attribute) {
                if (!array_key_exists($attribute, $data)) {
                    continue;
                }
                $_att = $data[$attribute];
                
                if ($_att) {
                    $_att = $_att ? 't' : 'f';
                    $this->getOwner()->$attribute = $_att;
                }
            }
        }
        return $this;
    }

    /**
     * convert the saved as a serialized string back into an array, cause
     * thats how we want to use it anyways ya know?
     */
    public function afterSave($success)
    {
        if (count($this->serialAttributes)) {
            $data = $this->getOwner()->getAll();
            foreach ($this->serialAttributes as $attribute) {
                if (!array_key_exists($attribute, $data)) {
                    continue;
                }
                $_att = $data[$attribute];
                if ($_att) {
                    $_att = !strcmp($_att, 't');
                    $this->getOwner()->$attribute = $_att;
                }
            }
        }
    }
}