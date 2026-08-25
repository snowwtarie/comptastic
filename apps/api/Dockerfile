# Build vendor/ separately so `composer install` is only re-run when
# composer.json/composer.lock change, not on every source edit.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-progress --prefer-dist --no-autoloader
COPY . .
RUN composer dump-autoload --optimize --no-dev

# Single image running nginx + php-fpm under supervisord — simpler to operate
# on a single VPS than splitting them into two containers that both need
# access to the same public/ directory.
FROM php:8.4-fpm-alpine

RUN apk add --no-cache postgresql-dev libzip-dev icu-dev nginx supervisor \
    && docker-php-ext-install pdo_pgsql pgsql bcmath intl opcache \
    && rm -rf /var/cache/apk/*

WORKDIR /var/www/html
COPY --from=vendor /app ./

RUN chown -R www-data:www-data storage bootstrap/cache \
    && mkdir -p /run/nginx

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf", "-n"]
