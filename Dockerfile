# --- Étape de base (Dépendances PHP) ---
FROM php:8.0-fpm AS base

WORKDIR /var/www

# Installation des dépendances système (Debian)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    git \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install gd zip pdo_mysql \
 && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copie uniquement les fichiers de dépendances pour optimiser le cache
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader

# --- Étape Vite (Assets) ---
FROM node:16 AS vite
WORKDIR /app
COPY . .
RUN npm install && npm run build

# --- Étape Production ---
FROM php:8.0-fpm AS production
WORKDIR /var/www

# On réinstalle les extensions PHP nécessaires ici aussi si on change d'image,
# MAIS le plus simple est de repartir de l'image 'base' pour garder la compatibilité.
COPY --from=base /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d
COPY --from=base /usr/local/lib/php/extensions /usr/local/lib/php/extensions
# (Ou plus simplement, utilise l'image 'base' comme base de prod)

COPY . .
COPY --from=base /var/www/vendor ./vendor
COPY --from=vite /app/public/build ./public/build 

# Finalisation de l'autoloader
COPY --from=base /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --optimize --no-dev

# Permissions
RUN chown -R www-data:www-data storage bootstrap/cache
RUN chmod -R 775 storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
