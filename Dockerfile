FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM composer:2 AS deps
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-progress
COPY . .
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-progress

FROM php:8.3-cli-alpine
WORKDIR /app
RUN apk add --no-cache icu-dev oniguruma-dev \
    && docker-php-ext-install pdo_mysql mbstring intl \
    && docker-php-ext-enable opcache
COPY --from=deps /app /app
COPY --from=assets /app/public/build /app/public/build
COPY docker/certs/aiven-ca.pem /app/certs/aiven-ca.pem
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh \
    && mkdir -p storage/logs bootstrap/cache \
    && chmod -R 777 storage bootstrap/cache
EXPOSE 8000
ENV PHP_CLI_SERVER_WORKERS=4
ENTRYPOINT ["/entrypoint.sh"]
