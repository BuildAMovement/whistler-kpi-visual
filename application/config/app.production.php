<?php
return [
    'dispatch' => [
        'controllers' => [
            'filtered-reports',
            'error'
        ],
        'controller_aliases' => [],
        'defaults' => [
            'controller' => 'filtered-reports',
            'action' => 'index'
        ]
    ],
    'storage' => APPLICATION_PATH . '/../storage',
    'jsOutStorage' => [
        'absrootpath' => APPLICATION_PATH . '/../public',
        'webpath' => '/static2/js/gc',
        'serve-minified' => false
    ],
    'assets' => [
        'serve-minified' => false
    ]
];
