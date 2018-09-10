<?php
namespace Controller;

class Error extends Base
{

    public function BadRequestAction()
    {
        $this->render([], '400.php');
    }
    
    public function UnauthorizedAction()
    {
        $this->render([], '401.php');
    }
    
    public function ForbiddenAction()
    {
        $this->render([], '403.php');
    }
    
    public function NotFoundAction()
    {
        $this->render([], '404.php');
    }
}