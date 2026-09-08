<?php

namespace Luminus\Breeze;

use Luminus\Providers\ServiceProvider;
use Luminus\View;
use Luminus\Router;

class BreezeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Nothing to bind at register time
    }

    public function boot(): void
    {
        $container = $this->app->getContainer();

        // Register the view namespace
        if ($container->has(View::class)) {
            /** @var View $view */
            $view = $container->get(View::class);
            $view->addNamespace('breeze', __DIR__ . '/views');
        }

        // Register routes
        if ($container->has(Router::class)) {
            $router = $container->get(Router::class);
            require __DIR__ . '/routes.php';
        }
    }
}
