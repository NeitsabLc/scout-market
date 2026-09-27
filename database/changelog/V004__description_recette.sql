--liquibase formatted sql

--changeset scout-market:V004-description-recette splitStatements:true endDelimiter:;
--comment: Ajoute une description HTML optionnelle aux recettes

ALTER TABLE scout_market.recette
    ADD COLUMN description TEXT;
