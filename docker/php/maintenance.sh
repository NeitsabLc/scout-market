#!/bin/sh

set -eu

while true; do
    php bin/console app:securite:purger-jetons-expires --env=prod --no-debug

    if [ "${MAINTENANCE_ONCE:-0}" = "1" ]; then
        exit 0
    fi

    sleep 86400
done
