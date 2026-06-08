<?php

use Luminus\Request;
use Luminus\Response;
use Luminus\Middleware;
use Example\Website\Controllers\PageController;

// Request logging middleware
$router->addMiddleware(new class implements Middleware {
    public function handle(Request $request, callable $next): Response
    {
        $start = microtime(true);
        $response = $next($request);
        $duration = (microtime(true) - $start) * 1000;

        $response->header('X-Debug-Time', round($duration, 2) . 'ms');

        return $response;
    }
});

$router->get('/', [PageController::class, 'home']);
$router->get('/about', [PageController::class, 'about']);
$router->get('/contact', [PageController::class, 'contact']);
$router->post('/contact', [PageController::class, 'sendContact']);
