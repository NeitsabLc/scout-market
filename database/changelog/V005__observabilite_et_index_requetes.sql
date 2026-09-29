--liquibase formatted sql

--changeset scout-market:V005-observabilite-et-index-requetes splitStatements:true endDelimiter:;
--comment: Active les statistiques de requêtes et indexe les relations utilisées par les écrans de gestion

CREATE EXTENSION IF NOT EXISTS pg_stat_statements;

CREATE INDEX idx_recette_denree_recette_ordre
    ON scout_market.recette_denree(recette_id, ordre);
CREATE INDEX idx_recette_denree_denree_recette
    ON scout_market.recette_denree(denree_id, recette_id);

CREATE INDEX idx_menu_denree_menu_ordre
    ON scout_market.menu_denree(menu_id, ordre);
CREATE INDEX idx_menu_denree_denree_menu_ordre
    ON scout_market.menu_denree(denree_id, menu_id, ordre);

CREATE INDEX idx_mouvement_ligne_denree_mouvement
    ON scout_market.mouvement_stock_ligne(denree_id, mouvement_stock_id);

CREATE INDEX idx_groupe_grille_nom_actif
    ON scout_market.groupe(grille_menu_id, nom)
    WHERE actif = TRUE;

--rollback DROP INDEX scout_market.idx_groupe_grille_nom_actif;
--rollback DROP INDEX scout_market.idx_mouvement_ligne_denree_mouvement;
--rollback DROP INDEX scout_market.idx_menu_denree_denree_menu_ordre;
--rollback DROP INDEX scout_market.idx_menu_denree_menu_ordre;
--rollback DROP INDEX scout_market.idx_recette_denree_denree_recette;
--rollback DROP INDEX scout_market.idx_recette_denree_recette_ordre;
--rollback DROP EXTENSION IF EXISTS pg_stat_statements;
