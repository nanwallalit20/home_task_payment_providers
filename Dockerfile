FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy existing application directory contents
COPY . /var/www/html

# Copy existing application directory permissions
COPY --chown=www-data:www-data . /var/www/html

# Change current user to www
USER www-data

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Switch back to root to configure Apache
USER root

# Copy Apache configuration
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Enable the site
RUN a2ensite 000-default

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage /var/www/html/bootstrap/cache

# Create startup script
RUN echo '#!/bin/bash\n\
# Wait for database to be ready\n\
echo "Waiting for database..."\n\
while ! nc -z db 3306; do\n\
  sleep 1\n\
done\n\
echo "Database is ready!"\n\
\n\
# Switch to application directory\n\
cd /var/www/html\n\
\n\
# Create .env file if it doesnt exist\n\
if [ ! -f .env ]; then\n\
  echo "Creating .env file..."\n\
  cat > .env << EOF\n\
APP_NAME=${APP_NAME:-Laravel}\n\
APP_ENV=${APP_ENV:-production}\n\
APP_KEY=\n\
APP_DEBUG=${APP_DEBUG:-false}\n\
APP_URL=${APP_URL:-http://localhost}\n\
\n\
DB_CONNECTION=${DB_CONNECTION:-mysql}\n\
DB_HOST=${DB_HOST:-127.0.0.1}\n\
DB_PORT=${DB_PORT:-3306}\n\
DB_DATABASE=${DB_DATABASE:-laravel}\n\
DB_USERNAME=${DB_USERNAME:-root}\n\
DB_PASSWORD=${DB_PASSWORD:-}\n\
\n\
LOG_CHANNEL=${LOG_CHANNEL:-stack}\n\
CACHE_STORE=${CACHE_STORE:-file}\n\
SESSION_DRIVER=${SESSION_DRIVER:-file}\n\
QUEUE_CONNECTION=${QUEUE_CONNECTION:-sync}\n\
\n\
JWT_SECRET=\n\
JWT_ALGO=${JWT_ALGO:-HS256}\n\
JWT_TTL=${JWT_TTL:-60}\n\
JWT_REFRESH_TTL=${JWT_REFRESH_TTL:-20160}\n\
JWT_BLACKLIST_ENABLED=${JWT_BLACKLIST_ENABLED:-true}\n\
EOF\n\
fi\n\
\n\
# Generate application key if not set\n\
if ! grep -q "APP_KEY=base64:" .env; then\n\
  echo "Generating application key..."\n\
  php artisan key:generate --force\n\
fi\n\
\n\
# Generate JWT secret if not set\n\
if ! grep -q "JWT_SECRET=" .env || [ -z "$(grep "JWT_SECRET=" .env | cut -d= -f2)" ]; then\n\
  echo "Generating JWT secret..."\n\
  php artisan jwt:secret --force\n\
fi\n\
\n\
# Run migrations\n\
echo "Running migrations..."\n\
php artisan migrate --force\n\
\n\
# Clear and cache config\n\
php artisan config:clear\n\
php artisan config:cache\n\
\n\
# Start Apache\n\
echo "Starting Apache..."\n\
apache2-foreground\n\
' > /usr/local/bin/start.sh && chmod +x /usr/local/bin/start.sh

# Install netcat for database connection checking
RUN apt-get update && apt-get install -y netcat-traditional && rm -rf /var/lib/apt/lists/*

# Expose port 80
EXPOSE 80

# Use the startup script
CMD ["/usr/local/bin/start.sh"]
