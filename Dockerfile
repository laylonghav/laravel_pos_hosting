# Use official PHP 8.2 FPM image
FROM php:8.2-fpm-bullseye

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libssl-dev \
    ca-certificates \
    zip \
    unzip \
    && update-ca-certificates \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd

# Install MongoDB PHP extension
RUN pecl install mongodb \
    && echo "extension=mongodb.so" > /usr/local/etc/php/conf.d/mongodb.ini

# Install Composer
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy Laravel project
COPY . .

# Install composer dependencies
RUN composer install --no-dev --optimize-autoloader

# No artisan commands here! (Render will use .env)
# DON'T RUN: php artisan key:generate

EXPOSE 10000

CMD php artisan serve --host=0.0.0.0 --port=10000
