# Luminus

Zero external dependencies. PHP 8.2+.

## Philosophy

- No facades, no service providers, no artisan
- Every class does one thing, readable top to bottom
- Compose what you need, ignore the rest
- DI container with autowiring, not service locators

## Structure

```
public/index.php       # Entry point
public/router.php      # Dev server router (static files + app)
src/
  App.php              # Bootstrap
  Container.php        # DI container
  Request.php          # Superglobal wrapper
  Response.php         # HTTP response builder
  Router.php           # Pattern matching + auto-injection
  View.php             # Template engine
  Database.php         # PDO wrapper
  QueryBuilder.php     # Fluent query builder
   Middleware.php       # Middleware interface
   Async.php            # Fiber-based concurrency
   helpers.php          # env() function
config/app.php         # Configuration
routes/web.php         # Route definitions
views/                 # PHP templates
```

~800 lines total.

## Docs

| Guide | What you'll find |
|---|---|
| [getting-started.md](getting-started.md) | Setup, config, first route |
 | [routing.md](routing.md) | Route patterns, handlers, middleware, method spoofing |
| [request-response.md](request-response.md) | Request input, JSON, files, Response builder |
| [views.md](views.md) | Templates, layouts, sections, data |
| [container.md](container.md) | DI container, autowiring, binding |
| [database.md](database.md) | PDO wrapper, query builder, transactions |
| [async.md](async.md) | Fibers, concurrent HTTP, parallel tasks |
| [docker-deploy.md](docker-deploy.md) | Docker, compose, Makefile, production |
| [cli.md](cli.md) | Console REPL, dev server, scripts |

## Quick start

```bash
make setup    # or: bin/setup
make dev      # or: bin/dev
```

## Components

| Component | File | Purpose |
|---|---|---|
| App | `src/App.php` | Loads config, routes, runs the app |
| Container | `src/Container.php` | Autowiring DI container |
| Router | `src/Router.php` | URI matching + handler injection |
| Request | `src/Request.php` | `$_GET`, `$_POST`, `$_SERVER`, JSON body |
| Response | `src/Response.php` | Status, headers, JSON, redirect |
| View | `src/View.php` | Templates with layouts and sections |
| Middleware | `src/Middleware.php` | Middleware interface (PSR-15-like) |
| Database | `src/Database.php` | PDO wrapper, transactions |
| QueryBuilder | `src/QueryBuilder.php` | Fluent SELECT/INSERT/UPDATE/DELETE |
| Async | `src/Async.php` | Fibers, concurrent HTTP, task collection |
