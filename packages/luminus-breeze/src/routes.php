<?php

use Luminus\Breeze\Controllers\AuthController;
use Luminus\Breeze\Controllers\ConfirmablePasswordController;

$router->get('/login', [AuthController::class, 'create']);
$router->post('/login', [AuthController::class, 'store']);
$router->post('/logout', [AuthController::class, 'destroy']);

$router->get('/register', [AuthController::class, 'registerCreate']);
$router->post('/register', [AuthController::class, 'registerStore']);

$router->get('/confirm-password', [ConfirmablePasswordController::class, 'show']);
$router->post('/confirm-password', [ConfirmablePasswordController::class, 'store']);
