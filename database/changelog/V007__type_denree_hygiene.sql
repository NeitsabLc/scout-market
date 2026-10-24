--liquibase formatted sql

--changeset scout-market:V007-type-denree-hygiene splitStatements:true endDelimiter:;
--comment: Ajoute le type de denrée Hygiène

ALTER TABLE scout_market.denree DROP CONSTRAINT chk_denree_type;
ALTER TABLE scout_market.denree
    ADD CONSTRAINT chk_denree_type CHECK (type IN ('SEC','FRUITS_LEGUMES','FRAIS','HYGIENE'));

--rollback ALTER TABLE scout_market.denree DROP CONSTRAINT chk_denree_type;
--rollback ALTER TABLE scout_market.denree ADD CONSTRAINT chk_denree_type CHECK (type IN ('SEC','FRUITS_LEGUMES','FRAIS'));
