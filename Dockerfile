# --- ÉTAPE 1 : PHP & Dépendances ---
FROM php:8.0-fpm AS base

WORKDIR /var/www

# Installation des dépendances système indispensables
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install gd zip pdo_mysql \
 && rm -rf /var/lib/apt/lists/*

# Installation de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copie des fichiers de dépendances en premier (optimisation du cache Docker)
COPY composer.json composer.lock ./

# On définit la limite de mémoire pour éviter l'Exit Code 2
ENV COMPOSER_MEMORY_LIMIT=-1

# Installation des dépendances PHP sans lancer les scripts de Laravel (qui pourraient planter ici)
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction

# --- ÉTAPE 2 : Compilation des Assets (Vite) ---
FROM node:16 AS vite-stage
WORKDIR /app
COPY . .
RUN npm install && npm run build

# --- ÉTAPE 3 : Image Finale de Production ---
FROM php:8.0-fpm AS production

WORKDIR /var/www

# On récupère les extensions PHP compilées dans l'étape 'base'
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev libzip-dev zip unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd zip pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Copie du code source et des dépendances
COPY . .
COPY --from=base /var/www/vendor ./vendor
COPY --from=base /usr/bin/composer /usr/bin/composer
COPY --from=vite-stage /app/public/build ./public/build

# Finalisation de l'autoloader (génère les chemins de classes optimisés)
RUN composer dump-autoload --optimize --no-dev

# Droits d'accès pour Laravel
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
RUN chmod -R 775 /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000

# Sur Render, si tu n'as pas de serveur Nginx séparé, tu peux utiliser 
# le serveur intégré de PHP pour tester, mais php-fpm reste le standard.
CMD ["php-fpm"]
