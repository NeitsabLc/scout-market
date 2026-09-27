-- Jeu de données de démonstration réservé à la recette Scout Market.
--
-- Le script est indépendant de Liquibase, transactionnel via le lanceur et
-- rejouable. Il ne supprime que les lignes dont les UUID commencent par 9,
-- plage réservée à ce jeu de données.

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'scout_market'
          AND table_name = 'grille_menu'
          AND column_name = 'type_distribution'
    ) THEN
        RAISE EXCEPTION 'Le schéma V002 ou supérieur est requis.';
    END IF;
END
$$;

DELETE FROM scout_market.mouvement_stock
WHERE id::text LIKE '98000000-0000-7000-8000-%';
DELETE FROM scout_market.groupe
WHERE id::text LIKE '97000000-0000-7000-8000-%';
DELETE FROM scout_market.menu
WHERE id::text LIKE '96000000-0000-7000-8000-%';
DELETE FROM scout_market.recette
WHERE id::text LIKE '95000000-0000-7000-8000-%';
DELETE FROM scout_market.denree_fournisseur
WHERE id::text LIKE '94000000-0000-7000-8000-%';
DELETE FROM scout_market.denree
WHERE id::text LIKE '93000000-0000-7000-8000-%';
DELETE FROM scout_market.fournisseur
WHERE id::text LIKE '92000000-0000-7000-8000-%';
DELETE FROM scout_market.grille_menu
WHERE id::text LIKE '91000000-0000-7000-8000-%';

INSERT INTO scout_market.grille_menu
    (id, label, date_debut, date_fin, type_distribution)
VALUES
    ('91000000-0000-7000-8000-000000000001', 'Démo recette — Stage forêt', CURRENT_DATE, CURRENT_DATE + 13, 'EN_CAISSE'),
    ('91000000-0000-7000-8000-000000000002', 'Démo recette — Camp itinérant', CURRENT_DATE, CURRENT_DATE + 13, 'SCOUT_MARKET'),
    ('91000000-0000-7000-8000-000000000003', 'Démo recette — Camp marin', CURRENT_DATE, CURRENT_DATE + 13, 'SCOUT_MARKET');

INSERT INTO scout_market.fournisseur (id, nom, telephone, email, adresse)
VALUES
    ('92000000-0000-7000-8000-000000000001', 'Démo recette — Centrale alimentaire', '01 40 00 10 01', 'centrale.demo@scout-market.local', '10 avenue des Camps, 75000 Paris'),
    ('92000000-0000-7000-8000-000000000002', 'Démo recette — Primeur local', '01 40 00 10 02', 'primeur.demo@scout-market.local', '11 rue du Potager, 75000 Paris'),
    ('92000000-0000-7000-8000-000000000003', 'Démo recette — Épicerie solidaire', '01 40 00 10 03', 'epicerie.demo@scout-market.local', '12 place du Marché, 75000 Paris');

INSERT INTO scout_market.denree
    (id, nom, type, unite_reference_id, unite_inventaire_id)
SELECT
    ('93000000-0000-7000-8000-' || lpad(numero::text, 12, '0'))::uuid,
    nom,
    type,
    (SELECT id FROM scout_market.unite WHERE symbole = unite_reference),
    (SELECT id FROM scout_market.unite WHERE symbole = unite_inventaire)
