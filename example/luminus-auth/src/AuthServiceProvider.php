<?php

namespace LuminusAuth;

use Luminus\Providers\ServiceProvider;
use Luminus\View;
use Luminus\Router;
use Luminus\Response;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind any auth-specific services here if needed
    }

    public function boot(): void
    {
        $container = $this->app->getContainer();

        // Register the view namespace for this plugin
        if ($container->has(View::class)) {
            /** @var View $view */
            $view = $container->get(View::class);
            $view->addNamespace('auth', __DIR__ . '/../views');
        }

        // Register routes
        if ($container->has(Router::class)) {
            /** @var Router $router */
            $router = $container->get(Router::class);

            $router->get('/login', function() use ($container) {
                /** @var View $view */
                $view = $container->get(View::class);
                return new Response(200, [], $view->render('auth::login'));
            });
        }
    }
}
