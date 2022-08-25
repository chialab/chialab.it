<?php

use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;
use Chialab\FrontendKit\Routing\LocaleUrlFilter;
use Chialab\FrontendKit\Routing\Route\ObjectRoute;

Router::addUrlFilter(new LocaleUrlFilter());

Router::plugin(
    'Design',
    ['path' => '/'],
    function (RouteBuilder $routes) {
        $routeBuilder = function (RouteBuilder $routes) {
            $routes->connect(
                '/',
                ['controller' => 'Pages', 'action' => 'home'],
                ['_name' => 'pages:home']
            );

            $routes->connect(
                '/login',
                ['controller' => 'Auth', 'action' => 'login'],
                ['_name' => 'auth:login']
            );

            $routes->connect(
                '/logout',
                ['controller' => 'Auth', 'action' => 'logout'],
                ['_name' => 'auth:logout']
            );

            $routes->connect(
                '/objects/{uname}',
                ['controller' => 'Pages', 'action' => 'object'],
                ['_name' => 'pages:objects', 'pass' => ['uname'], 'routeClass' => ObjectRoute::class]
            );

            $routes->connect(
                '/**',
                ['controller' => 'Pages', 'action' => 'fallback'],
                ['_name' => 'pages:fallback']
            );
        };

        $routes->scope('/lang/{locale}', ['_namePrefix' => 'lang:'], $routeBuilder);
        $routeBuilder($routes);
    }
);
