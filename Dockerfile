# Build the Vite assets.
FROM node:22-alpine AS frontend
WORKDIR /app
COPY . .
RUN npm ci && npm run build

# Run Laravel on Apache.
FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev \
        libonig-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        mbstring \
        pdo_mysql \
        pdo_sqlite \
        zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies before copying the rest of the application for better layer caching.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

COPY . .
COPY --from=frontend /app/public/build ./public/build

# Serve Laravel's public directory and ensure Laravel can write its runtime files.
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && chown -R www-data:www-data storage bootstrap/cache \
    && php artisan package:discover --ansi

ENV APP_ENV=production \
    APP_DEBUG=false \
    PORT=10000

EXPOSE 10000

# Render provides PORT at runtime; make Apache listen on that port.
CMD ["sh", "-c", "sed -i \"s/^Listen 80$/Listen ${PORT:-10000}/\" /etc/apache2/ports.conf && sed -i \"s/<VirtualHost \\*:80>/<VirtualHost *:${PORT:-10000}>/\" /etc/apache2/sites-available/000-default.conf && exec apache2-foreground"]
