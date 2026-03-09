<?php

$params = require __DIR__ . '/params.php';
$db     = require __DIR__ . '/db.php';

$config = [
    'id'         => 'basic',
    'name'       => 'Samba.ai idosell v3 connector',
    'basePath'   => dirname(__DIR__),
    'bootstrap'  => [
        'log',
        'app\extensions\ModuleBootstrap',
    ],
    'aliases'    => [
        '@bower'         => '@vendor/bower-asset',
        '@npm'           => '@vendor/npm-asset',
        '@api'           => '../modules/api',
        '@iai'           => '../modules/IAI',
        '@xml_generator' => '../modules/xml_generator',
    ],
    'modules'    => [
        'iai'           => [
            'class' => 'app\modules\IAI\Module',
        ],
        'api'           => [
            'class' => 'app\modules\api\Module',
        ],
        'xml_generator' => [
            'class' => 'app\modules\xml_generator\Module',
        ],
    ],
    'components' => [
        'request'      => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => getenv('YII_COOKIE_KEY') ?: '0vR308SsCEt2ykxnhf8gNSAkTj1N_6Xo',
        ],
        'authManager'  => [
            'class'        => 'yii\rbac\PhpManager',
            'defaultRoles' => ['end-user'],
        ],
        'cache'        => [
            'class' => 'yii\caching\FileCache',
        ],
        'user'         => [
            'identityClass'   => 'app\models\User',
            'enableAutoLogin' => true,
            'loginUrl'        => '/authorization/login',
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],

        'mailer'       => [
            'class'            => 'yii\swiftmailer\Mailer',
            // send all mails to a file by default. You have to set
            // 'useFileTransport' to false and configure a transport
            // for the mailer to send real emails.
            'useFileTransport' => true,
        ],
        'log'          => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets'    => [
                [
                    'class'  => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db'           => $db,
        'urlManager'   => [
            'enablePrettyUrl' => true,
            'showScriptName'  => false,
            'rules'           => [
            ],
        ],
        'session'      => [
            'cookieParams' => [
                'lifetime' => 10800,
                'path'     => '/; SameSite=None',
                'domain'   => '',
                'secure'   => true,
                'httponly' => true,
            ],
        ],
        'assetManager' => [
            'basePath' => '@webroot/assets',
            'baseUrl'  => '@web/assets',
        ],
    ],
    'params'     => $params,

];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][]      = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][]    = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
