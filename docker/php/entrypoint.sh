#!/bin/sh
set -e

# Prepare the database before PHP-FPM starts serving requests.
# With more than one replica this would move to a separate release step.
if [ "$1" = "php-fpm" ]; then
    bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

    if [ "${LOAD_DEMO_DATA:-0}" = "1" ]; then
        bin/console app:demo-data
    fi
fi

exec "$@"
