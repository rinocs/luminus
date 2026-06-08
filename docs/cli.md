# CLI

## Dev server

```bash
bin/dev              # default localhost:8080
bin/dev 9090         # custom port
bin/dev 9090 0.0.0.0 # custom host + port
```

## Console REPL

```bash
bin/console
```

Opens an interactive PHP shell with the app bootstrapped:

```
╔══════════════════════════════════╗
║  Luminus Console                 ║
╚══════════════════════════════════╝

Available: $app, $container

luminus> $app->config('debug');
true
luminus> $container->has(Luminus\View::class);
true
luminus> exit
```

**One-shot commands:**

```bash
bin/console 'echo $app->config("env");'
```

## Setup

```bash
bin/setup
```

Creates `.env`, runs `composer dump-autoload`, creates storage directories with proper permissions.

## Makefile

```bash
make dev      # start server
make console  # open REPL
make setup    # first-time setup
make build    # build Docker image
make up       # docker compose up
make down     # docker compose down
```
