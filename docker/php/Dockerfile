FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
    libpq-dev libzip-dev zip unzip git curl bash supervisor \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql pgsql zip opcache bcmath pcntl \
    && docker-php-ext-enable opcache bcmath pcntl

COPY docker/php/conf.d/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/conf.d/php.ini /usr/local/etc/php/conf.d/99-custom.ini

FROM composer:latest AS composer-build
WORKDIR /app
COPY backend/composer.json backend/composer.lock* /app/
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

FROM base AS app
WORKDIR /var/www/backend

COPY --from=composer-build /app/vendor /var/www/backend/vendor
COPY backend/ /var/www/backend/

RUN composer dump-autoload --no-dev --optimize \
    && php artisan event:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan config:cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

USER www-data
EXPOSE 9000
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php-fpm -t || exit 1
CMD ["php-fpm"]

FROM base AS worker
WORKDIR /var/www/backend
COPY --from=composer-build /app/vendor /var/www/backend/vendor
COPY backend/ /var/www/backend/
RUN chown -R www-data:www-data storage bootstrap/cache
USER www-data
HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD php artisan queue:status || exit 1
CMD ["php", "artisan", "queue:work", "--sleep=3", "--tries=3", "--max-time=3600"]
