<?php
namespace Middleware;

use Webimpress\HttpMiddlewareCompatibility\HandlerInterface as DelegateInterface;
use Psr\Http\Message\ServerRequestInterface;

class AccessRestriction extends \Ufw\Middleware\Base
{

    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $this->getController()->accessRestriction();
        return $delegate->process($request);
    }
}