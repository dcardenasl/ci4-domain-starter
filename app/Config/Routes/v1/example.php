<?php

declare(strict_types=1);

/** @var \CodeIgniter\Router\RouteCollection $routes */

$routes->group('example', ['namespace' => '\App\Controllers\Api\V1\Example'], function ($routes): void {

    // Item Index & Show (Read)
    $routes->group('', ['filter' => ['domainauth', 'permission:items.read', 'throttle']], function ($routes): void {
        $routes->get('items', 'ItemController::index');
        $routes->get('items/(:num)', 'ItemController::show/$1');
    });

    // Item Create
    $routes->group('', ['filter' => ['domainauth', 'permission:items.create', 'throttle']], function ($routes): void {
        $routes->post('items', 'ItemController::create');
    });

    // Item Update
    $routes->group('', ['filter' => ['domainauth', 'permission:items.update', 'throttle']], function ($routes): void {
        $routes->put('items/(:num)', 'ItemController::update/$1');
    });

    // Item Delete
    $routes->group('', ['filter' => ['domainauth', 'permission:items.delete', 'throttle']], function ($routes): void {
        $routes->delete('items/(:num)', 'ItemController::delete/$1');
    });

    // Resource routes will be injected here
});