FROM (VALUES
    (1, 'Démo recette — Lait demi-écrémé', 'FRAIS', 'mL', 'L'),
    (2, 'Démo recette — Flocons d’avoine', 'SEC', 'g', 'kg'),
    (3, 'Démo recette — Pâtes', 'SEC', 'g', 'kg'),
    (4, 'Démo recette — Tomates concassées', 'SEC', 'g', 'kg'),
    (5, 'Démo recette — Bœuf haché', 'FRAIS', 'g', 'kg'),
    (6, 'Démo recette — Lentilles vertes', 'SEC', 'g', 'kg'),
    (7, 'Démo recette — Pommes', 'FRUITS_LEGUMES', 'g', 'kg'),
    (8, 'Démo recette — Bananes', 'FRUITS_LEGUMES', 'pc', 'pc'),
    (9, 'Démo recette — Pain', 'FRAIS', 'g', 'kg'),
    (10, 'Démo recette — Riz', 'SEC', 'g', 'kg'),
    (11, 'Démo recette — Carottes', 'FRUITS_LEGUMES', 'g', 'kg'),
    (12, 'Démo recette — Yaourts nature', 'FRAIS', 'pc', 'pc'),
    (13, 'Démo recette — Pois chiches', 'SEC', 'g', 'kg'),
    (14, 'Démo recette — Courgettes', 'FRUITS_LEGUMES', 'g', 'kg'),
    (15, 'Démo recette — Fromage', 'FRAIS', 'g', 'kg')
) AS donnees(numero, nom, type, unite_reference, unite_inventaire);

INSERT INTO scout_market.denree_fournisseur
    (id, fournisseur_id, denree_id, reference, principal)
SELECT
    ('94000000-0000-7000-8000-' || lpad(numero::text, 12, '0'))::uuid,
    ('92000000-0000-7000-8000-' || lpad(fournisseur::text, 12, '0'))::uuid,
    ('93000000-0000-7000-8000-' || lpad(numero::text, 12, '0'))::uuid,
    'DEMO-' || lpad(numero::text, 3, '0'),
    TRUE
FROM (VALUES
    (1, 1), (2, 3), (3, 3), (4, 1), (5, 1),
    (6, 3), (7, 2), (8, 2), (9, 1), (10, 3),
    (11, 2), (12, 1), (13, 3), (14, 2), (15, 1)
) AS donnees(numero, fournisseur);

INSERT INTO scout_market.denree_fournisseur_conditionnement
    (reference_fournisseur_id, ordre, libelle, quantite_contenu, unite_contenu_id, conditionnement_id)
SELECT
    ('94000000-0000-7000-8000-' || lpad(numero::text, 12, '0'))::uuid,
    1,
    libelle,
    quantite,
    (SELECT id FROM scout_market.unite WHERE symbole = unite_contenu),
    (SELECT id FROM scout_market.unite WHERE symbole = conditionnement)
FROM (VALUES
    (1, 'Brique de 1 L', 1.000, 'L', 'brique'),
    (2, 'Paquet de 500 g', 500.000, 'g', 'paquet'),
    (3, 'Paquet de 1 kg', 1.000, 'kg', 'paquet'),
    (4, 'Boîte de 800 g', 800.000, 'g', 'boîte'),
    (5, 'Barquette de 2 kg', 2.000, 'kg', 'barquette'),
    (6, 'Sachet de 1 kg', 1.000, 'kg', 'sachet'),
    (7, 'Carton de 10 kg', 10.000, 'kg', 'carton'),
    (8, 'Carton de 18 pièces', 18.000, 'pc', 'carton'),
    (9, 'Paquet de 500 g', 500.000, 'g', 'paquet'),
    (10, 'Sachet de 1 kg', 1.000, 'kg', 'sachet'),
    (11, 'Sachet de 5 kg', 5.000, 'kg', 'sachet'),
    (12, 'Carton de 12 pièces', 12.000, 'pc', 'carton'),
    (13, 'Boîte de 800 g', 800.000, 'g', 'boîte'),
    (14, 'Caisse de 5 kg', 5.000, 'kg', 'carton'),
    (15, 'Meule de 2 kg', 2.000, 'kg', 'carton')
) AS donnees(numero, libelle, quantite, unite_contenu, conditionnement);

INSERT INTO scout_market.denree_fournisseur_conditionnement
    (reference_fournisseur_id, ordre, libelle, quantite_contenu, unite_contenu_id, conditionnement_id)
SELECT
    ('94000000-0000-7000-8000-' || lpad(numero::text, 12, '0'))::uuid,
    2,
    libelle,
    1000.000,
    (SELECT id FROM scout_market.unite WHERE symbole = unite_contenu),
    (SELECT id FROM scout_market.unite WHERE symbole = conditionnement)
