<?php

use Cake\Core\Configure;

Configure::write(
    'Skua.paths.templates',
    array_merge(
        (array)Configure::read('Skua.paths.templates'),
        [
            ROOT . DS . 'plugins' . DS . 'Skua' . DS . 'templates' . DS,
        ],
    ),
);

Configure::write(
    'Skua.paths.locales',
    array_merge(
        (array)Configure::read('Skua.paths.locales'),
        [
            ROOT . DS . 'plugins' . DS . 'Skua' . DS . 'resources' . DS . 'locales' . DS,
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
