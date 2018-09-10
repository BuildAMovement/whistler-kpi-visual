<?php
return (function() {
    switch (APPLICATION_ENV) {
        case 'development':
            $config = include 'app.development.php';
            break;
        case 'production':
        default:
            $config = include 'app.production.php';
    }
    $config['dispatch']['routes'] = include 'routes.php';
    return $config;
})();
