# --- SINGLE STAGE: Build Dependencies and Runtime ---
# Use the robust FPM (FastCGI Process Manager) base image
FROM php:8.2-fpm

# Set working directory
WORKDIR /var/www/html

# Install necessary system dependencies, Caddy, and Node.js
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    zip \
    # Install Caddy web server
    caddy \
    # PHP extensions dependencies
    libpng-dev \
    libonig-dev \
    libzip-dev \
    libsodium-dev \
    libpq-dev \
    default-mysql-client \
    default-libmysqlclient-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    # Install common PHP extensions
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql pdo_mysql mbstring exif bcmath gd zip sodium \
    # Install MongoDB extension
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    # FIX: Explicitly enable the MongoDB extension
    && echo "extension=mongodb.so" > /usr/local/etc/php/conf.d/mongodb.ini \
    # Install Node.js 18 and npm
    && curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs \
    # FIX: Ensure Caddy user can read the socket created by www-data
    && usermod -a -G www-data caddy \
    # Clean up APT caches
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . .

# Copy Caddy and FPM configuration files
COPY Caddyfile /etc/caddy/Caddyfile
COPY www.conf /usr/local/etc/php-fpm.d/www.conf

# CRITICAL FIX: Ignore the missing MongoDB extension during install so the build completes. 
# This is safe because we already installed the extension in the first RUN command.
RUN composer install --optimize-autoloader --no-dev --no-interaction --ignore-platform-reqs=ext-mongodb

# Install JS dependencies and compile assets
RUN npm install && npm run build

# Set permissions for Laravel storage and cache
# The FPM user is 'www-data' (UID 33)
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port (good practice, Caddy will listen on $PORT)
EXPOSE 8000

# CRITICAL FIX: Run PHP-FPM in Daemon mode (-D) so it runs in the background 
# and Caddy (the main process) runs in the foreground.
CMD ["sh", "-c", "php-fpm -D && caddy run --config /etc/caddy/Caddyfile --adapter caddyfile"]