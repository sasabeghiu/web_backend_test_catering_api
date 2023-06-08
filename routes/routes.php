<?php

/** @var Bramus\Router\Router $router */

// Define routes here
$router->get('/', App\Controllers\IndexController::class . '@test');

$router->get('/facilities/search', App\Controllers\FacilityController::class . '@search');
$router->post('/facility', App\Controllers\FacilityController::class . '@createOne');
$router->get('/facility/{id}', App\Controllers\FacilityController::class . '@readOne');
$router->get('/facilities', App\Controllers\FacilityController::class . '@readAll');
$router->put('/facility/{id}', App\Controllers\FacilityController::class . '@updateOne');
$router->delete('/facility/{id}', App\Controllers\FacilityController::class . '@deleteOne');
