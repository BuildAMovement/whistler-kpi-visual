<?php
namespace Middleware;

use Webimpress\HttpMiddlewareCompatibility\HandlerInterface as DelegateInterface;
use Psr\Http\Message\ServerRequestInterface;

class User extends \Ufw\Middleware\Base
{

    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        session_start();
        if (isset($_COOKIE['kobonaut']) && $_COOKIE['kobonaut']) {
            /**
             *
             * @var \Component\User $user
             */
            $user = $this->getHelper('user')();
            /**
             * 
             * @var \Model\Record\Session $djangoSessionRow
             */
            $djangoSessionRow = (new \Model\Session())->one($_COOKIE['kobonaut']);
            if ($djangoSessionRow && $djangoSessionRow->isValid() && $djangoSessionRow->getUserId()) {
                $id = $djangoSessionRow->getUserId();
                if ($user->isValid($id)) {
                    $user->setId($id);
                } else {
                    setcookie("kobonaut", "", time() - 3600);
                    unset($_COOKIE['kobonaut']);
                }
            } else {
                setcookie("kobonaut", "", time() - 3600);
                unset($_COOKIE['kobonaut']);
            }
        }
        return $delegate->process($request);
    }
}