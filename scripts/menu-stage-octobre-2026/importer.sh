#!/bin/sh

set -eu

script_dir=$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd)
sql_file="$script_dir/import.sql"
mode=controle
host=web01
container=scout-market-database-1

usage() {
    echo "Usage: $0 [--apply] [--host HOTE] [--container CONTENEUR]" >&2
}

while [ "$#" -gt 0 ]; do
    case "$1" in
        --apply) mode=application ;;
        --host) shift; [ "$#" -gt 0 ] || { usage; exit 2; }; host=$1 ;;
        --container) shift; [ "$#" -gt 0 ] || { usage; exit 2; }; container=$1 ;;
        -h|--help) usage; exit 0 ;;
        *) usage; exit 2 ;;
    esac
    shift
done

[ -r "$sql_file" ] || { echo "Script SQL introuvable : $sql_file" >&2; exit 1; }
command -v ssh >/dev/null 2>&1 || { echo "SSH est requis." >&2; exit 1; }

if [ "$mode" = application ]; then
    fin=COMMIT
    echo "Application sur $host/$container"
else
    fin=ROLLBACK
    echo "Contrôle sans écriture sur $host/$container"
fi

{
    printf '%s\n' 'BEGIN;'
    sed '/^--liquibase/d' "$sql_file"
    printf '%s;\n' "$fin"
} | ssh -o BatchMode=yes "$host" "docker exec -i '$container' sh -ec '
    PGPASSWORD=\"\$POSTGRES_APP_PASSWORD\" exec psql \\
        --host=127.0.0.1 \\
        --username=\"\$POSTGRES_APP_USER\" \\
        --dbname=\"\$POSTGRES_DB\" \\
        --set=ON_ERROR_STOP=1
'"
