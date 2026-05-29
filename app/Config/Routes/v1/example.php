<?php

declare(strict_types=1);

/** @var \CodeIgniter\Router\RouteCollection $routes */

$routes->group('example', ['namespace' => '\App\Controllers\Api\V1\Example'], function ($routes): void {

    // Auth & Admin Protected Group
    $routes->group('', ['filter' => ['domainauth', 'permission:items.read', 'throttle']], function ($routes): void {
        // Item Routes
        $routes->get('items', 'ItemController::index');
        $routes->get('items/(:num)', 'ItemController::show/$1');
        $routes->post('items', 'ItemController::create');
        $routes->put('items/(:num)', 'ItemController::update/$1');
        $routes->delete('items/(:num)', 'ItemController::delete/$1');

        // Resource routes will be injected here
    });
});
