# Luminus

Minimal PHP framework — zero external dependencies. PHP 8.2+.

## Features

- Zero dependencies — only PHP 8.2+ and Composer
- DI container with autowiring via reflection
- Router with `{param}` patterns and auto-injection into handlers
- Middleware pipeline (PSR-15-like interface)
- Template engine with layouts and sections (plain PHP)
- Fiber-based async for concurrent operations
- Fluent query builder for PDO
- Error handling with debug/production modes
- Security: Session & Flash manager, CSRF middleware, global XSS escaping helper, and browser security headers
- Docker + Nginx + PHP-FPM production-ready
- ~1200 lines of core code

## Create a new app

```bash
# Using the scaffold script (recommended)
bin/new my-project
cd my-project
make dev

# Or with Composer create-project (local path)
composer create-project --repository='{"type":"path","url":"./luminus"}' luminus/luminus my-project
cd my-project
make dev
```

## Quick start (this project)

```bash
make setup
make dev
# or: bin/dev
# or: php -S localhost:8080 -t public public/router.php
```

## Documentation

| Guide | Description |
|---|---|---|
| [Getting started](docs/getting-started.md) | Setup, env, first route |
| [Routing](docs/routing.md) | Patterns, handlers, controllers, middleware |
| [Request & Response](docs/request-response.md) | Input, JSON, files, responses |
| [Views](docs/views.md) | Templates, layouts, sections |
| [Container](docs/container.md) | Autowiring, singleton, binding |
| [Database](docs/database.md) | PDO wrapper, query builder |
| [Async](docs/async.md) | Fibers, concurrent HTTP, parallel tasks |
| [Docker & deploy](docs/docker-deploy.md) | Docker, compose, Makefile |
| [CLI](docs/cli.md) | Console, scripts, Makefile targets |

## Examples

See [`example/Api/`](example/Api) for a RESTful CRUD API and [`example/Website/`](example/Website) for a multi-page website:

```bash
php -S localhost:8080 -t example/Api
php -S localhost:8081 -t example/Website
```

## Project structure

```
public/index.php       # Entry point
public/router.php      # Dev server router
src/                   # Framework core (Luminus\)
  App.php              # Bootstrap & orchestrator
  Container.php        # DI container with autowiring
  Request.php          # $_GET, $_POST, $_SERVER, JSON
  Response.php         # HTTP response builder
  Router.php           # Pattern matching + injection
  View.php             # Plain PHP template engine
  Database.php         # PDO wrapper
   QueryBuilder.php     # Fluent query builder
   Middleware.php       # Middleware interface
   Async.php            # Fiber-based concurrency
   helpers.php          # env(), e(), csrf_token(), csrf_field(), session() helpers
   Session.php          # Secure session manager
   StartSessionMiddleware.php # Session bootstrap middleware
   CsrfMiddleware.php   # CSRF protection middleware
   SecurityHeadersMiddleware.php # Security headers middleware
config/app.php         # Configuration
routes/web.php         # Route definitions
views/                 # PHP templates
app/                   # Your application code
storage/               # Logs, cache, sessions
```

## Routes

```php
$router->get('/', fn(Request $req) => 'Hello');
$router->get('/users/{id}', fn(string $id) => "User $id");
$router->post('/users', fn(Request $req) => $req->input('name'));

// Type-hinted dependencies auto-injected
$router->get('/page', fn(View $view) => $view->render('page'));
$router->get('/api', fn(Response $res) => $res->json(['ok' => true]));

// Controller classes resolved via container
$router->get('/admin', [AdminController::class, 'index']);

// Middleware wraps the route handler
$router->addMiddleware(new class implements Luminus\Middleware {
    public function handle(Request $req, callable $next): Response {
        // ... before handler ...
        $response = $next($req);
        // ... after handler ...
        return $response;
    }
});
```

## Views

```php
<?php // views/page.php
$this->layout('layouts.main') ?>
<?php $this->section('content') ?>
<h1><?= $title ?></h1>
<?php $this->endSection() ?>
```

```php
<?php // views/layouts/main.php ?>
<html><body>
<?php $this->renderSection('content') ?>
</body></html>
```

## Database

```php
$db->query('SELECT * FROM users WHERE active = ?', [1]);
$db->table('users')->where('email', '=', $email)->first();
$db->table('posts')->insert(['title' => 'Hello', 'body' => '...']);
$db->table('posts')->where('id', '=', 5)->delete();
```

## Container

```php
$container->singleton(Logger::class, fn() => new Logger('/tmp/app.log'));
$service = $container->get(Logger::class); // singleton

// Autowiring resolves constructor dependencies
$controller = $container->get(ProductController::class);
```

## Middleware

```php
// Implement the Middleware interface
class AuthMiddleware implements Luminus\Middleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!$request->header('X-Auth-Token')) {
            return (new Response())->status(401)->json(['error' => 'Unauthorized']);
        }
        return $next($request);
    }
}

// Register via App or Router
$app->addMiddleware(new AuthMiddleware());
// or: $router->addMiddleware(new AuthMiddleware());
```

Middleware executes in registration order (first added = outermost).

## Async (PHP 8.1+ Fibers)

```php
$results = Async::all([
    'users' => fn() => $db->query('SELECT * FROM users'),
    'posts' => fn() => $db->query('SELECT * FROM posts'),
]);

$responses = Async::httpGet([
    'github' => 'https://api.github.com',
    'json' => 'https://jsonplaceholder.typicode.com/posts',
]);
```

## Config

```php
$app->config('debug');     // true
$app->config('database');  // array
env('APP_ENV', 'production');
```

## Security

Luminus provides built-in, lightweight security measures:

### Output Escaping (XSS Protection)

Use the global `e()` helper in your views to escape variable outputs:

```php
<h1><?= e($title) ?></h1>
```

### CSRF Protection & Sessions

Enable CSRF protection and session management by registering the middlewares on the Router:

```php
use Luminus\StartSessionMiddleware;
use Luminus\CsrfMiddleware;

// Start session must run first
$router->addMiddleware(new StartSessionMiddleware());
$router->addMiddleware(new CsrfMiddleware(except: ['/api/*']));
```

In your forms, include the hidden CSRF input using `csrf_field()`:

```html
<form method="POST" action="/submit">
    <?= csrf_field() ?>
    <button type="submit">Submit</button>
</form>
```

### Security Headers

Enforce defense-in-depth headers (Clickjacking, MIME sniffing, and Referrer policy defense):

```php
use Luminus\SecurityHeadersMiddleware;

$router->addMiddleware(new SecurityHeadersMiddleware());
```

## License

MIT