FROM (VALUES
    (1, 'Litre', 'mL', 'L'),
    (2, 'Kilogramme', 'g', 'kg'), (3, 'Kilogramme', 'g', 'kg'),
    (4, 'Kilogramme', 'g', 'kg'), (5, 'Kilogramme', 'g', 'kg'),
    (6, 'Kilogramme', 'g', 'kg'), (7, 'Kilogramme', 'g', 'kg'),
    (9, 'Kilogramme', 'g', 'kg'), (10, 'Kilogramme', 'g', 'kg'),
    (11, 'Kilogramme', 'g', 'kg'), (13, 'Kilogramme', 'g', 'kg'),
    (14, 'Kilogramme', 'g', 'kg'), (15, 'Kilogramme', 'g', 'kg')
) AS donnees(numero, libelle, unite_contenu, conditionnement);

INSERT INTO scout_market.recette (id, nom, categorie)
VALUES
    ('95000000-0000-7000-8000-000000000001', 'Démo recette — Porridge aux fruits', 'PETIT_DEJEUNER'),
    ('95000000-0000-7000-8000-000000000002', 'Démo recette — Salade de carottes', 'ENTREE'),
    ('95000000-0000-7000-8000-000000000003', 'Démo recette — Pâtes bolognaises', 'PLAT'),
    ('95000000-0000-7000-8000-000000000004', 'Démo recette — Lentilles au riz', 'PLAT'),
    ('95000000-0000-7000-8000-000000000005', 'Démo recette — Curry de pois chiches', 'PLAT'),
    ('95000000-0000-7000-8000-000000000006', 'Démo recette — Tartines et yaourt', 'GOUTER'),
    ('95000000-0000-7000-8000-000000000007', 'Démo recette — Salade de fruits', 'DESSERT'),
    ('95000000-0000-7000-8000-000000000008', 'Démo recette — Plateau de fromage', 'FROMAGE');

INSERT INTO scout_market.recette_denree
    (id, recette_id, denree_id, conditionnement_id, regime, ordre)
SELECT
    ('95100000-0000-7000-8000-' || lpad(numero::text, 12, '0'))::uuid,
    ('95000000-0000-7000-8000-' || lpad(recette::text, 12, '0'))::uuid,
    ('93000000-0000-7000-8000-' || lpad(denree::text, 12, '0'))::uuid,
    (SELECT id FROM scout_market.unite WHERE symbole = unite),
    regime,
    ordre
FROM (VALUES
    (1, 1, 2, 'g', NULL, 10), (2, 1, 1, 'mL', NULL, 20), (3, 1, 8, 'pc', NULL, 30),
    (4, 2, 11, 'g', NULL, 10), (5, 2, 7, 'g', NULL, 20),
    (6, 3, 3, 'g', NULL, 10), (7, 3, 4, 'g', NULL, 20), (8, 3, 5, 'g', NULL, 30),
    (9, 4, 6, 'g', 'VEGETARIEN', 10), (10, 4, 10, 'g', 'VEGETARIEN', 20), (11, 4, 11, 'g', 'VEGETARIEN', 30),
    (12, 5, 13, 'g', 'VEGETARIEN', 10), (13, 5, 10, 'g', 'VEGETARIEN', 20), (14, 5, 14, 'g', 'VEGETARIEN', 30),
    (15, 6, 9, 'g', NULL, 10), (16, 6, 12, 'pc', NULL, 20),
    (17, 7, 7, 'g', NULL, 10), (18, 7, 8, 'pc', NULL, 20),
    (19, 8, 15, 'g', NULL, 10), (20, 8, 9, 'g', NULL, 20)
) AS donnees(numero, recette, denree, unite, regime, ordre);

INSERT INTO scout_market.recette_denree_quantite
    (recette_denree_id, public_cible_id, quantite_individuelle)
SELECT
    ('95100000-0000-7000-8000-' || lpad(quantites.numero::text, 12, '0'))::uuid,
    public_cible.id,
    round(quantites.base * CASE public_cible.code
        WHEN 'FARFADETS' THEN 0.65
        WHEN 'LOUVETEAUX_JEANNETTES' THEN 0.80
        WHEN 'SCOUTS_GUIDES' THEN 1.00
        WHEN 'PIONNIERS_CARAVELLES' THEN 1.15
        ELSE 1.00
    END, 3)
