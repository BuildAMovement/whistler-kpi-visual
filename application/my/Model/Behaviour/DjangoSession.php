<?php
namespace Model\Behaviour;

class DjangoSession extends Base
{

    public $serialAttributes = array();

    public function beforeDataSet($data)
    {
        if (count($this->serialAttributes)) {
            foreach ($this->serialAttributes as $attribute) {
                $_att = $data[$attribute];
                if (!empty($_att) && is_scalar($_att)) {
                    list($key, $val) = preg_split('~:~', base64_decode($_att), 2);
                    if ($key && $val) {
                        $val = json_decode($val, true);
                        if ($val !== false) {
                            $data[$attribute] = [$key => $val];
                        } else {
                            $data[$attribute] = array();
                        }
                    }
                }
            }
        }
        return $data;
    }

}