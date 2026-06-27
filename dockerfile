FROM php:8.3-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git curl libpq-dev libzip-dev zip unzip postgresql-client \
    && docker-php-ext-install pdo pdo_pgsql zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN chmod +x ./build.sh

EXPOSE 8080

CMD ["sh", "-c", "./build.sh && php artisan serve --host=0.0.0.0 --port=8080"]