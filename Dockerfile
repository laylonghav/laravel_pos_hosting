# --- STAGE 1: Build Dependencies and Prepare Application ---
# Use the FPM (FastCGI Process Manager) base image instead of CLI.
# This image is designed for web server environments.
FROM php:8.2-fpm

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
# We use '&&' to chain commands, minimizing layers and cleaning up afterward.
RUN apt-get update && apt-get install -y \
    git \
    curl \
    unzip \
    zip \
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
    # FIX: Explicitly enable the MongoDB extension by creating a .ini file
    && echo "extension=mongodb.so" > /usr/local/etc/php/conf.d/mongodb.ini \
    # Clean up APT caches
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install Node.js 18 and npm
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy application files
COPY . .

# Set permissions for Laravel storage and cache
# Use the 'www-data' user, which is the default for the FPM image
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Install PHP dependencies (using --no-dev flag is crucial for production)
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# Install JS dependencies and compile assets
RUN npm install && npm run build

# Expose port (good practice)
EXPOSE 8000

# FINAL FIX: Use the 'fpm' user to run the server. 
# We use 'exec' to ensure signals are passed correctly, and we force the $PORT 
# for Railway, while running the development server (since we aren't using Nginx/Apache).
CMD ["sh", "-c", "exec /usr/sbin/php-fpm -F & php artisan serve --env=production --host=0.0.0.0 --port=${PORT}"]