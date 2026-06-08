FROM php:8.2-fpm-alpine

RUN apk add --no-cache nginx supervisor curl \
    && docker-php-ext-install pdo pdo_mysql

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN mkdir -p storage/framework storage/logs storage/sessions \
    && chmod -R 775 storage \
    && chown -R www-data:www-data storage

RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY docker/nginx.conf /etc/nginx/http.d/default.conf

EXPOSE 80

CMD php-fpm -D && nginx -g "daemon off;"
