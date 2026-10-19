# syntax=docker/dockerfile:1

# --- base: PHP-FPM with the extensions the app needs -------------------------
FROM php:8.5-fpm-alpine AS base

COPY --from=ghcr.io/mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql intl

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/app-entrypoint

ENV COMPOSER_HOME=/tmp/composer
WORKDIR /app
RUN chown www-data:www-data /app

# The app never runs as root, not even the entrypoint.
USER www-data

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]

# --- dev: local stack, includes dev dependencies so tests and QA tools run ----
FROM base AS dev

ENV APP_ENV=dev

COPY --chown=www-data:www-data backend/composer.json backend/composer.lock backend/symfony.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY --chown=www-data:www-data backend/ ./
RUN composer dump-autoload

# --- prod: no dev dependencies, optimized autoloader -------------------------
FROM base AS prod

ENV APP_ENV=prod

COPY --chown=www-data:www-data backend/composer.json backend/composer.lock backend/symfony.lock ./
RUN composer install --no-interaction --no-scripts --no-autoloader --prefer-dist --no-dev

COPY --chown=www-data:www-data backend/ ./
# Configuration (APP_SECRET, DATABASE_URL) comes from real environment variables at runtime.
RUN composer dump-autoload --no-dev --classmap-authoritative \
    && APP_SECRET=build-only bin/console cache:warmup
