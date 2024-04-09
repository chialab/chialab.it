<?php

use Cake\Core\Configure;

Configure::write(
    'App.paths.templates',
    array_merge(
        (array)Configure::read('App.paths.templates'),
        [
            ROOT . DS . 'plugins' . DS . 'OpenSource' . DS . 'templates' . DS,
        ]
    )
);

Configure::write(
    'App.paths.locales',
    array_merge(
        (array)Configure::read('App.paths.locales'),
        [
            ROOT . DS . 'plugins' . DS . 'OpenSource' . DS . 'resources' . DS . 'locales' . DS,
        ]
    )
);

Configure::write(
    'I18n',
    [
        'locales' => [
            'en' => 'en',
        ],
        'default' => 'en',
        'languages' => [
            'en' => 'English',
        ],
    ],
);
