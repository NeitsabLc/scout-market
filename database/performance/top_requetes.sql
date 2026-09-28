\pset pager off
\pset null '—'
\timing off

\echo 'Période couverte par pg_stat_statements'
SELECT stats_reset AS collecte_depuis
FROM pg_stat_statements_info;

\echo '20 requêtes ayant consommé le plus de temps cumulé'
SELECT
    calls,
    round(total_exec_time::numeric, 1) AS temps_total_ms,
    round(mean_exec_time::numeric, 2) AS temps_moyen_ms,
    round(max_exec_time::numeric, 2) AS temps_max_ms,
    rows,
    shared_blks_read,
    shared_blks_hit,
    temp_blks_written,
    left(regexp_replace(query, '[[:space:]]+', ' ', 'g'), 180) AS requete
FROM pg_stat_statements
WHERE dbid = (SELECT oid FROM pg_database WHERE datname = current_database())
  AND query NOT ILIKE '%pg_stat_statements%'
ORDER BY total_exec_time DESC
LIMIT 20;

\echo 'Utilisation des index Scout Market depuis le dernier redémarrage des statistiques'
SELECT
    relname AS table_cible,
    indexrelname AS index,
    idx_scan,
    idx_tup_read,
    idx_tup_fetch
FROM pg_stat_user_indexes
WHERE schemaname = 'scout_market'
ORDER BY idx_scan DESC, relname, indexrelname;
