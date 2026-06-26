FROM php:8.3-fpm-alpine

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    bash \
    supervisor \
    redis 2>/dev/null || true && \  # redis cli optional for debugging
    \
    # Install PHP extensions required for Laravel + Supabase
    docker-php-ext-install \
        pdo_pgsql \
        pgsql \
        zip \
        opcache \
        && \
    docker-php-ext-enable opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/backend

# Copy application code
COPY . .

# Install dependencies (no dev dependencies in production)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set permissions for Laravel storage/bootstrap
RUN chown -R www-data:www-data /var/www/backend/storage /var/www/backend/bootstrap/cache || true && \
    chmod -R 775 /var/www/backend/storage /var/www/backend/bootstrap/cache 2>/dev/null || true

# Expose PHP-FPM port
EXPOSE 9000

CMD ["php-fpm"]
