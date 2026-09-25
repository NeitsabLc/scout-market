--liquibase formatted sql

--changeset scout-market:V002-distribution-par-grille splitStatements:true endDelimiter:;
--comment: Porte le mode de distribution au niveau global de la grille de menus

ALTER TABLE scout_market.grille_menu
    ADD COLUMN type_distribution VARCHAR(30) NOT NULL DEFAULT 'SCOUT_MARKET';

-- Une ancienne grille mixte devient un Stage afin de ne pas omettre les
-- préparations qui étaient auparavant marquées « En caisse » sur un repas.
UPDATE scout_market.grille_menu AS grille
SET type_distribution = 'EN_CAISSE'
WHERE EXISTS (
    SELECT 1
    FROM scout_market.menu AS menu
    WHERE menu.grille_menu_id = grille.id
      AND menu.type_distribution = 'EN_CAISSE'
);

ALTER TABLE scout_market.grille_menu
    ADD CONSTRAINT chk_grille_menu_type_distribution
    CHECK (type_distribution IN ('SCOUT_MARKET', 'EN_CAISSE'));

ALTER TABLE scout_market.menu
    DROP CONSTRAINT chk_menu_type_distribution,
    DROP COLUMN type_distribution;
