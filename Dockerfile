# --- ÉTAPE 1 : Base avec PHP 8.1 ---
# On choisit 8.1 pour assurer la compatibilité avec Sanctum 3.3
FROM php:8.1-fpm AS base

WORKDIR /var/www

# Installation des dépendances système indispensables pour Laravel et ses packages (Excel, Avatars)
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

# Configuration pour éviter les erreurs de mémoire durant le build
ENV COMPOSER_MEMORY_LIMIT=-1
ENV COMPOSER_ALLOW_SUPERUSER=1

# Copie des fichiers de dépendances
COPY composer.json composer.lock* ./

# Installation des dépendances sans scripts pour éviter les blocages
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --ignore-platform-reqs

# --- ÉTAPE 2 : Compilation des Assets (Vite) ---
FROM node:16 AS vite-stage
WORKDIR /app
COPY . .
RUN npm install && npm run build

# --- ÉTAPE 3 : Image de Production Finale ---
FROM php:8.1-fpm AS production

WORKDIR /var/www

# On réinstalle les extensions nécessaires dans l'image finale
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libzip-dev libicu-dev zip unzip \
    && docker-php-ext-install gd zip pdo_mysql bcmath intl \
    && rm -rf /var/lib/apt/lists/*

# Copie du code source complet
COPY . .

# On récupère les dossiers générés dans les étapes précédentes
COPY --from=base /var/www/vendor ./vendor
COPY --from=vite-stage /app/public/build ./public/build

# Finalisation de l'autoloader
COPY --from=base /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --optimize --no-dev

# Gestion des permissions pour Laravel
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
RUN chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# --- CONFIGURATION POUR RENDER ---
# On utilise le port 10000 qui est le standard sur Render
EXPOSE 10000

# Commande de démarrage : on lance le serveur intégré de Laravel
# Cela permet à Render de détecter un port HTTP ouvert
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=10000"]