FROM (VALUES
    (1, 55.000), (2, 250.000), (3, 1.000), (4, 90.000), (5, 40.000),
    (6, 120.000), (7, 100.000), (8, 100.000), (9, 85.000), (10, 65.000),
    (11, 80.000), (12, 90.000), (13, 60.000), (14, 100.000), (15, 90.000),
    (16, 1.000), (17, 100.000), (18, 1.000), (19, 35.000), (20, 50.000)
) AS quantites(numero, base)
CROSS JOIN scout_market.public_cible
WHERE public_cible.actif;

WITH grilles(id, numero) AS (VALUES
    ('91000000-0000-7000-8000-000000000001'::uuid, 1),
    ('91000000-0000-7000-8000-000000000002'::uuid, 2),
    ('91000000-0000-7000-8000-000000000003'::uuid, 3)
), repas(code, libelle, numero) AS (VALUES
    ('PETIT_DEJEUNER', 'Petit-déjeuner', 1),
    ('DEJEUNER', 'Déjeuner', 2),
    ('GOUTER', 'Goûter', 3),
    ('DINER', 'Dîner', 4)
)
INSERT INTO scout_market.menu (id, grille_menu_id, type_repas_id, date_menu, nom)
SELECT
    ('96000000-0000-7000-8000-' || lpad((grilles.numero * 1000 + jour * 10 + repas.numero)::text, 12, '0'))::uuid,
    grilles.id,
    type_repas.id,
    CURRENT_DATE + jour,
    repas.libelle || ' — jour ' || (jour + 1)
FROM grilles
CROSS JOIN generate_series(0, 13) AS jour
CROSS JOIN repas
JOIN scout_market.type_repas ON type_repas.code = repas.code;

WITH grilles(id, numero) AS (VALUES
    ('91000000-0000-7000-8000-000000000001'::uuid, 1),
    ('91000000-0000-7000-8000-000000000002'::uuid, 2),
    ('91000000-0000-7000-8000-000000000003'::uuid, 3)
), speciaux(code, libelle, numero) AS (VALUES
    ('EXPLO', 'Repas d’exploration', 1),
    ('PIQUE_NIQUE_1', 'Pique-nique froid', 2),
    ('PIQUE_NIQUE_2', 'Pique-nique chaud', 3)
)
INSERT INTO scout_market.menu (id, grille_menu_id, special_code, nom)
SELECT
    ('96000000-0000-7000-8000-' || lpad((grilles.numero * 1000 + 900 + speciaux.numero)::text, 12, '0'))::uuid,
    grilles.id,
    speciaux.code,
    speciaux.libelle
FROM grilles
CROSS JOIN speciaux;

