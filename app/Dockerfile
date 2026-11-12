FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev

FROM dunglas/frankenphp:1-php8.4-alpine
ARG APP_REVISION=""
ENV APP_ENV=prod APP_DEBUG=0 APP_REVISION=${APP_REVISION} SERVER_NAME=:80
WORKDIR /app
COPY --from=vendor /app /app
RUN php bin/console cache:warmup && mkdir -p var/share
