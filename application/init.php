<?php
date_default_timezone_set('Europe/Belgrade');

error_reporting(E_ALL ^ E_NOTICE);
ini_set('memory_limit', '64M');

defined('APPLICATION_ENV') || define('APPLICATION_ENV', (getenv('APPLICATION_ENV') ? getenv('APPLICATION_ENV') : 'production'));

// Define path to application directory
defined('APPLICATION_PATH') || define('APPLICATION_PATH', realpath(__DIR__));

// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(APPLICATION_PATH . '/lib'),
    get_include_path()
)));

include_once 'lib.inc.php';
include_once (APPLICATION_PATH . '/lib/SplClassLoader.php');

@include_once __DIR__ . '/../vendor/autoload.php';

(new SplClassLoader(null, APPLICATION_PATH . '/my'))->register();

\Ufw\InfoHash::setDbClass(\Db\Db::class);
