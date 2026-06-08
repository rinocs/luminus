<?php

use Luminus\Request;
use Luminus\Response;
use Luminus\View;
use Luminus\Middleware;

// Benchmarking middleware
$router->addMiddleware(new class implements Middleware {
    public function handle(Request $request, callable $next): Response
    {
        $start = microtime(true);
        $response = $next($request);
        $duration = (microtime(true) - $start) * 1000;
        return $response->header('X-Duration', round($duration, 2) . 'ms');
    }
});

$router->get('/', function (Request $req, View $view): string {
    return $view->render('welcome', ['name' => 'Luminus']);
});

$router->get('/hello/{name}', function (string $name): string {
    return "<h1>Hello, {$name}!</h1>";
});

$router->get('/api/ping', function (Response $res): Response {
    return $res->json(['pong' => true, 'time' => time()]);
});
