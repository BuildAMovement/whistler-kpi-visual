<?php
namespace Controller;

/**
 *
 * @method string partial(string $script, array $vars = array()) render some partial script
 * @method void redirect(string $url) redirect to url
 * @method string render(array $vars = array(), string $script = null, boolean $controllerDir = true) render view script, pass $vars, if script is omitted use script name after action name in controller name directory (3rd param true)
 * @method \Component\User user() current logged on user helper
 * @method \Ufw\Helper\Url url() url builder
 * @method \Ufw\Helper\FlashMessenger flashMessenger() Flash Messenger - implement session-based messages
 *        
 * @property \Component\User $user
 *
 */
abstract class Base extends \Ufw\Controller\Base
{

    public function accessRestriction()
    {
        return $this;
    }

    protected function middlewaresPre()
    {
        return [
            \Ufw\Middleware\JsonRequest::class,
            \Ufw\Middleware\JsonResponse::class
        ];
    }

    protected function middlewaresPost()
    {
        return [];
    }

    protected function getDb()
    {
        return \Db\Db::instance();
    }

    protected function send($outfile)
    {
        $this->getResponse()
            ->setFormat('raw')
            ->withHeader('Content-Disposition', 'attachment; filename="' . rawurlencode($outfile['clientFilename']) . '"; filename*="' . rawurlencode($outfile['clientFilename']) . '"')
            ->withHeader('Cache-Control', 'private')
            ->withHeader('Pragma', '""')
            ->withHeader('Content-Type', $outfile['clientMediaType'])
            ->withHeader('Content-Length', $outfile['size']);
        $ffn = $this->getApplication()->getConfig()['storage'] . '/' . $outfile['storedFileneme'];
        $body = new \Zend\Diactoros\Stream($ffn, 'r');
        $this->getResponse()->withBody($body);
    }
}