WITH menus_recettes AS (
    SELECT menu.id AS menu_id, recette.id AS recette_id
    FROM scout_market.menu
    JOIN scout_market.type_repas ON type_repas.id = menu.type_repas_id
    JOIN scout_market.recette recette ON recette.id = CASE type_repas.code
        WHEN 'PETIT_DEJEUNER' THEN '95000000-0000-7000-8000-000000000001'::uuid
        WHEN 'GOUTER' THEN '95000000-0000-7000-8000-000000000006'::uuid
        ELSE ('95000000-0000-7000-8000-' || lpad((3 + mod(EXTRACT(DAY FROM menu.date_menu)::int + mod(EXTRACT(DAY FROM menu.date_menu)::int, 3), 3))::text, 12, '0'))::uuid
    END
    WHERE menu.id::text LIKE '96000000-0000-7000-8000-%'
      AND menu.special_code IS NULL

    UNION ALL

    SELECT menu.id, entree.id
    FROM scout_market.menu
    JOIN scout_market.type_repas ON type_repas.id = menu.type_repas_id
    JOIN scout_market.recette entree ON entree.id = '95000000-0000-7000-8000-000000000002'
    WHERE menu.id::text LIKE '96000000-0000-7000-8000-%'
      AND type_repas.code IN ('DEJEUNER', 'DINER')

    UNION ALL

    SELECT menu.id, dessert.id
    FROM scout_market.menu
    JOIN scout_market.type_repas ON type_repas.id = menu.type_repas_id
    JOIN scout_market.recette dessert ON dessert.id = '95000000-0000-7000-8000-000000000007'
    WHERE menu.id::text LIKE '96000000-0000-7000-8000-%'
      AND type_repas.code IN ('DEJEUNER', 'GOUTER', 'DINER')

    UNION ALL

    SELECT menu.id, fromage.id
    FROM scout_market.menu
    JOIN scout_market.type_repas ON type_repas.id = menu.type_repas_id
    JOIN scout_market.recette fromage ON fromage.id = '95000000-0000-7000-8000-000000000008'
    WHERE menu.id::text LIKE '96000000-0000-7000-8000-%'
      AND type_repas.code = 'DINER'
      AND EXTRACT(DAY FROM menu.date_menu)::int % 3 = 0

    UNION ALL

    SELECT menu.id, recette.id
    FROM scout_market.menu
    JOIN scout_market.recette recette ON recette.id = CASE menu.special_code
        WHEN 'EXPLO' THEN '95000000-0000-7000-8000-000000000003'::uuid
        WHEN 'PIQUE_NIQUE_1' THEN '95000000-0000-7000-8000-000000000006'::uuid
        ELSE '95000000-0000-7000-8000-000000000004'::uuid
    END
    WHERE menu.id::text LIKE '96000000-0000-7000-8000-%'
      AND menu.special_code IS NOT NULL
), associations AS (
    SELECT DISTINCT menu_id, recette_id
    FROM menus_recettes
)
INSERT INTO scout_market.menu_denree
    (menu_id, denree_id, conditionnement_id, regime, recette_id, recette_instance_id, categorie, ordre)
SELECT
    associations.menu_id,
    recette_denree.denree_id,
    recette_denree.conditionnement_id,
    recette_denree.regime,
    associations.recette_id,
    md5(associations.menu_id::text || ':' || associations.recette_id::text)::uuid,
    CASE WHEN recette.categorie IN ('ENTREE', 'PLAT', 'FROMAGE', 'DESSERT') THEN recette.categorie ELSE NULL END,
    recette_denree.ordre
FROM associations
JOIN scout_market.recette_denree ON recette_denree.recette_id = associations.recette_id
JOIN scout_market.recette ON recette.id = associations.recette_id;

INSERT INTO scout_market.menu_denree_quantite
    (menu_denree_id, public_cible_id, quantite_individuelle)
SELECT
    menu_denree.id,
    recette_quantite.public_cible_id,
    recette_quantite.quantite_individuelle
FROM scout_market.menu_denree
JOIN scout_market.recette_denree
    ON recette_denree.recette_id = menu_denree.recette_id
    AND recette_denree.denree_id = menu_denree.denree_id
    AND recette_denree.ordre = menu_denree.ordre
JOIN scout_market.recette_denree_quantite recette_quantite
    ON recette_quantite.recette_denree_id = recette_denree.id
WHERE menu_denree.menu_id::text LIKE '96000000-0000-7000-8000-%';

INSERT INTO scout_market.groupe
    (id, grille_menu_id, nom, effectif_jeune, effectif_adulte, nombre_vegetariens, nombre_sans_lactose, nombre_sans_gluten, type, date_debut_presence, date_fin_presence)
