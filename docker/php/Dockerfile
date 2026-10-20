# syntax=docker/dockerfile:1

# --- base: PHP-FPM with the extensions the app needs -------------------------
FROM php:8.5-fpm-alpine3.24 AS base

COPY --from=ghcr.io/mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql intl \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=composer:2.10 /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_HOME=/tmp/composer
WORKDIR /app
RUN chown www-data:www-data /app
USER www-data

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]

# --- dev: with dev dependencies for tests and QA tools -----------------------
FROM base AS dev

ENV APP_ENV=dev

COPY --chown=www-data:www-data backend/composer.json backend/composer.lock backend/symfony.lock ./
RUN --mount=type=cache,target=/tmp/composer/cache,uid=82,gid=82 \
    composer install --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY --chown=www-data:www-data backend/ ./
RUN composer dump-autoload

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/entrypoint.sh /usr/local/bin/app-entrypoint

# --- prod: no dev dependencies, optimized autoloader -------------------------
FROM base AS prod

ENV APP_ENV=prod

COPY --chown=www-data:www-data backend/composer.json backend/composer.lock backend/symfony.lock ./
RUN --mount=type=cache,target=/tmp/composer/cache,uid=82,gid=82 \
    composer install --no-interaction --no-scripts --no-autoloader --prefer-dist --no-dev

COPY --chown=www-data:www-data backend/ ./
# APP_SECRET and DATABASE_URL come from the runtime environment.
RUN composer dump-autoload --no-dev --classmap-authoritative \
    && APP_SECRET=build-only bin/console cache:warmup

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/php-prod.ini /usr/local/etc/php/conf.d/zz-app-prod.ini
COPY docker/php/entrypoint.sh /usr/local/bin/app-entrypoint
