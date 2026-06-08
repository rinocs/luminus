<?php

use Luminus\Request;
use Luminus\Response;
use Luminus\View;

$router->get('/', function (Request $req, View $view): string {
    return $view->render('welcome', ['name' => 'Luminus']);
});

$router->get('/hello/{name}', function (Request $req, string $name): string {
    return "<h1>Hello, {$name}!</h1>";
});

$router->get('/api/ping', function (Request $req, Response $res): Response {
    return $res->json([
        'pong' => true,
        'time' => time(),
    ]);
});

$router->get('/users', function (Request $req, Response $res): Response {
    $users = [
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
        ['id' => 3, 'name' => 'Charlie'],
    ];

    return $res->json($users);
});
