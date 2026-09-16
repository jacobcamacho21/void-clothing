FROM php:8.4-cli-alpine

# Install system dependencies & PostgreSQL driver
RUN apk add --no-cache git unzip libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# Install PHP dependencies without version conflicts
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

EXPOSE 8000

CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8000