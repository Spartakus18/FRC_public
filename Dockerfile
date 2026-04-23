# On passe en 8.1 pour supporter Sanctum 3.3
FROM php:8.1-fpm AS base

WORKDIR /var/www

# Installation de TOUTES les extensions requises par tes packages (Excel, Avatars, etc.)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libmagickwand-dev \
    libicu-dev \
    zip \
    unzip \
    git \
    curl \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install gd zip pdo_mysql bcmath intl \
 && rm -rf /var/lib/apt/lists/*

# Installation de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configuration de l'environnement de build
ENV COMPOSER_MEMORY_LIMIT=-1
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock* ./

# On installe avec --ignore-platform-reqs au cas où une extension manque encore
# et -vvv pour voir les détails si ça échoue
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --no-interaction \
    --ignore-platform-reqs \
    -vvv

# --- Étape Vite ---
FROM node:16 AS vite-stage
WORKDIR /app
COPY . .
RUN npm install && npm run build

# --- Image Finale ---
FROM php:8.1-fpm AS production
WORKDIR /var/www

# Réinstallation minimale des libs pour la prod
RUN apt-get update && apt-get install -y libpng-dev libjpeg-dev libzip-dev zip unzip \
    && docker-php-ext-install gd zip pdo_mysql bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY . .
COPY --from=base /var/www/vendor ./vendor
COPY --from=base /usr/bin/composer /usr/bin/composer
COPY --from=vite-stage /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev

RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
RUN chmod -R 775 /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
