<?php

return (function () {
    if (APPLICATION_ENV == 'development') {
        // windows developer
        $___dbconnection_list = array(
            'local' => array(
                'ident' => 'local',
                'host' => '127.0.0.1',
                'user' => '',
                'pass' => '',
                'dbname' => 'kobotoolbox',
                'newlink' => false,
                'collation' => 'utf8',
                'port' => '15434'
            )
        );
    } else {
        $___dbconnection_list = array(
            'local' => array(
                'ident' => 'local',
                'host' => 'postgres',
                'user' => '',
                'pass' => '',
                'dbname' => 'kobotoolbox',
                'newlink' => false,
                'collation' => 'utf8',
                'collation' => '5432'
            )
        );
    }
    
    $___dbconnection_list['default'] = & $___dbconnection_list['local'];
    $___dbconnection_list['master_server'] = & $___dbconnection_list['local'];
    
    return $___dbconnection_list;
})();
