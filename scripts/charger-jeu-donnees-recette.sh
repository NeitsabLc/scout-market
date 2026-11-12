#!/bin/sh

set -eu

# Usage : charger-jeu-donnees-recette.sh [AAAA-MM-JJ]
# Sans argument, les grilles commencent au jour calendaire de Paris.
date_debut=${1:-}
case "$date_debut" in
    '') ;;
    [0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]) ;;
    *)
        echo "Date de début invalide : attendu AAAA-MM-JJ." >&2
        exit 1
        ;;
esac

script_dir=$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd)
sql_file="$script_dir/recette/charger-jeu-donnees.sql"
container=${SCOUT_MARKET_RECETTE_DB_CONTAINER:-scout-market-recette-database-1}

command -v docker >/dev/null 2>&1 || {
    echo "Docker est requis." >&2
    exit 1
}

[ -r "$sql_file" ] || {
    echo "Script SQL introuvable : $sql_file" >&2
    exit 1
}

project=$(docker inspect --format '{{ index .Config.Labels "com.docker.compose.project" }}' "$container" 2>/dev/null || true)
[ "$project" = scout-market-recette ] || {
    echo "Refus d'exécution : le conteneur doit appartenir au projet scout-market-recette." >&2
    exit 1
}

# Le compte applicatif a les droits de lecture et d'écriture sur les données du
# schéma, sans droit de structure ; le compte de supervision n'en a aucun.
docker exec -i -e DATE_DEBUT="$date_debut" "$container" sh -ec '
    if [ -n "$DATE_DEBUT" ]; then set -- --set=date_debut="$DATE_DEBUT"; fi
    PGPASSWORD="$POSTGRES_APP_PASSWORD" exec psql \
        --host=127.0.0.1 \
        --username="$POSTGRES_APP_USER" \
        --dbname="$POSTGRES_DB" \
        --set=ON_ERROR_STOP=1 \
        --single-transaction \
        "$@"
' <"$sql_file"

docker exec "$container" sh -ec '
    PGPASSWORD="$POSTGRES_APP_PASSWORD" exec psql \
        --host=127.0.0.1 \
        --username="$POSTGRES_APP_USER" \
        --dbname="$POSTGRES_DB" \
        --no-align \
        --field-separator=" | " \
        --tuples-only \
        --set=ON_ERROR_STOP=1 \
        --command="
            SELECT
                count(*) AS grilles,
                min(date_debut) AS debut,
                max(date_fin) AS fin,
                string_agg(DISTINCT type_distribution, '\'', '\'' ORDER BY type_distribution) AS modes
            FROM scout_market.grille_menu
            WHERE id::text LIKE '\''91000000-0000-7000-8000-%'\'';
            SELECT
                count(*) FILTER (WHERE special_code IS NULL) AS repas,
                count(*) FILTER (WHERE special_code IS NOT NULL) AS repas_speciaux
            FROM scout_market.menu
            WHERE id::text LIKE '\''96000000-0000-7000-8000-%'\'';
            SELECT count(*) AS unites
            FROM scout_market.groupe
            WHERE id::text LIKE '\''97000000-0000-7000-8000-%'\'';
        "
'
