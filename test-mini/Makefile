APP_PORT ?= 8080

.PHONY: dev setup

dev:
	php -S localhost:$(APP_PORT) -t public public/router.php

setup:
	@cp -n .env.example .env 2>/dev/null || true
	composer dump-autoload
	@mkdir -p storage/framework storage/logs storage/sessions
	@chmod -R 775 storage
	@echo "Done. Run: make dev"
