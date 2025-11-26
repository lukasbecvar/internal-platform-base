#!/bin/bash
set -euo pipefail

WWWGROUP="${WWWGROUP:-33}"
WWWUSER="${WWWUSER:-33}"

# change ownership of cache directory
if [ -d "/var/www/var" ]; then
    chown -R "${WWWUSER}:${WWWGROUP}" /var/www/var
fi

exec docker-php-entrypoint "$@"
