<?php
/**
 * @var array $config
 */
$config;

return [
    'submissions' => [
        'type' => '\Ufw\Route\RegEx',
        'patterns' => [
            '~^/(?P<username>[^/]+)/(?P<controller>[^/]+)/(?P<xform_id>[^/]+)$~',
            '~^/(?P<username>[^/]+)/(?P<controller>[^/]+)/(?P<xform_id>[^/]+)/(?P<action>[^/]+)$~'
        ],
        'defaults' => [
            'action' => 'submissions'
        ],
        'constraints' => [
            'checkIfControllerIsRegistered' => true,
            'checkAction' => true,
            'httpMethod' => [
                'GET'
            ],
            'controller' => '~^(filtered-reports)$~'
        ],
        'options' => [
            'allowUriParams' => false
        ],
        'urlBuilder' => [
            '/:username/:controller/:xform_id',
            '/:username/:controller/:xform_id/:action',
        ]
    ],
    'default' => [
        'type' => '\Ufw\Route\RegEx',
        'patterns' => [
            '~^/(?P<controller>[^/]+)(/(?P<action>[^/]+))?/?~',
            '~^/(?P<controller>[^/]+)/?~',
            '~^/(?P<action>[^/]+)/?~',
            '~^/~'
        ],
        'defaults' => [
            'controller' => $config['dispatch']['defaults']['controller'],
            'action' => $config['dispatch']['defaults']['action']
        ],
        'constraints' => [
            'checkIfControllerIsRegistered' => true,
            'checkAction' => true
        ],
        'options' => [
            'allowUriParams' => true
        ],
        'urlBuilder' => [
            '/:controller/:action',
            '/:controller',
            '/:action',
            '/'
        ]
    ]
];