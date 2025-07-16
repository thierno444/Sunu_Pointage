FROM php:8.3-cli

# Dépendances système
RUN apt-get update && apt-get install -y \
    unzip git curl libzip-dev libpng-dev libssl-dev pkg-config libcurl4-openssl-dev \
    && docker-php-ext-install zip pdo pdo_mysql

# Extension Mongo
RUN pecl install mongodb-1.20.0 && docker-php-ext-enable mongodb

# Installer Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

# Installer les dépendances Laravel
RUN composer install

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
