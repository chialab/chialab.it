<?php

use BEdita\AWS\Filesystem\Adapter\S3Adapter;
use BEdita\Core\Cache\Engine\LayeredEngine;
use Cake\Cache\Engine\FileEngine;
use Cake\Cache\Engine\RedisEngine;
use Cake\Database\Connection;
use Cake\Database\Driver\Mysql;

return [
    'debug' => filter_var(env('DEBUG', false), FILTER_VALIDATE_BOOLEAN),

    'Security' => [
        'salt' => env('SECURITY_SALT', null),
    ],

    'Datasources' => [
        'default' => [
            'url' => env('DATABASE_URL', null),
            'ssl_ca' => env('DATABASE_SSL_CA_PATH', null),
            'log' => false,
        ],

        'old-import' => [
            'className' => Connection::class,
            'driver' => Mysql::class,
            'persistent' => false,
            'timezone' => 'UTC',
            'flags' => [],
            'cacheMetadata' => true,
            'log' => false,
            'quoteIdentifiers' => false,

            'url' => env('DATABASE_IMPORT_URL', null),
            'ssl_ca' => env('DATABASE_SSL_CA_PATH', null),
        ],
    ],

    'Cache' => [
        'default' => [
            'className' => RedisEngine::class,
            'host' => env('CACHE_REDIS_HOST', null),
            'port' => env('CACHE_REDIS_PORT', 6379),
            'database' => 0,
            'prefix' => sprintf('chialab_%s_', env('CACHE_SCOPE', 'default')),
        ],

        'session' => [
            'className' => RedisEngine::class,
            'host' => env('CACHE_REDIS_HOST', null),
            'port' => env('CACHE_REDIS_PORT', 6379),
            'database' => 3,
            'prefix' => 'session_',
            'duration' => 'tomorrow 4:00',
        ],

        '_bedita_core_' => [
            'className' => LayeredEngine::class,
            'persistent' => [
                'className' => RedisEngine::class,
                'host' => env('CACHE_REDIS_HOST', null),
                'port' => env('CACHE_REDIS_PORT', 6379),
                'database' => 0,
                'prefix' => 'bedita_core_',
            ],
            'prefix' => 'bedita_core_',
            'serialize' => true,
            'duration' => '+1 year',
            'url' => env('CACHE_BEDITACORE_URL', null),
        ],

        '_bedita_object_types_' => [
            'className' => LayeredEngine::class,
            'persistent' => [
                'className' => RedisEngine::class,
                'host' => env('CACHE_REDIS_HOST', null),
                'port' => env('CACHE_REDIS_PORT', 6379),
                'database' => 0,
                'prefix' => 'bedita_object_types_',
            ],
            'prefix' => 'bedita_object_types_',
            'serialize' => true,
            'duration' => '+1 year',
            'url' => env('CACHE_BEDITAOBJECTTYPES_URL', null),
        ],

        '_cake_core_' => [
            'className' => FileEngine::class,
            'prefix' => 'cake_core_',
            'path' => CACHE . 'persistent/',
            'serialize' => true,
            'duration' => '+1 years',
            'url' => env('CACHE_CAKECORE_URL', null),
        ],

        '_cake_model_' => [
            'className' => RedisEngine::class,
            'host' => env('CACHE_REDIS_HOST', null),
            'port' => env('CACHE_REDIS_PORT', 6379),
            'database' => 0,
            'prefix' => 'cake_model_',
            'serialize' => true,
            'duration' => '+1 years',
        ],

        '_cake_routes_' => [
            'className' => FileEngine::class,
            'prefix' => 'cake_routes_',
            'path' => CACHE,
            'serialize' => true,
            'duration' => '+1 years',
            'url' => env('CACHE_CAKEROUTES_URL', null),
        ],

        '_twig_views_' => [
            'className' => FileEngine::class,
            'prefix' => 'twig_views_',
            'path' => CACHE . 'twigView/',
            'serialize' => true,
            'duration' => '+1 year',
        ],

        '_clear_cache_' => [
            'className' => RedisEngine::class,
            'host' => env('CACHE_REDIS_HOST', null),
            'port' => env('CACHE_REDIS_PORT', 6379),
            'database' => 0,
        ],
    ],

    'Session' => [
        'defaults' => 'cache',
        'timeout' => 1440, // 1 day, in minutes
        'handler' => [
            'config' => 'session',
        ],
    ],

    'Filesystem' => [
        'default' => [
            'className' => S3Adapter::class,
            'visibility' => 'private',
            'bucket' => env('S3_BUCKET_NAME', null),
            'prefix' => '',
            'region' => env('S3_BUCKET_REGION', env('AWS_DEFAULT_REGION', null)),
            'distributionId' => env('CDN_DISTRIBUTION_ID', null),
            'baseUrl' => env('CDN_DISTRIBUTION_URL', null),
        ],
        'thumbnails' => [
            'className' => S3Adapter::class,
            'visibility' => 'private',
            'bucket' => env('S3_BUCKET_NAME', null),
            'prefix' => '',
            'region' => env('S3_BUCKET_REGION', env('AWS_DEFAULT_REGION', null)),
            'distributionId' => env('CDN_DISTRIBUTION_ID', null),
            'baseUrl' => env('CDN_DISTRIBUTION_URL', null),
        ],
    ],

    'FrontendPlugin' => env('FRONTEND_PLUGIN', 'BEdita/API'),
    'Theme' => env('THEME', 'Chialab'),

    'StagingSite' => filter_var(env('STAGING', false), FILTER_VALIDATE_BOOLEAN),

    'Manage' => [
        'manager' => [
            'url' => env('MANAGER_URL', ''),
        ],
    ],
];
