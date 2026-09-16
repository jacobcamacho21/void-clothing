FROM php:8.4-cli-alpine

# Install system dependencies & PostgreSQL driver
RUN apk add --no-cache git unzip libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pgsql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# Install dependencies without running artisan scripts during build
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs --no-scripts

# Create start script that clears old config caches before launching
RUN echo '#!/bin/sh' > /app/entrypoint.sh && \
    echo 'php artisan config:clear' >> /app/entrypoint.sh && \
    echo 'php artisan cache:clear' >> /app/entrypoint.sh && \
    echo 'php artisan migrate --force' >> /app/entrypoint.sh && \
    echo 'exec php artisan serve --host=0.0.0.0 --port=8000' >> /app/entrypoint.sh && \
    chmod +x /app/entrypoint.sh

EXPOSE 8000

CMD ["/app/entrypoint.sh"]