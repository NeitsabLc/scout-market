--liquibase formatted sql

--changeset scout-market:V003-completer-quantites-recettes splitStatements:true endDelimiter:;
--comment: Initialise à zéro les quantités par public manquantes dans toutes les recettes

INSERT INTO scout_market.recette_denree_quantite (
    recette_denree_id,
    public_cible_id,
    quantite_individuelle
)
SELECT
    recette_denree.id,
    public_cible.id,
    0
FROM scout_market.recette_denree
CROSS JOIN scout_market.public_cible
LEFT JOIN scout_market.recette_denree_quantite AS quantite
    ON quantite.recette_denree_id = recette_denree.id
    AND quantite.public_cible_id = public_cible.id
WHERE quantite.id IS NULL
ON CONFLICT (recette_denree_id, public_cible_id) DO NOTHING;
