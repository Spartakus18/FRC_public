# Dockerfile for Laravel 9 Application

## Base Stage
FROM php:8.0-fpm AS base

# Set working directory
WORKDIR /var/www

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    npm \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install gd zip \
 && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

## Dependencies Installation
COPY ./composer.json ./
COPY ./composer.lock ./
RUN composer install --no-dev --optimize-autoloader

# Copy application code
COPY . .

## Vite Assets Build Stage
FROM node:16 AS vite
WORKDIR /var/www
COPY --from=base /var/www .

# Install npm dependencies and build assets
RUN npm install && npm run build

## Production Stage
FROM php:8.0-fpm-alpine AS production
WORKDIR /var/www
COPY --from=base /var/www .
COPY --from=vite /var/www/public/dist ./public/dist

# Set proper permissions for Laravel
RUN chown -R www-data:www-data storage bootstrap/cache
RUN chmod -R 775 storage bootstrap/cache

# Laravel configuration
EXPOSE 9000
CMD ["php-fpm"]