VALUES
    ('97000000-0000-7000-8000-000000000001', '91000000-0000-7000-8000-000000000001', 'Démo recette — Farfadets des Chênes', 18, 4, 2, 1, 1, 'farfadets', CURRENT_DATE, CURRENT_DATE + 6),
    ('97000000-0000-7000-8000-000000000002', '91000000-0000-7000-8000-000000000001', 'Démo recette — Louveteaux de la Rivière', 26, 5, 3, 2, 1, 'louveteaux-jeannettes', CURRENT_DATE, CURRENT_DATE + 13),
    ('97000000-0000-7000-8000-000000000003', '91000000-0000-7000-8000-000000000001', 'Démo recette — Équipe technique', 0, 12, 2, 1, 2, 'scouts-guides', CURRENT_DATE + 2, CURRENT_DATE + 13),
    ('97000000-0000-7000-8000-000000000004', '91000000-0000-7000-8000-000000000002', 'Démo recette — Scouts-Guides des Étoiles', 32, 6, 5, 1, 2, 'scouts-guides', CURRENT_DATE, CURRENT_DATE + 10),
    ('97000000-0000-7000-8000-000000000005', '91000000-0000-7000-8000-000000000002', 'Démo recette — Pionniers du Levant', 21, 4, 4, 2, 2, 'pionniers-caravelles', CURRENT_DATE + 3, CURRENT_DATE + 13),
    ('97000000-0000-7000-8000-000000000006', '91000000-0000-7000-8000-000000000003', 'Démo recette — Moussaillons du Large', 28, 5, 3, 0, 1, 'louveteaux-jeannettes', CURRENT_DATE, CURRENT_DATE + 13),
    ('97000000-0000-7000-8000-000000000007', '91000000-0000-7000-8000-000000000003', 'Démo recette — Caravelles de l’Océan', 24, 5, 6, 2, 1, 'pionniers-caravelles', CURRENT_DATE + 1, CURRENT_DATE + 12);

INSERT INTO scout_market.groupe_repas (groupe_id, menu_id, mode)
SELECT affectations.groupe_id::uuid, menu.id, affectations.mode
FROM (VALUES
    ('97000000-0000-7000-8000-000000000001', '91000000-0000-7000-8000-000000000001', 2, 'DEJEUNER', 'PIQUE_NIQUE_1'),
    ('97000000-0000-7000-8000-000000000002', '91000000-0000-7000-8000-000000000001', 5, 'DINER', 'EXPLO'),
    ('97000000-0000-7000-8000-000000000003', '91000000-0000-7000-8000-000000000001', 8, 'DEJEUNER', 'NON_PRIS'),
    ('97000000-0000-7000-8000-000000000004', '91000000-0000-7000-8000-000000000002', 3, 'DEJEUNER', 'PIQUE_NIQUE_2'),
    ('97000000-0000-7000-8000-000000000005', '91000000-0000-7000-8000-000000000002', 7, 'DINER', 'EXPLO'),
    ('97000000-0000-7000-8000-000000000006', '91000000-0000-7000-8000-000000000003', 4, 'DEJEUNER', 'PIQUE_NIQUE_1'),
    ('97000000-0000-7000-8000-000000000007', '91000000-0000-7000-8000-000000000003', 9, 'DINER', 'NON_PRIS')
) AS affectations(groupe_id, grille_id, jour, type_repas, mode)
JOIN scout_market.menu ON menu.grille_menu_id = affectations.grille_id::uuid
    AND menu.date_menu = CURRENT_DATE + affectations.jour
JOIN scout_market.type_repas ON type_repas.id = menu.type_repas_id
    AND type_repas.code = affectations.type_repas;

INSERT INTO scout_market.mouvement_stock
    (id, utilisateur_id, type_mouvement_id, origine_mouvement_id, date_mouvement)
VALUES (
    '98000000-0000-7000-8000-000000000001',
    (SELECT id FROM scout_market.utilisateur WHERE email = 'saisie-consommation@scout-market.local'),
    (SELECT id FROM scout_market.type_mouvement WHERE code = 'ENTREE'),
    (SELECT id FROM scout_market.origine_mouvement WHERE code = 'INVENTAIRE'),
    CURRENT_TIMESTAMP - INTERVAL '1 day'
);

INSERT INTO scout_market.mouvement_stock_ligne
    (mouvement_stock_id, denree_id, conditionnement_saisie_id, quantite_saisie, numero_lot)
