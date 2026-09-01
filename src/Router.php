<?php

namespace Luminus;

class Router
{
    private array $routes = [];
    private array $middleware = [];
    private Container $container;

    // Track active route grouping context
    private array $groupStack = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function get(string $pattern, mixed $handler): void
    {
        $this->addRoute('GET', $pattern, $handler);
    }

    public function post(string $pattern, mixed $handler): void
    {
        $this->addRoute('POST', $pattern, $handler);
    }

    public function put(string $pattern, mixed $handler): void
    {
        $this->addRoute('PUT', $pattern, $handler);
    }

    public function patch(string $pattern, mixed $handler): void
    {
        $this->addRoute('PATCH', $pattern, $handler);
    }

    public function delete(string $pattern, mixed $handler): void
    {
        $this->addRoute('DELETE', $pattern, $handler);
    }

    /**
     * Define a group of routes sharing attributes like prefixes and middlewares.
     */
    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;

        $callback($this);

        array_pop($this->groupStack);
    }

    private function addRoute(string $method, string $pattern, mixed $handler): void
    {
        // Resolve prefix and middleware from the active group stack
        $prefix = '';
        $middlewares = [];

        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
            if (isset($group['middleware'])) {
                $groupMw = is_array($group['middleware']) ? $group['middleware'] : [$group['middleware']];
                $middlewares = array_merge($middlewares, $groupMw);
            }
        }

        if ($prefix !== '') {
            $pattern = '/' . trim($prefix, '/') . '/' . ltrim($pattern, '/');
            if ($pattern !== '/') {
                $pattern = rtrim($pattern, '/');
            }
        }

        $this->routes[] = [
            'method' => $method,
            'pattern' => $this->compilePattern($pattern),
            'handler' => $handler,
            'middleware' => $middlewares,
        ];
    }

    private function compilePattern(string $pattern): string
    {
        $quoted = preg_quote($pattern, '#');
        $quoted = preg_replace('/\\\\\{(\w+)\\\\\}/', '(?P<$1>[^/]+)', $quoted);
        return '#^' . $quoted . '$#';
    }

    public function addMiddleware(Middleware $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method();
        $path = $request->path();

        // HEAD requests are served by the matching GET route.
        $lookupMethod = $method === 'HEAD' ? 'GET' : $method;

        $route = null;
        $params = [];
        $allowedMethods = [];

        foreach ($this->routes as $r) {
            if (preg_match($r['pattern'], $path, $matches)) {
                if ($r['method'] === $lookupMethod) {
                    $route = $r;
                    $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                    break;
                }
                $allowedMethods[] = $r['method'];
            }
        }

        if ($route !== null) {
            $handler = fn(Request $req) => $this->resolveHandler($route['handler'], $req, $params);

            // Apply route-specific middleware
            if (!empty($route['middleware'])) {
                foreach (array_reverse($route['middleware']) as $mw) {
                    $handler = fn(Request $req) => $mw->handle($req, $handler);
                }
            }
        } elseif ($allowedMethods !== []) {
            $allow = implode(', ', array_unique($allowedMethods));
            $handler = fn(Request $req) => (new Response())
                ->status(405)
                ->header('Allow', $allow)
                ->body('405 Method Not Allowed');
        } else {
            $handler = fn(Request $req) => (new Response())->status(404)->body('404 Not Found');
        }

        foreach (array_reverse($this->middleware) as $mw) {
            $handler = fn(Request $req) => $mw->handle($req, $handler);
        }

        return $handler($request);
    }

    private function resolveHandler(mixed $handler, Request $request, array $params): Response
    {
        $result = null;

        if ($handler instanceof \Closure) {
            $args = $this->resolveCallableArgs(
                new \ReflectionFunction($handler),
                $request,
                $params
            );
            $result = $handler(...$args);
        } elseif (is_array($handler) && count($handler) === 2) {
            if (!is_string($handler[0]) && !is_object($handler[0])) {
                throw new \RuntimeException('Route handler controller must be a class name string or object');
            }
            if (!is_string($handler[1])) {
                throw new \RuntimeException('Route handler method must be a string');
            }
            [$class, $method] = $handler;
            $controller = is_string($class) ? $this->container->get($class) : $class;
            $refMethod = new \ReflectionMethod($controller, $method);
            $args = $this->resolveCallableArgs($refMethod, $request, $params);
            $result = $controller->$method(...$args);
        } else {
            throw new \RuntimeException('Invalid route handler');
        }

        if ($result instanceof Response) {
            return $result;
        }

        return (new Response())->body((string) $result);
    }

    private function resolveCallableArgs(
        \ReflectionFunctionAbstract $ref,
        Request $request,
        array $params
    ): array {
        $args = [];

        foreach ($ref->getParameters() as $param) {
            $type = $param->getType();

            if ($type && !$type->isBuiltin()) {
                $typeName = $type->getName();

                if ($typeName === Request::class) {
                    $args[] = $request;
                } else {
                    $args[] = $this->container->get($typeName);
                }
            } elseif (isset($params[$param->getName()])) {
                $args[] = $params[$param->getName()];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } elseif ($param->allowsNull()) {
                $args[] = null;
            } else {
                throw new \RuntimeException(
                    "Cannot resolve parameter \${$param->getName()} for route handler"
                );
            }
        }

        return $args;
    }
}
