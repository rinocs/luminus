FROM php:8.2-fpm-alpine AS base

RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    bash \
    && docker-php-ext-install pdo pdo_mysql

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

RUN mkdir -p storage/framework storage/logs storage/sessions \
    && chmod -R 775 storage \
    && chown -R www-data:www-data storage

RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor.d/luminus.ini

EXPOSE 80

CMD ["supervisord", "-c", "/etc/supervisor.d/luminus.ini"]

# --- dev stage ---
FROM base AS dev

COPY docker/php-dev.ini /usr/local/etc/php/conf.d/luminus.ini

RUN composer install --optimize-autoloader --no-interaction

CMD ["php", "-S", "0.0.0.0:80", "-t", "public", "public/router.php"]
