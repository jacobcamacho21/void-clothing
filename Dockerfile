FROM richarvey/nginx-php-fpm:latest

# Set working directory
COPY . /var/www/html

# Image Configuration
ENV WEBROOT="/var/www/html/public"
ENV PHP_ERRORS_STDERR="1"
ENV RUN_CLI="false"
ENV REAL_IP_HEADER="1"

# Install dependencies and setup Laravel
RUN composer install --no-dev --optimize-autoloader

# Set permissions for storage & cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80