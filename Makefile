APP_PORT ?= 8080
NGINX_PORT ?= 8081

.PHONY: dev build up down shell console setup env

dev:
	php -S localhost:$(APP_PORT) -t public public/router.php

build:
	docker build -t luminus --target base .

up:
	docker compose up -d

up-prod:
	docker compose --profile prod up -d

up-db:
	docker compose --profile db up -d

down:
	docker compose down --remove-orphans

shell:
	php -a

console:
	php -r '
		require "vendor/autoload.php";
		$$app = new Luminus\App();
		$$app->loadEnv();
		$$app->loadConfig(require "config/app.php");
		echo "Luminus console ready.\n";
		$$_ = function() use($$app) { return $$app; };
	' -a

setup:
	@cp -n .env.example .env 2>/dev/null || true
	composer dump-autoload
	@mkdir -p storage/framework storage/logs storage/sessions
	@chmod -R 775 storage
	@echo "Done. Run: make dev"

test:
	@if [ -f "vendor/bin/phpunit" ]; then vendor/bin/phpunit; else echo "No test suite configured."; fi

clean:
	rm -rf vendor storage/framework/* storage/logs/*

distclean: clean
	rm -f .env
