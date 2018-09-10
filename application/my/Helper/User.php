<?php
namespace Helper;

class User extends Base
{

    public function __invoke()
    {
        return $this->current();
    }

    public function current()
    {
        return \Component\User::getInstance();
    }
}