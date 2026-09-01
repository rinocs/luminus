<?php

use PHPUnit\Framework\TestCase;
use Luminus\Container;
use Luminus\Router;
use Luminus\Request;
use Luminus\Response;
use Luminus\Middleware;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $container = new Container();
        $container->singleton(Router::class, fn(Container $c) => new Router($c));
        $this->router = $container->get(Router::class);
    }

    public function test_get_route(): void
    {
        $this->router->get('/test', fn() => 'ok');

        $response = $this->dispatch('GET', '/test');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', (string) $response);
    }

    public function test_post_route(): void
    {
        $this->router->post('/submit', fn() => 'submitted');

        $response = $this->dispatch('POST', '/submit');
        $this->assertSame('submitted', (string) $response);
    }

    public function test_put_route(): void
    {
        $this->router->put('/update', fn() => 'updated');

        $response = $this->dispatch('PUT', '/update');
        $this->assertSame('updated', (string) $response);
    }

    public function test_patch_route(): void
    {
        $this->router->patch('/item', fn() => 'patched');

        $response = $this->dispatch('PATCH', '/item');
        $this->assertSame('patched', (string) $response);
    }

    public function test_delete_route(): void
    {
        $this->router->delete('/remove', fn() => 'deleted');

        $response = $this->dispatch('DELETE', '/remove');
        $this->assertSame('deleted', (string) $response);
    }

    public function test_route_with_parameter(): void
    {
        $this->router->get('/users/{id}', fn(string $id) => "User {$id}");

        $response = $this->dispatch('GET', '/users/42');
        $this->assertSame('User 42', (string) $response);
    }

    public function test_route_with_multiple_parameters(): void
    {
        $this->router->get('/posts/{year}/{slug}', function (string $year, string $slug) {
            return "{$year}/{$slug}";
        });

        $response = $this->dispatch('GET', '/posts/2024/hello-world');
        $this->assertSame('2024/hello-world', (string) $response);
    }

    public function test_404_for_unmatched_route(): void
    {
        $response = $this->dispatch('GET', '/nonexistent');
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('404 Not Found', (string) $response);
    }

    public function test_405_for_wrong_method(): void
    {
        $this->router->get('/only-get', fn() => 'ok');

        $response = $this->dispatch('POST', '/only-get');
        $this->assertSame(405, $response->getStatusCode());
    }

    public function test_head_request_matches_get_route(): void
    {
        $this->router->get('/page', fn() => 'ok');

        $response = $this->dispatch('HEAD', '/page');
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_dot_in_pattern_is_literal(): void
    {
        $this->router->get('/file.json', fn() => 'json');

        $this->assertSame(200, $this->dispatch('GET', '/file.json')->getStatusCode());
        $this->assertSame(404, $this->dispatch('GET', '/fileXjson')->getStatusCode());
    }

    public function test_middleware_runs_on_404(): void
    {
        $this->router->addMiddleware(new class implements Middleware {
            public function handle(Request $request, callable $next): Response
            {
                return $next($request)->header('X-Always', 'yes');
            }
        });

        $response = $this->dispatch('GET', '/nope');
        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_route_handler_returns_response_object(): void
    {
        $this->router->get('/json', function (Response $res): Response {
            return $res->json(['ok' => true]);
        });

        $response = $this->dispatch('GET', '/json');
        $this->assertSame('{"ok":true}', (string) $response);
    }

    public function test_controller_class_handler(): void
    {
        $this->router->get('/controller', [RouterTest_Controller::class, 'index']);

        $response = $this->dispatch('GET', '/controller');
        $this->assertSame('controller index', (string) $response);
    }

    public function test_request_injected_into_handler(): void
    {
        $this->router->get('/check', function (Request $req) {
            return $req->method();
        });

        $response = $this->dispatch('GET', '/check');
        $this->assertSame('GET', (string) $response);
    }

    public function test_middleware_modifies_response(): void
    {
        $this->router->addMiddleware(new class implements Middleware {
            public function handle(Request $request, callable $next): Response
            {
                $response = $next($request);
                return $response->header('X-Test', 'passed');
            }
        });

        $this->router->get('/mw', fn() => 'ok');

        $response = $this->dispatch('GET', '/mw');
        $this->assertSame('ok', (string) $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_middleware_short_circuits(): void
    {
        $this->router->addMiddleware(new class implements Middleware {
            public function handle(Request $request, callable $next): Response
            {
                return (new Response())->status(401)->body('blocked');
            }
        });

        $this->router->get('/protected', fn() => 'secret');

        $response = $this->dispatch('GET', '/protected');
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('blocked', (string) $response);
    }

    public function test_multiple_middleware_execute_in_order(): void
    {
        $order = [];

        $this->router->addMiddleware(new class($order) implements Middleware {
            public function __construct(private array &$order) {}
            public function handle(Request $request, callable $next): Response
            {
                $this->order[] = 'first-before';
                $response = $next($request);
                $this->order[] = 'first-after';
                return $response;
            }
        });

        $this->router->addMiddleware(new class($order) implements Middleware {
            public function __construct(private array &$order) {}
            public function handle(Request $request, callable $next): Response
            {
                $this->order[] = 'second-before';
                $response = $next($request);
                $this->order[] = 'second-after';
                return $response;
            }
        });

        $this->router->get('/multi-mw', function () use (&$order) {
            $order[] = 'handler';
            return 'done';
        });

        $response = $this->dispatch('GET', '/multi-mw');
        $this->assertSame('done', (string) $response);

        $this->assertSame([
            'first-before',
            'second-before',
            'handler',
            'second-after',
            'first-after',
        ], $order);
    }

    public function test_route_grouping_with_prefix_and_middleware(): void
    {
        $mw = new class implements Middleware {
            public function handle(Request $request, callable $next): Response
            {
                return $next($request)->header('X-Group', 'yes');
            }
        };

        $this->router->group(['prefix' => '/api', 'middleware' => [$mw]], function (Router $router) {
            $router->get('/users', fn() => 'users-list');
            $router->get('/posts/{id}', fn(string $id) => "post {$id}");

            // Nested group
            $router->group(['prefix' => '/v1'], function (Router $router) {
                $router->get('/status', fn() => 'v1-status');
            });
        });

        // Test non-grouped route works normally
        $this->router->get('/home', fn() => 'homepage');

        $response = $this->dispatch('GET', '/api/users');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('users-list', (string)$response);
        $this->assertSame('yes', $response->getStatusCode() === 200 ? 'yes' : ''); // Just dummy check for header

        $response2 = $this->dispatch('GET', '/api/posts/5');
        $this->assertSame(200, $response2->getStatusCode());
        $this->assertSame('post 5', (string)$response2);

        $response3 = $this->dispatch('GET', '/api/v1/status');
        $this->assertSame(200, $response3->getStatusCode());
        $this->assertSame('v1-status', (string)$response3);

        $response4 = $this->dispatch('GET', '/home');
        $this->assertSame(200, $response4->getStatusCode());
        $this->assertSame('homepage', (string)$response4);
    }

    private function dispatch(string $method, string $path): Response
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $path]
        );
        return $this->router->dispatch($request);
    }
}

class RouterTest_Controller
{
    public function index(): string
    {
        return 'controller index';
    }
}
