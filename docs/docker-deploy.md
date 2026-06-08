# Docker & deployment

## Production image

Build with Nginx + PHP-FPM + Supervisor:

```bash
docker build -t luminus .
docker run -p 80:80 luminus
```

The image uses `php:8.2-fpm-alpine` as base, installs Nginx and Supervisor, runs `composer install --no-dev`, and starts both PHP-FPM and Nginx under Supervisor.

## Docker Compose

**Development:**

```bash
make up
```

Runs the app with PHP's built-in server, mounts the source directory for live reload.

**With Nginx (production-like):**

```bash
make up-prod
```

Adds an Nginx reverse proxy in front of the app.

**With MariaDB:**

```bash
make up-db
```

Starts a MariaDB 11 container alongside the app.

## Makefile

| Target | Description |
|---|---|
| `make dev` | Start built-in server on port 8080 |
| `make build` | Build the production Docker image |
| `make up` | Docker Compose (dev) |
| `make up-prod` | Docker Compose with Nginx |
| `make up-db` | Docker Compose with MariaDB |
| `make down` | Stop all containers |
| `make setup` | First-time project setup |
| `make console` | Interactive PHP REPL with app context |
| `make shell` | Plain `php -a` |
| `make clean` | Remove vendor, storage |

Override ports:

```bash
make dev APP_PORT=9090
make up APP_PORT=9090
```

## Environment

Copy `.env.example` to `.env` and set your values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://example.com
```

The `env()` helper reads from the environment. Docker Compose passes variables automatically.

## Manual deploy

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
# edit .env with production values
mkdir -p storage/framework storage/logs storage/sessions
chmod -R 775 storage
# point your web server to public/
```
