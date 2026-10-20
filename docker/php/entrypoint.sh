#!/bin/sh
set -e

if [ "$APP_ENV" = "prod" ] && [ -z "$APP_SECRET" ]; then
    echo "APP_SECRET must be set in production." >&2
    exit 1
fi

# Prepare the database before PHP-FPM starts serving requests.
# With more than one replica this would move to a separate release step.
if [ "$1" = "php-fpm" ]; then
    bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

    if [ "${LOAD_DEMO_DATA:-0}" = "1" ]; then
        bin/console app:load-demo-data
    fi
fi

exec "$@"
