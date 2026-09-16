FROM php:8.4-cli-alpine

# Install system dependencies & PostgreSQL driver
RUN apk add --no-cache git unzip libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs --no-scripts

# Create start script: wipe cache, run fresh migrations with seeders, and start app
RUN echo '#!/bin/sh' > /app/entrypoint.sh && \
    echo 'php artisan config:clear' >> /app/entrypoint.sh && \
    echo 'php artisan cache:clear' >> /app/entrypoint.sh && \
    echo 'php artisan migrate:fresh --seed --force' >> /app/entrypoint.sh && \
    echo 'exec php artisan serve --host=0.0.0.0 --port=8000' >> /app/entrypoint.sh && \
    chmod +x /app/entrypoint.sh

EXPOSE 8000

CMD ["/app/entrypoint.sh"]