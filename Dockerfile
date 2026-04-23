# Dockerfile

FROM php:8.2-cli

# Set working directory
WORKDIR /var/www/html

# Install dependencies
RUN apt-get update && apt-get install -y \
    curl \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Laravel dependencies
COPY ./composer.json ./
COPY ./composer.lock ./
RUN composer install --no-autoloader

# Copy application code
COPY . .

# Optimize autoloader
RUN composer dump-autoload --optimize

# Expose port 10000
EXPOSE 10000

# Command to run php server
CMD ["php", "-S", "0.0.0.0:10000", "-t", "."]