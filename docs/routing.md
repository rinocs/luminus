# Routing

## Basic routes

```php
$router->get('/', fn() => 'Home');
$router->post('/submit', fn(Request $req) => $req->input('name'));
$router->put('/users/{id}', [UserController::class, 'update']);
$router->patch('/items/{id}', fn(Request $req, string $id) => "...");
$router->delete('/posts/{id}', [PostController::class, 'destroy']);
```

## Route parameters

`{param}` matches any non-slash segment:

```php
$router->get('/users/{id}', fn(string $id) => "User $id");
$router->get('/posts/{year}/{slug}', fn(string $year, string $slug) => "...");
```

Parameters are injected by name into the handler.

## Handler types

**Closure** — type-hinted dependencies auto-injected:

```php
$router->get('/', function (Request $req, View $view, Response $res) {
    return $view->render('home');
});
```

**Controller class** — resolved via container:

```php
$router->get('/products', [ProductController::class, 'index']);

// ProductController is instantiated by the container
// with constructor dependencies auto-resolved
```

## Method spoofing

HTML forms can spoof PUT/PATCH/DELETE by adding a `_method` field:

```php
<form method="POST" action="/users/5">
    <input type="hidden" name="_method" value="DELETE">
    <button>Delete</button>
</form>
```

## Middleware

Middleware wraps route handlers. Implement the `Middleware` interface and register it on the router or app:

```php
use Luminus\Middleware;
use Luminus\Request;
use Luminus\Response;

class AuthMiddleware implements Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        $token = $request->header('X-Auth-Token');

        if (!$token || $token !== 'valid-token') {
            return (new Response())->status(401)->json(['error' => 'Unauthorized']);
        }

        return $next($request);
    }
}

// Register on the router directly
$router->addMiddleware(new AuthMiddleware());

// Or via the app (convenience)
$app->addMiddleware(new LoggingMiddleware());
```

**Execution order:**

Middleware runs in registration order — first added is outermost (processes the request first, receives the response last):

```php
$router->addMiddleware(new A()); // runs first (outermost)
$router->addMiddleware(new B()); // runs second
$router->addMiddleware(new C()); // runs last (innermost, closest to handler)

// Request:  A → B → C → handler
// Response: handler → C → B → A
```

**Short-circuiting:**

A middleware can return a `Response` without calling `$next($request)` to short-circuit the pipeline (e.g., for auth failures or rate limiting).

## 404

Unmatched routes return `404 Not Found` with a 404 status code.
