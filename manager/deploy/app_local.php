<?php

use Cake\Cache\Engine\RedisEngine;

return [
    'debug' => false,

    'Project' => [
        'name' => 'Chialab Content Manager',
    ],

    'Security' => [
        'salt' => env('SECURITY_SALT', '__SALT__'),
    ],

    'API' => [
        'apiBaseUrl' => env('BEDITA_API', ''),
        'apiKey' => env('BEDITA_API_KEY', ''),
        'log' => [
            //'log_file' => LOGS . 'api.log',
        ],
        'guzzleConfig' => [
            'connect_timeout' => 2,
            'timeout' => 15,
            'verify' => false,
        ],
    ],

    'Cache' => [
        'default' => [
            'className' => RedisEngine::class,
            'host' => env('CACHE_REDIS_HOST', null),
            'port' => env('CACHE_REDIS_PORT', 6379),
            'database' => 4,
            'prefix' => 'bcbf_manager_cake_',
        ],

        'session' => [
            'className' => RedisEngine::class,
            'host' => env('CACHE_REDIS_HOST', null),
            'port' => env('CACHE_REDIS_PORT', 6379),
            'database' => 5,
            'prefix' => 'bcbf_manager_session_',
            'duration' => 'tomorrow 4:00',
        ],
    ],

    'Session' => [
        'defaults' => 'cache',
        'timeout' => 1440, // 1 day, in minutes
        'handler' => [
            'config' => 'session',
        ],
    ],

    'AlertMessage' => [
        'text' => env('ALERT_TEXT', null),
        'color' => env('ALERT_COLOR', null),
    ],

    /**
     * Modules configuration.
     *
     * Keys must be actual API endpoint names like `documents`, `users` or `folders`.
     * Modules order will follow key order of this configuration.
     * In case of core or plugin modules not directly served by ModulesController
     * (generally modules not related to bject types) a 'route' attribute can be specified for
     * custom controller and action rules.
     *
     * Array value may contain:
     *
     *  'label' - module label to display, if not set `key` will be used
     *  'shortLabel' - short label, 3 character recommended
     *  'color' - primary color code,
     *  'route' - (optional) custom route (named route or absolute/relative URL) used by plugin modules mainly
     *  'secondaryColor' - secondary color code,
     *  'sort' - sort order to be used in index; use a field name prepending optionl `-` sign
     *          to indicate a descendant order, f.i. '-title' will sort by title in reverse alphabetical order
     *          (default is '-id'),
     *  'icon' - icon code, f.i. `icon-article`, have a look in
     *      `webroot/css/be-icons-codes.css` for a complete list of codes
     */
    'Modules' => [
        'folders' => [
            'color' => '#6FB78B',
            'shortLabel' => 'fold',
        ],
        'exhibitions' => [
                'color' => '#FFD800',
                'secondaryColor' => '#000000',
        ],
        'documents' => [
                'color' => '#D95700',
                'shortLabel' => 'doc',
        ],
        'profiles' => [
            'color' => '#2C8E00',
        ],
        'books' => [
            'color' => '#CCAD00',
        ],
        'images' => [
            'color' => '#D5002B',
        ],
        'videos' => [
            'color' => '#D5002B',
        ],
        'audio' => [
            'color' => '#D5002B',
        ],
        'files' => [
            'color' => '#D5002B',
        ],
        'media' => [
            'color' => '#B3001B',
        ],
        'translations' => [
            'color' => '#cc6666',
        ],
        'news' => [
            'color' => '#6464FE',
        ],
        'events' => [
            'color' => '#09c',
            'sort' => '-id',
        ],
        'galleries' => [
            'color' => '#FF9D00',
        ],
        'bibliographies' => [
            'color' => '#CCAD00',
            'shortLabel' => 'bib',
        ],
        'links' => [
            'color' => '#006CFF',
        ],
        'locations' => [
            'color' => '#6A6256',
            'categories' => [
                '_element' => 'Modules/locations_categories',
            ],
            'sidebar' => [
                '_element' => 'Modules/locations_sidebar',
            ],
        ],
        'users' => [
            'color' => '#000000',
        ],
        'tags' => [
            'color' => '#C4C4A9',
            'secondaryColor' => '#000000',
        ],
        'publications' => [
            'color' => '#6F94B7',
            'shortLabel' => 'pub',
        ],
        'objects' => [
            'shortLabel' => 'obj',
            'color' => '#230637',
            'sort' => '-modified',
        ],
    ],

    /**
     * Add custom style formats to Richeditor
     */
    'Richeditor' => [
        'style_formats' => [
            [
                'title' => 'Stili',
                'items' => [
                    ['title' => 'Autore citazione', 'inline' => 'b', 'classes' => 'quote-author'],
                    ['title' => 'Fonte citazione', 'inline' => 'cite'],
                ],
            ],
            [
                'title' => 'Blocchi personalizzati',
                'items' => [
                    ['title' => 'Citazione breve', 'block' => 'blockquote', 'classes' => 'short-quote'],
                ],
            ]
        ],
        'content_style' => '.quote-author, cite { font-weight: 400; font-style: italic; }' .
            '.short-quote { padding: 0; margin-left: 20px; font-style: italic; border: none; border-top: 1px solid black; border-bottom: 1px solid black; }' .
            '.short-quote .quote-author { font-style: normal; font-size: 12px; margin-top: 12px; }',
        'style_formats_merge' => true,
        'max_height' => 1500
    ],
];
