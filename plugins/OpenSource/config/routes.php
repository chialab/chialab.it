<?php
use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;
use Cake\Routing\Route\DashedRoute;

return static function (RouteBuilder $routes): void
{
    $routes->plugin(
        'OpenSource',
        ['path' => '/open-source'],
        function (RouteBuilder $routes) {
            $routes->fallbacks(DashedRoute::class);
        }
    );
};
