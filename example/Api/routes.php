<?php

use Luminus\Request;
use Luminus\Response;
use Luminus\Middleware;
use Example\Api\Controllers\ProductController;

// API key authentication middleware
$router->addMiddleware(new class implements Middleware {
    public function handle(Request $request, callable $next): Response
    {
        $key = $request->header('X-API-Key');

        if (!$key || $key !== 'luminus-secret') {
            return (new Response())
                ->status(401)
                ->json(['error' => 'Unauthorized']);
        }

        return $next($request);
    }
});

$router->get('/products', [ProductController::class, 'index']);
$router->get('/products/{id}', [ProductController::class, 'show']);
$router->post('/products', [ProductController::class, 'store']);
$router->put('/products/{id}', [ProductController::class, 'update']);
$router->delete('/products/{id}', [ProductController::class, 'destroy']);
