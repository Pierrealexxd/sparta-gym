FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM php:8.3-cli-alpine
WORKDIR /app

RUN apk add --no-cache icu-dev oniguruma-dev freetype-dev libpng-dev libjpeg-turbo-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring intl gd zip \
    && docker-php-ext-enable opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-progress

COPY . .
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress

COPY --from=assets /app/public/build /app/public/build
COPY docker/certs/aiven-ca.pem /app/certs/aiven-ca.pem
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh \
    && mkdir -p storage/logs bootstrap/cache \
    && chmod -R 777 storage bootstrap/cache
EXPOSE 8000
ENV PHP_CLI_SERVER_WORKERS=4
ENTRYPOINT ["/entrypoint.sh"]
