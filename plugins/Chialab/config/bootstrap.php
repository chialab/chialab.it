<?php

use Cake\Core\Configure;

Configure::write(
    'App.paths.templates',
    array_merge(
        (array)Configure::read('App.paths.templates'),
        [
            ROOT . DS . 'plugins' . DS . 'Chialab' . DS . 'templates' . DS,
        ],
    ),
);

Configure::write(
    'App.paths.locales',
    array_merge(
        (array)Configure::read('App.paths.locales'),
        [
            ROOT . DS . 'plugins' . DS . 'Chialab' . DS . 'resources' . DS . 'locales' . DS,
        ],
    ),
);

Configure::write(
    'I18n',
    [
        'locales' => [
            'it' => 'it',
            'en' => 'en',
        ],
        'default' => 'it',
        'languages' => [
            'it' => 'Italiano',
            'en' => 'English',
        ],
    ],
);
