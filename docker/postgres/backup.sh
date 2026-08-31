#!/bin/sh

set -eu
umask 077

repertoire=/backups
retention_minutes=10080
: "${BACKUP_AGE_RECIPIENT:?BACKUP_AGE_RECIPIENT doit contenir la clé publique age de sauvegarde}"

mkdir -p "$repertoire"

while true; do
    horodatage=$(date -u +%Y%m%dT%H%M%SZ)
    base_claire=$(mktemp "/tmp/scout-market-$horodatage.dump.XXXXXX")
    base_temp="$repertoire/scout-market-$horodatage.dump.age.tmp"
    base_finale="$repertoire/scout-market-$horodatage.dump.age"

    nettoyer() {
        rm -f "$base_claire" "$base_temp"
    }
    trap nettoyer EXIT INT TERM

    pg_dump --format=custom --no-owner --no-acl \
        --host=database --username="$POSTGRES_USER" --dbname="$POSTGRES_DB" \
        --file="$base_claire"
    age --encrypt --recipient "$BACKUP_AGE_RECIPIENT" --output "$base_temp" "$base_claire"
    chmod 0600 "$base_temp"
    mv "$base_temp" "$base_finale"
    rm -f "$base_claire"
    trap - EXIT INT TERM

    find "$repertoire" -type f \
        -name 'scout-market-*.dump.age' \
        -mmin "+$retention_minutes" -delete

    if [ "${BACKUP_ONCE:-0}" = "1" ]; then
        exit 0
    fi

    sleep 86400
done
