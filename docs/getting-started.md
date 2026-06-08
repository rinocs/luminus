# Getting started

## Requirements

- PHP 8.2+
- Composer
- PDO + `pdo_mysql` (optional, for database)

## Installation

```bash
git clone <repo> luminus
cd luminus
make setup
```

`make setup` creates `.env`, runs `composer dump-autoload`, and sets up storage directories.

## Development server

```bash
make dev
# or
bin/dev
# or
php -S localhost:8080 -t public public/router.php
```

The router file (`public/router.php`) serves static files when they exist and routes everything else through the application.

## First route

Open `routes/web.php`:

```php
$router->get('/', function () {
    return 'Hello, world!';
});

$router->get('/hello/{name}', function (Request $req, string $name): string {
    return "<h1>Hello, {$name}!</h1>";
});
```

## Configuration

Environment variables in `.env`:

```env
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8080

DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_DATABASE=luminus
DB_USERNAME=root
DB_PASSWORD=
```

## Error handling

`App::run()` wraps the request in try/catch. When `APP_DEBUG=true`, exceptions show a detailed trace. In production (`APP_DEBUG=false`), a generic 500 page is returned.

```env
APP_DEBUG=true    # detailed error page
APP_DEBUG=false   # generic 500 page
```

## Configuration

Access config anywhere:

```php
$app->config('debug');
$app->config('database');
```

The `env()` helper reads from `$_ENV` or `getenv()`:

```php
env('APP_DEBUG', false);   // second param = default
```
