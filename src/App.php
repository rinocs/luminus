<?php

namespace Luminus;

class App
{
    private Container $container;
    private array $config = [];

    public function __construct()
    {
        $this->container = new Container();
        $this->container->set(App::class, $this);
        $this->container->set(Container::class, $this->container);
    }

    public function loadConfig(array $config): void
    {
        $this->config = $config;
        $this->container->set('config', $this->config);

        $this->container->singleton(Request::class, fn() => Request::capture());
        $this->container->singleton(Response::class, fn() => new Response());
        $this->container->singleton(Router::class, fn(Container $c) => new Router($c));
        $this->container->singleton(
            View::class,
            fn() => new View($config['views_path'] ?? __DIR__ . '/../views')
        );

        if (isset($config['database'])) {
            $this->container->singleton(
                Database::class,
                fn() => new Database($config['database'])
            );
        }

        if (isset($config['queue'])) {
            $this->container->singleton(
                \Luminus\Queue\QueueManager::class,
                fn(Container $c) => new \Luminus\Queue\QueueManager($c, $config['queue'])
            );
        }
    }

    public function loadEnv(string $path = __DIR__ . '/../.env'): void
    {

        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                $value = preg_replace('/^["\'](.*)["\']$/', '$1', $value);

                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }

    public function loadRoutes(string $routesFile): void
    {
        $router = $this->container->get(Router::class);

        if (!file_exists($routesFile)) {
            throw new \RuntimeException("Routes file not found: {$routesFile}");
        }

        require $routesFile;
    }

    public function run(): void
    {
        try {
            $router = $this->container->get(Router::class);
            $request = $this->container->get(Request::class);

            $response = $router->dispatch($request);
            $response->send();
        } catch (\Throwable $e) {
            error_log((string) $e);

            $debug = $this->config['debug'] ?? false;

            if ($debug) {
                $body = '<h1>500 Internal Server Error</h1><pre>'
                    . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "\n"
                    . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . '</pre>';
            } else {
                $body = '<h1>500 Internal Server Error</h1>';
            }

            $response = new Response();
            $response->status(500)->body($body)->send();
        }
    }

    public function addMiddleware(Middleware $middleware): void
    {
        $router = $this->container->get(Router::class);
        $router->addMiddleware($middleware);
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }
        return $this->config[$key] ?? $default;
    }
}
