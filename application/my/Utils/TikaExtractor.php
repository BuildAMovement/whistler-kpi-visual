<?php 
namespace Utils;

class TikaExtractor {
    
    protected static $client = null;
    
    public static function text($filename) {
        $cfg = \Ufw\Registry::getInstance()->get('application')->getConfig();
        $ffn = $filename[0] == '/' ? $filename : $cfg['storage'] . '/' . $filename;
        $cmd = $cfg['tikapath'] . " --html --encoding=cp1250 \"$ffn\"";
        $output = [];
        $retval = null;
        exec($cmd, $output, $retval);
        return iconv('CP1250', 'UTF-8', join('', $output));
    }

}

