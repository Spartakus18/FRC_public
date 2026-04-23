FROM php:8.1-fpm AS base

WORKDIR /var/www

RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libmagickwand-dev \
    libicu-dev \
    libpq-dev \
    zip \
    unzip \
    git \
    curl \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install gd zip pdo_pgsql bcmath intl \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

ENV COMPOSER_MEMORY_LIMIT=-1
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --ignore-platform-reqs

FROM node:16 AS vite-stage
WORKDIR /app
COPY . .
RUN npm install && npm run build

FROM php:8.1-fpm AS production
WORKDIR /var/www
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libzip-dev libpq-dev libicu-dev zip unzip \
    && docker-php-ext-install gd zip pdo_pgsql bcmath intl \
    && rm -rf /var/lib/apt/lists/*

COPY . .
COPY --from=base /var/www/vendor ./vendor
COPY --from=vite-stage /app/public/build ./public/build
COPY --from=base /usr/bin/composer /usr/bin/composer

RUN composer dump-autoload --optimize --no-dev
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
RUN chmod -R 775 /var/www/storage /var/www/bootstrap/cache

EXPOSE 10000

# Script avec Migration + Seeding
RUN echo '#!/bin/sh\n\
php artisan migrate --force\n\
php artisan db:seed --force\n\
php artisan serve --host=0.0.0.0 --port=10000' > /start.sh

RUN chmod +x /start.sh

CMD ["/start.sh"]
