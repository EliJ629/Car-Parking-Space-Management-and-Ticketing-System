FROM node:22-slim AS assets
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

FROM php:8.4-cli

RUN apt-get update && apt-get install -y git unzip libzip-dev libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql zip bcmath \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .
COPY --from=assets /app/public/build ./public/build

# Ensure any local dev hot file is removed so Laravel resolves compiled assets
RUN rm -f public/hot

RUN composer install --no-dev --optimize-autoloader --no-interaction
RUN chmod -R 775 storage bootstrap/cache

# Allow PHP CLI built-in server to handle concurrent static asset requests
ENV PHP_CLI_SERVER_WORKERS=4

EXPOSE 10000

CMD php artisan config:clear && php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=${PORT:-10000} --no-reload