SELECT
    '98000000-0000-7000-8000-000000000001',
    ('93000000-0000-7000-8000-' || lpad(numero::text, 12, '0'))::uuid,
    (SELECT id FROM scout_market.unite WHERE symbole = unite),
    quantite,
    'RECETTE-' || lpad(numero::text, 3, '0')
FROM (VALUES
    (1, 'L', 60.000), (2, 'kg', 25.000), (3, 'kg', 80.000),
    (4, 'kg', 55.000), (5, 'kg', 35.000), (6, 'kg', 45.000),
    (7, 'kg', 60.000), (8, 'pc', 240.000), (9, 'kg', 65.000),
    (10, 'kg', 55.000), (11, 'kg', 45.000), (12, 'pc', 300.000),
    (13, 'kg', 40.000), (14, 'kg', 50.000), (15, 'kg', 25.000)
) AS inventaire(numero, unite, quantite);

INSERT INTO scout_market.mouvement_stock
    (id, utilisateur_id, groupe_id, menu_id, type_mouvement_id, origine_mouvement_id, date_mouvement)
SELECT
    '98000000-0000-7000-8000-000000000002',
    (SELECT id FROM scout_market.utilisateur WHERE email = 'saisie-consommation@scout-market.local'),
    '97000000-0000-7000-8000-000000000001',
    menu.id,
    (SELECT id FROM scout_market.type_mouvement WHERE code = 'SORTIE'),
    (SELECT id FROM scout_market.origine_mouvement WHERE code = 'DISTRIBUTION'),
    CURRENT_TIMESTAMP
FROM scout_market.menu
JOIN scout_market.type_repas ON type_repas.id = menu.type_repas_id
WHERE menu.grille_menu_id = '91000000-0000-7000-8000-000000000001'
  AND menu.date_menu = CURRENT_DATE
  AND type_repas.code = 'DEJEUNER';

INSERT INTO scout_market.mouvement_stock_ligne
    (mouvement_stock_id, denree_id, conditionnement_saisie_id, quantite_saisie)
VALUES
    ('98000000-0000-7000-8000-000000000002', '93000000-0000-7000-8000-000000000003', (SELECT id FROM scout_market.unite WHERE symbole = 'kg'), 4.500),
    ('98000000-0000-7000-8000-000000000002', '93000000-0000-7000-8000-000000000004', (SELECT id FROM scout_market.unite WHERE symbole = 'kg'), 3.200),
    ('98000000-0000-7000-8000-000000000002', '93000000-0000-7000-8000-000000000011', (SELECT id FROM scout_market.unite WHERE symbole = 'kg'), 2.800);

DO $$
DECLARE
    nombre_grilles integer;
    nombre_menus integer;
    nombre_unites integer;
    nombre_modes integer;
BEGIN
    SELECT count(*) INTO nombre_grilles
    FROM scout_market.grille_menu
    WHERE id::text LIKE '91000000-0000-7000-8000-%';

    SELECT count(*) INTO nombre_menus
    FROM scout_market.menu
    WHERE id::text LIKE '96000000-0000-7000-8000-%'
      AND special_code IS NULL;

    SELECT count(*) INTO nombre_unites
    FROM scout_market.groupe
    WHERE id::text LIKE '97000000-0000-7000-8000-%';

    SELECT count(DISTINCT type_distribution) INTO nombre_modes
    FROM scout_market.grille_menu
    WHERE id::text LIKE '91000000-0000-7000-8000-%';

    IF nombre_grilles <> 3 OR nombre_menus <> 168 OR nombre_unites <> 7 OR nombre_modes <> 2 THEN
        RAISE EXCEPTION 'Jeu incomplet : % grilles, % menus, % unités, % modes.',
            nombre_grilles, nombre_menus, nombre_unites, nombre_modes;
    END IF;

    IF EXISTS (
        SELECT 1
        FROM scout_market.grille_menu
        WHERE id::text LIKE '91000000-0000-7000-8000-%'
          AND (date_debut <> CURRENT_DATE OR date_fin <> CURRENT_DATE + 13)
    ) THEN
        RAISE EXCEPTION 'Les grilles de recette ne couvrent pas exactement les 14 prochains jours.';
    END IF;
END
$$;
