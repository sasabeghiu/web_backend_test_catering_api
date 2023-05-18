<?php

/** @var Bramus\Router\Router $router */

// Define routes here
$router->get('/', App\Controllers\IndexController::class . '@test');

$router->get('/facilities', App\Controllers\FacilityController::class . '@facilities');
$router->get('/facilities/{id}', App\Controllers\FacilityController::class . '@getOne');
