<?php

/** @var Bramus\Router\Router $router */

// Define routes here
$router->get('/', App\Controllers\IndexController::class . '@test');

$router->post('/facilities', App\Controllers\FacilityController::class . '@createOne');
$router->get('/facilities/{id}', App\Controllers\FacilityController::class . '@readOne');
$router->get('/facilities', App\Controllers\FacilityController::class . '@readAll');
$router->put('/facilities/{id}', App\Controllers\FacilityController::class . '@updateOne');
$router->delete('/facilities/{id}', App\Controllers\FacilityController::class . '@deleteOne');
