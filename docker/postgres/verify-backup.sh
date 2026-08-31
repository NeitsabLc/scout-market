#!/bin/sh

set -eu
umask 077

archive_base=${1:?Indiquez le chemin du fichier .dump.age à restaurer}
: "${BACKUP_AGE_IDENTITY_FILE:?BACKUP_AGE_IDENTITY_FILE doit désigner la clé privée age}"
: "${POSTGRES_HOST:=database}"
: "${POSTGRES_USER:?POSTGRES_USER doit contenir le rôle administrateur de restauration}"
: "${POSTGRES_DB:?POSTGRES_DB doit contenir le nom de la base source}"
: "${RESTORE_DATABASE_NAME:=${POSTGRES_DB}_restore_check}"

sauvegarde_claire=$(mktemp /tmp/scout-market-restore.dump.XXXXXX)

nettoyer() {
    rm -f "$sauvegarde_claire"
    dropdb --host="$POSTGRES_HOST" --username="$POSTGRES_USER" \
        --if-exists --force "$RESTORE_DATABASE_NAME" >/dev/null 2>&1 || true
}
trap nettoyer EXIT INT TERM

age --decrypt --identity "$BACKUP_AGE_IDENTITY_FILE" --output "$sauvegarde_claire" "$archive_base"
dropdb --host="$POSTGRES_HOST" --username="$POSTGRES_USER" --if-exists --force "$RESTORE_DATABASE_NAME"
createdb --host="$POSTGRES_HOST" --username="$POSTGRES_USER" --owner="$POSTGRES_USER" "$RESTORE_DATABASE_NAME"
pg_restore --host="$POSTGRES_HOST" --username="$POSTGRES_USER" \
    --dbname="$RESTORE_DATABASE_NAME" --exit-on-error --no-owner --no-acl \
    "$sauvegarde_claire"

source_count=$(psql --host="$POSTGRES_HOST" --username="$POSTGRES_USER" \
    --dbname="$POSTGRES_DB" --tuples-only --no-align \
    --command='SELECT COUNT(*) FROM scout_market.utilisateur')
restore_count=$(psql --host="$POSTGRES_HOST" --username="$POSTGRES_USER" \
    --dbname="$RESTORE_DATABASE_NAME" --tuples-only --no-align \
    --command='SELECT COUNT(*) FROM scout_market.utilisateur')
test "$restore_count" = "$source_count"

echo "Restauration vérifiée dans $RESTORE_DATABASE_NAME ($restore_count utilisateurs)."
