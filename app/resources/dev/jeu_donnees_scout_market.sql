-- Jeu de données local, rejouable et relatif à CURRENT_DATE.
-- Les UUID de la plage *000000000001 à *000000000099 sont réservés à ce jeu.

DELETE FROM scout_market.mouvement_stock
WHERE id IN (
    '80000000-0000-7000-8000-000000000001',
    '80000000-0000-7000-8000-000000000002'
);
DELETE FROM scout_market.utilisateur
WHERE id IN (
    '71000000-0000-7000-8000-000000000001',
    '71000000-0000-7000-8000-000000000002',
    '71000000-0000-7000-8000-000000000003'
);
DELETE FROM scout_market.groupe
WHERE id IN (
    '70000000-0000-7000-8000-000000000001',
    '70000000-0000-7000-8000-000000000002',
    '70000000-0000-7000-8000-000000000003',
    '70000000-0000-7000-8000-000000000004',
    '70000000-0000-7000-8000-000000000005'
);
DELETE FROM scout_market.menu
WHERE grille_menu_id IN (
    '10000000-0000-7000-8000-000000000001',
    '10000000-0000-7000-8000-000000000002',
    '10000000-0000-7000-8000-000000000003'
);
DELETE FROM scout_market.recette
WHERE id IN (
    '50000000-0000-7000-8000-000000000001',
    '50000000-0000-7000-8000-000000000002',
    '50000000-0000-7000-8000-000000000003',
    '50000000-0000-7000-8000-000000000004',
    '50000000-0000-7000-8000-000000000005'
);
DELETE FROM scout_market.denree_fournisseur
WHERE id::text LIKE '40000000-0000-7000-8000-%';
DELETE FROM scout_market.denree
WHERE id::text LIKE '30000000-0000-7000-8000-%';
DELETE FROM scout_market.fournisseur
WHERE id::text LIKE '20000000-0000-7000-8000-%';
DELETE FROM scout_market.grille_menu
WHERE id IN (
    '10000000-0000-7000-8000-000000000001',
    '10000000-0000-7000-8000-000000000002',
    '10000000-0000-7000-8000-000000000003'
);

INSERT INTO scout_market.grille_menu (id, label, date_debut, date_fin)
VALUES
    ('10000000-0000-7000-8000-000000000001', 'Grille École des bois', CURRENT_DATE - 7, CURRENT_DATE + 14),
    ('10000000-0000-7000-8000-000000000002', 'Grille Grande aventure', CURRENT_DATE - 3, CURRENT_DATE + 10),
    ('10000000-0000-7000-8000-000000000003', 'Grille Week-end nature', CURRENT_DATE - 10, CURRENT_DATE + 3);

INSERT INTO scout_market.fournisseur (id, nom, telephone, email, adresse)
VALUES
    ('20000000-0000-7000-8000-000000000001', 'Métro Démonstration', '01 40 00 00 01', 'commandes.metro@scout-market.local', '1 avenue des Éclaireurs, 75000 Paris'),
    ('20000000-0000-7000-8000-000000000002', 'Primeur des Scouts', '01 40 00 00 02', 'primeur@scout-market.local', '2 rue du Marché, 75000 Paris'),
    ('20000000-0000-7000-8000-000000000003', 'Épicerie Solidaire', '01 40 00 00 03', 'epicerie@scout-market.local', '3 place du Camp, 75000 Paris');

INSERT INTO scout_market.denree (id, nom, type, unite_reference_id, unite_inventaire_id)
VALUES
    ('30000000-0000-7000-8000-000000000001', 'Lait demi-écrémé', 'FRAIS', (SELECT id FROM scout_market.unite WHERE symbole = 'mL'), (SELECT id FROM scout_market.unite WHERE symbole = 'L')),
    ('30000000-0000-7000-8000-000000000002', 'Flocons d’avoine', 'SEC', (SELECT id FROM scout_market.unite WHERE symbole = 'g'), (SELECT id FROM scout_market.unite WHERE symbole = 'kg')),
    ('30000000-0000-7000-8000-000000000003', 'Pâtes', 'SEC', (SELECT id FROM scout_market.unite WHERE symbole = 'g'), (SELECT id FROM scout_market.unite WHERE symbole = 'kg')),
    ('30000000-0000-7000-8000-000000000004', 'Tomates concassées', 'SEC', (SELECT id FROM scout_market.unite WHERE symbole = 'g'), (SELECT id FROM scout_market.unite WHERE symbole = 'kg')),
    ('30000000-0000-7000-8000-000000000005', 'Bœuf haché', 'FRAIS', (SELECT id FROM scout_market.unite WHERE symbole = 'g'), (SELECT id FROM scout_market.unite WHERE symbole = 'kg')),
    ('30000000-0000-7000-8000-000000000006', 'Lentilles vertes', 'SEC', (SELECT id FROM scout_market.unite WHERE symbole = 'g'), (SELECT id FROM scout_market.unite WHERE symbole = 'kg')),
    ('30000000-0000-7000-8000-000000000007', 'Pommes', 'FRUITS_LEGUMES', (SELECT id FROM scout_market.unite WHERE symbole = 'g'), (SELECT id FROM scout_market.unite WHERE symbole = 'kg')),
    ('30000000-0000-7000-8000-000000000008', 'Bananes', 'FRUITS_LEGUMES', (SELECT id FROM scout_market.unite WHERE symbole = 'pc'), (SELECT id FROM scout_market.unite WHERE symbole = 'pc')),
    ('30000000-0000-7000-8000-000000000009', 'Pain', 'FRAIS', (SELECT id FROM scout_market.unite WHERE symbole = 'g'), (SELECT id FROM scout_market.unite WHERE symbole = 'kg')),
    ('30000000-0000-7000-8000-000000000010', 'Riz', 'SEC', (SELECT id FROM scout_market.unite WHERE symbole = 'g'), (SELECT id FROM scout_market.unite WHERE symbole = 'kg')),
    ('30000000-0000-7000-8000-000000000011', 'Carottes', 'FRUITS_LEGUMES', (SELECT id FROM scout_market.unite WHERE symbole = 'g'), (SELECT id FROM scout_market.unite WHERE symbole = 'kg')),
    ('30000000-0000-7000-8000-000000000012', 'Yaourts nature', 'FRAIS', (SELECT id FROM scout_market.unite WHERE symbole = 'pc'), (SELECT id FROM scout_market.unite WHERE symbole = 'pc'));

INSERT INTO scout_market.denree_fournisseur (id, fournisseur_id, denree_id, reference, principal)
SELECT
    ('40000000-0000-7000-8000-' || lpad(numero::text, 12, '0'))::uuid,
    fournisseur_id::uuid,
    ('30000000-0000-7000-8000-' || lpad(numero::text, 12, '0'))::uuid,
    reference,
    TRUE
FROM (VALUES
    (1, '20000000-0000-7000-8000-000000000001', 'LAIT-1L'),
    (2, '20000000-0000-7000-8000-000000000003', 'AVOINE-500'),
    (3, '20000000-0000-7000-8000-000000000003', 'PATES-1K'),
    (4, '20000000-0000-7000-8000-000000000001', 'TOMATES-800'),
    (5, '20000000-0000-7000-8000-000000000001', 'BOEUF-2K'),
    (6, '20000000-0000-7000-8000-000000000003', 'LENTILLES-1K'),
    (7, '20000000-0000-7000-8000-000000000002', 'POMMES-10K'),
    (8, '20000000-0000-7000-8000-000000000002', 'BANANES-18'),
    (9, '20000000-0000-7000-8000-000000000001', 'PAIN-500'),
    (10, '20000000-0000-7000-8000-000000000003', 'RIZ-1K'),
    (11, '20000000-0000-7000-8000-000000000002', 'CAROTTES-5K'),
    (12, '20000000-0000-7000-8000-000000000001', 'YAOURT-12')
) AS donnees(numero, fournisseur_id, reference);

INSERT INTO scout_market.denree_fournisseur_conditionnement
    (reference_fournisseur_id, ordre, libelle, quantite_contenu, unite_contenu_id, conditionnement_id)
SELECT
    ('40000000-0000-7000-8000-' || lpad(numero::text, 12, '0'))::uuid,
    1,
    libelle,
    quantite,
    (SELECT id FROM scout_market.unite WHERE symbole = unite_symbole),
    (SELECT id FROM scout_market.unite WHERE symbole = conditionnement_symbole)
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
    (12, 'Carton de 12 pièces', 12.000, 'pc', 'carton')
) AS donnees(numero, libelle, quantite, unite_symbole, conditionnement_symbole);

-- Niveau physique intermédiaire utilisé pour convertir les quantités des menus
-- vers les unités d'inventaire (g vers kg et mL vers L).
INSERT INTO scout_market.denree_fournisseur_conditionnement
    (reference_fournisseur_id, ordre, libelle, quantite_contenu, unite_contenu_id, conditionnement_id)
SELECT
    ('40000000-0000-7000-8000-' || lpad(numero::text, 12, '0'))::uuid,
    2,
    libelle,
    1000.000,
    (SELECT id FROM scout_market.unite WHERE symbole = unite_contenu_symbole),
    (SELECT id FROM scout_market.unite WHERE symbole = conditionnement_symbole)
FROM (VALUES
    (1, 'Litre', 'mL', 'L'),
    (2, 'Kilogramme', 'g', 'kg'),
    (3, 'Kilogramme', 'g', 'kg'),
    (4, 'Kilogramme', 'g', 'kg'),
    (5, 'Kilogramme', 'g', 'kg'),
    (6, 'Kilogramme', 'g', 'kg'),
    (7, 'Kilogramme', 'g', 'kg'),
    (9, 'Kilogramme', 'g', 'kg'),
    (10, 'Kilogramme', 'g', 'kg'),
    (11, 'Kilogramme', 'g', 'kg')
) AS donnees(numero, libelle, unite_contenu_symbole, conditionnement_symbole);

INSERT INTO scout_market.recette (id, nom, categorie)
VALUES
    ('50000000-0000-7000-8000-000000000001', 'Porridge aux fruits', 'PETIT_DEJEUNER'),
    ('50000000-0000-7000-8000-000000000002', 'Salade de carottes', 'ENTREE'),
    ('50000000-0000-7000-8000-000000000003', 'Pâtes bolognaises', 'PLAT'),
    ('50000000-0000-7000-8000-000000000004', 'Lentilles au riz', 'PLAT'),
    ('50000000-0000-7000-8000-000000000005', 'Salade de fruits', 'DESSERT');

INSERT INTO scout_market.recette_denree (id, recette_id, denree_id, conditionnement_id, regime, ordre)
SELECT
    ('51000000-0000-7000-8000-' || lpad(numero::text, 12, '0'))::uuid,
    recette_id::uuid,
    denree_id::uuid,
    (SELECT id FROM scout_market.unite WHERE symbole = unite_symbole),
    regime,
    ordre
FROM (VALUES
    (1, '50000000-0000-7000-8000-000000000001', '30000000-0000-7000-8000-000000000002', 'g', NULL, 10),
    (2, '50000000-0000-7000-8000-000000000001', '30000000-0000-7000-8000-000000000001', 'mL', NULL, 20),
    (3, '50000000-0000-7000-8000-000000000001', '30000000-0000-7000-8000-000000000008', 'pc', NULL, 30),
    (4, '50000000-0000-7000-8000-000000000002', '30000000-0000-7000-8000-000000000011', 'g', NULL, 10),
    (5, '50000000-0000-7000-8000-000000000003', '30000000-0000-7000-8000-000000000003', 'g', NULL, 10),
    (6, '50000000-0000-7000-8000-000000000003', '30000000-0000-7000-8000-000000000004', 'g', NULL, 20),
    (7, '50000000-0000-7000-8000-000000000003', '30000000-0000-7000-8000-000000000005', 'g', NULL, 30),
    (8, '50000000-0000-7000-8000-000000000004', '30000000-0000-7000-8000-000000000006', 'g', 'VEGETARIEN', 10),
    (9, '50000000-0000-7000-8000-000000000004', '30000000-0000-7000-8000-000000000010', 'g', 'VEGETARIEN', 20),
    (10, '50000000-0000-7000-8000-000000000004', '30000000-0000-7000-8000-000000000011', 'g', 'VEGETARIEN', 30),
    (11, '50000000-0000-7000-8000-000000000005', '30000000-0000-7000-8000-000000000007', 'g', NULL, 10),
    (12, '50000000-0000-7000-8000-000000000005', '30000000-0000-7000-8000-000000000008', 'pc', NULL, 20)
) AS donnees(numero, recette_id, denree_id, unite_symbole, regime, ordre);

INSERT INTO scout_market.recette_denree_quantite (recette_denree_id, public_cible_id, quantite_individuelle)
SELECT
    ('51000000-0000-7000-8000-' || lpad(quantites.numero::text, 12, '0'))::uuid,
    public_cible.id,
    CASE public_cible.code
        WHEN 'FARFADETS' THEN quantites.farfadets
        WHEN 'LOUVETEAUX_JEANNETTES' THEN quantites.louveteaux
        WHEN 'SCOUTS_GUIDES' THEN quantites.scouts
        WHEN 'PIONNIERS_CARAVELLES' THEN quantites.pionniers
        ELSE quantites.adultes
    END
FROM (VALUES
    (1, 35.000, 45.000, 55.000, 65.000, 55.000),
    (2, 160.000, 200.000, 250.000, 300.000, 250.000),
    (3, 0.500, 0.500, 1.000, 1.000, 1.000),
    (4, 60.000, 75.000, 90.000, 100.000, 90.000),
    (5, 80.000, 100.000, 120.000, 140.000, 120.000),
    (6, 70.000, 85.000, 100.000, 110.000, 100.000),
    (7, 60.000, 80.000, 100.000, 120.000, 100.000),
    (8, 55.000, 70.000, 85.000, 100.000, 85.000),
    (9, 45.000, 60.000, 75.000, 90.000, 75.000),
    (10, 40.000, 50.000, 60.000, 70.000, 60.000),
    (11, 80.000, 100.000, 120.000, 140.000, 120.000),
    (12, 0.500, 0.500, 1.000, 1.000, 1.000)
) AS quantites(numero, farfadets, louveteaux, scouts, pionniers, adultes)
CROSS JOIN scout_market.public_cible
WHERE public_cible.actif;

INSERT INTO scout_market.menu (id, grille_menu_id, type_repas_id, date_menu, nom, type_distribution)
VALUES
    ('60000000-0000-7000-8000-000000000001', '10000000-0000-7000-8000-000000000001', (SELECT id FROM scout_market.type_repas WHERE code = 'PETIT_DEJEUNER'), CURRENT_DATE, 'Petit-déjeuner énergie', 'SCOUT_MARKET'),
    ('60000000-0000-7000-8000-000000000002', '10000000-0000-7000-8000-000000000001', (SELECT id FROM scout_market.type_repas WHERE code = 'DEJEUNER'), CURRENT_DATE, 'Déjeuner italien', 'EN_CAISSE'),
    ('60000000-0000-7000-8000-000000000003', '10000000-0000-7000-8000-000000000001', (SELECT id FROM scout_market.type_repas WHERE code = 'GOUTER'), CURRENT_DATE, 'Goûter fruité', 'EN_CAISSE'),
    ('60000000-0000-7000-8000-000000000004', '10000000-0000-7000-8000-000000000001', (SELECT id FROM scout_market.type_repas WHERE code = 'DINER'), CURRENT_DATE, 'Dîner végétarien', 'SCOUT_MARKET'),
    ('60000000-0000-7000-8000-000000000005', '10000000-0000-7000-8000-000000000001', (SELECT id FROM scout_market.type_repas WHERE code = 'DEJEUNER'), CURRENT_DATE + 1, 'Déjeuner du lendemain', 'EN_CAISSE'),
    ('60000000-0000-7000-8000-000000000006', '10000000-0000-7000-8000-000000000001', (SELECT id FROM scout_market.type_repas WHERE code = 'DINER'), CURRENT_DATE + 1, 'Dîner du lendemain', 'SCOUT_MARKET'),
    ('60000000-0000-7000-8000-000000000010', '10000000-0000-7000-8000-000000000002', (SELECT id FROM scout_market.type_repas WHERE code = 'PETIT_DEJEUNER'), CURRENT_DATE, 'Petit-déjeuner sportif', 'SCOUT_MARKET'),
    ('60000000-0000-7000-8000-000000000011', '10000000-0000-7000-8000-000000000002', (SELECT id FROM scout_market.type_repas WHERE code = 'DEJEUNER'), CURRENT_DATE, 'Déjeuner de l’aventure', 'SCOUT_MARKET'),
    ('60000000-0000-7000-8000-000000000012', '10000000-0000-7000-8000-000000000002', (SELECT id FROM scout_market.type_repas WHERE code = 'GOUTER'), CURRENT_DATE, 'Goûter du sentier', 'EN_CAISSE'),
    ('60000000-0000-7000-8000-000000000013', '10000000-0000-7000-8000-000000000002', (SELECT id FROM scout_market.type_repas WHERE code = 'DINER'), CURRENT_DATE, 'Dîner du bivouac', 'EN_CAISSE'),
    ('60000000-0000-7000-8000-000000000014', '10000000-0000-7000-8000-000000000002', (SELECT id FROM scout_market.type_repas WHERE code = 'DEJEUNER'), CURRENT_DATE + 1, 'Déjeuner couscous', 'SCOUT_MARKET'),
    ('60000000-0000-7000-8000-000000000015', '10000000-0000-7000-8000-000000000002', (SELECT id FROM scout_market.type_repas WHERE code = 'DINER'), CURRENT_DATE + 1, 'Dîner lentilles', 'EN_CAISSE'),
    ('60000000-0000-7000-8000-000000000019', '10000000-0000-7000-8000-000000000003', (SELECT id FROM scout_market.type_repas WHERE code = 'PETIT_DEJEUNER'), CURRENT_DATE - 1, 'Petit-déjeuner du week-end', 'SCOUT_MARKET'),
    ('60000000-0000-7000-8000-000000000020', '10000000-0000-7000-8000-000000000003', (SELECT id FROM scout_market.type_repas WHERE code = 'DEJEUNER'), CURRENT_DATE - 1, 'Déjeuner du camp', 'EN_CAISSE'),
    ('60000000-0000-7000-8000-000000000021', '10000000-0000-7000-8000-000000000003', (SELECT id FROM scout_market.type_repas WHERE code = 'GOUTER'), CURRENT_DATE - 1, 'Goûter aux pommes', 'EN_CAISSE'),
    ('60000000-0000-7000-8000-000000000022', '10000000-0000-7000-8000-000000000003', (SELECT id FROM scout_market.type_repas WHERE code = 'DINER'), CURRENT_DATE - 1, 'Dîner sous les étoiles', 'SCOUT_MARKET'),
    ('60000000-0000-7000-8000-000000000023', '10000000-0000-7000-8000-000000000003', (SELECT id FROM scout_market.type_repas WHERE code = 'PETIT_DEJEUNER'), CURRENT_DATE, 'Brunch du dimanche', 'SCOUT_MARKET'),
    ('60000000-0000-7000-8000-000000000024', '10000000-0000-7000-8000-000000000003', (SELECT id FROM scout_market.type_repas WHERE code = 'DEJEUNER'), CURRENT_DATE, 'Déjeuner de clôture', 'EN_CAISSE');

INSERT INTO scout_market.menu (id, grille_menu_id, special_code, nom)
VALUES
    ('60000000-0000-7000-8000-000000000007', '10000000-0000-7000-8000-000000000001', 'EXPLO', 'Repas d’exploration'),
    ('60000000-0000-7000-8000-000000000008', '10000000-0000-7000-8000-000000000001', 'PIQUE_NIQUE_1', 'Pique-nique froid'),
    ('60000000-0000-7000-8000-000000000009', '10000000-0000-7000-8000-000000000001', 'PIQUE_NIQUE_2', 'Pique-nique chaud'),
    ('60000000-0000-7000-8000-000000000016', '10000000-0000-7000-8000-000000000002', 'EXPLO', 'Explo grande aventure'),
    ('60000000-0000-7000-8000-000000000017', '10000000-0000-7000-8000-000000000002', 'PIQUE_NIQUE_1', 'Pique-nique du sentier'),
    ('60000000-0000-7000-8000-000000000018', '10000000-0000-7000-8000-000000000002', 'PIQUE_NIQUE_2', 'Pique-nique du sommet'),
    ('60000000-0000-7000-8000-000000000025', '10000000-0000-7000-8000-000000000003', 'EXPLO', 'Explo nature'),
    ('60000000-0000-7000-8000-000000000026', '10000000-0000-7000-8000-000000000003', 'PIQUE_NIQUE_1', 'Pique-nique forêt'),
    ('60000000-0000-7000-8000-000000000027', '10000000-0000-7000-8000-000000000003', 'PIQUE_NIQUE_2', 'Pique-nique prairie');

WITH menus_recettes(menu_id, recette_id, recette_instance_id) AS (VALUES
    ('60000000-0000-7000-8000-000000000001'::uuid, '50000000-0000-7000-8000-000000000001'::uuid, '62000000-0000-7000-8000-000000000001'::uuid),
    ('60000000-0000-7000-8000-000000000002'::uuid, '50000000-0000-7000-8000-000000000002'::uuid, '62000000-0000-7000-8000-000000000002'::uuid),
    ('60000000-0000-7000-8000-000000000002'::uuid, '50000000-0000-7000-8000-000000000003'::uuid, '62000000-0000-7000-8000-000000000003'::uuid),
    ('60000000-0000-7000-8000-000000000003'::uuid, '50000000-0000-7000-8000-000000000005'::uuid, '62000000-0000-7000-8000-000000000004'::uuid),
    ('60000000-0000-7000-8000-000000000004'::uuid, '50000000-0000-7000-8000-000000000004'::uuid, '62000000-0000-7000-8000-000000000005'::uuid),
    ('60000000-0000-7000-8000-000000000005'::uuid, '50000000-0000-7000-8000-000000000003'::uuid, '62000000-0000-7000-8000-000000000006'::uuid),
    ('60000000-0000-7000-8000-000000000006'::uuid, '50000000-0000-7000-8000-000000000004'::uuid, '62000000-0000-7000-8000-000000000007'::uuid),
    ('60000000-0000-7000-8000-000000000007'::uuid, '50000000-0000-7000-8000-000000000005'::uuid, '62000000-0000-7000-8000-000000000008'::uuid),
    ('60000000-0000-7000-8000-000000000008'::uuid, '50000000-0000-7000-8000-000000000003'::uuid, '62000000-0000-7000-8000-000000000009'::uuid),
    ('60000000-0000-7000-8000-000000000009'::uuid, '50000000-0000-7000-8000-000000000004'::uuid, '62000000-0000-7000-8000-000000000010'::uuid),
    ('60000000-0000-7000-8000-000000000010'::uuid, '50000000-0000-7000-8000-000000000001'::uuid, '62000000-0000-7000-8000-000000000011'::uuid),
    ('60000000-0000-7000-8000-000000000011'::uuid, '50000000-0000-7000-8000-000000000002'::uuid, '62000000-0000-7000-8000-000000000012'::uuid),
    ('60000000-0000-7000-8000-000000000011'::uuid, '50000000-0000-7000-8000-000000000003'::uuid, '62000000-0000-7000-8000-000000000013'::uuid),
    ('60000000-0000-7000-8000-000000000012'::uuid, '50000000-0000-7000-8000-000000000005'::uuid, '62000000-0000-7000-8000-000000000014'::uuid),
    ('60000000-0000-7000-8000-000000000013'::uuid, '50000000-0000-7000-8000-000000000004'::uuid, '62000000-0000-7000-8000-000000000015'::uuid),
    ('60000000-0000-7000-8000-000000000014'::uuid, '50000000-0000-7000-8000-000000000003'::uuid, '62000000-0000-7000-8000-000000000016'::uuid),
    ('60000000-0000-7000-8000-000000000015'::uuid, '50000000-0000-7000-8000-000000000004'::uuid, '62000000-0000-7000-8000-000000000017'::uuid),
    ('60000000-0000-7000-8000-000000000016'::uuid, '50000000-0000-7000-8000-000000000005'::uuid, '62000000-0000-7000-8000-000000000018'::uuid),
    ('60000000-0000-7000-8000-000000000017'::uuid, '50000000-0000-7000-8000-000000000003'::uuid, '62000000-0000-7000-8000-000000000019'::uuid),
    ('60000000-0000-7000-8000-000000000018'::uuid, '50000000-0000-7000-8000-000000000004'::uuid, '62000000-0000-7000-8000-000000000020'::uuid),
    ('60000000-0000-7000-8000-000000000019'::uuid, '50000000-0000-7000-8000-000000000001'::uuid, '62000000-0000-7000-8000-000000000021'::uuid),
    ('60000000-0000-7000-8000-000000000020'::uuid, '50000000-0000-7000-8000-000000000003'::uuid, '62000000-0000-7000-8000-000000000022'::uuid),
    ('60000000-0000-7000-8000-000000000021'::uuid, '50000000-0000-7000-8000-000000000005'::uuid, '62000000-0000-7000-8000-000000000023'::uuid),
    ('60000000-0000-7000-8000-000000000022'::uuid, '50000000-0000-7000-8000-000000000004'::uuid, '62000000-0000-7000-8000-000000000024'::uuid),
    ('60000000-0000-7000-8000-000000000023'::uuid, '50000000-0000-7000-8000-000000000001'::uuid, '62000000-0000-7000-8000-000000000025'::uuid),
    ('60000000-0000-7000-8000-000000000024'::uuid, '50000000-0000-7000-8000-000000000002'::uuid, '62000000-0000-7000-8000-000000000026'::uuid),
    ('60000000-0000-7000-8000-000000000024'::uuid, '50000000-0000-7000-8000-000000000003'::uuid, '62000000-0000-7000-8000-000000000027'::uuid),
    ('60000000-0000-7000-8000-000000000025'::uuid, '50000000-0000-7000-8000-000000000005'::uuid, '62000000-0000-7000-8000-000000000028'::uuid),
    ('60000000-0000-7000-8000-000000000026'::uuid, '50000000-0000-7000-8000-000000000003'::uuid, '62000000-0000-7000-8000-000000000029'::uuid),
    ('60000000-0000-7000-8000-000000000027'::uuid, '50000000-0000-7000-8000-000000000004'::uuid, '62000000-0000-7000-8000-000000000030'::uuid)
)
INSERT INTO scout_market.menu_denree
    (menu_id, denree_id, conditionnement_id, regime, recette_id, recette_instance_id, categorie, ordre)
SELECT
    menus_recettes.menu_id,
    recette_denree.denree_id,
    recette_denree.conditionnement_id,
    recette_denree.regime,
    menus_recettes.recette_id,
    menus_recettes.recette_instance_id,
    CASE WHEN recette.categorie IN ('ENTREE', 'PLAT', 'FROMAGE', 'DESSERT') THEN recette.categorie ELSE NULL END,
    recette_denree.ordre
FROM menus_recettes
JOIN scout_market.recette_denree ON recette_denree.recette_id = menus_recettes.recette_id
JOIN scout_market.recette ON recette.id = menus_recettes.recette_id;

INSERT INTO scout_market.menu_denree_quantite (menu_denree_id, public_cible_id, quantite_individuelle)
SELECT menu_denree.id, recette_quantite.public_cible_id, recette_quantite.quantite_individuelle
FROM scout_market.menu_denree
JOIN scout_market.recette_denree
    ON recette_denree.recette_id = menu_denree.recette_id
    AND recette_denree.denree_id = menu_denree.denree_id
    AND recette_denree.ordre = menu_denree.ordre
JOIN scout_market.recette_denree_quantite recette_quantite
    ON recette_quantite.recette_denree_id = recette_denree.id
WHERE menu_denree.menu_id::text LIKE '60000000-0000-7000-8000-%';

INSERT INTO scout_market.groupe
    (id, grille_menu_id, nom, effectif_jeune, effectif_adulte, nombre_vegetariens, nombre_sans_lactose, nombre_sans_gluten, type, date_debut_presence, date_fin_presence)
VALUES
    ('70000000-0000-7000-8000-000000000001', '10000000-0000-7000-8000-000000000001', 'Farfadets de la Clairière', 18, 4, 2, 1, 1, 'farfadets', CURRENT_DATE - 1, CURRENT_DATE + 2),
    ('70000000-0000-7000-8000-000000000002', '10000000-0000-7000-8000-000000000001', 'Louveteaux de la Rivière', 26, 5, 3, 2, 1, 'louveteaux-jeannettes', CURRENT_DATE - 2, CURRENT_DATE + 4),
    ('70000000-0000-7000-8000-000000000003', '10000000-0000-7000-8000-000000000002', 'Scouts-Guides des Étoiles', 32, 6, 5, 1, 2, 'scouts-guides', CURRENT_DATE, CURRENT_DATE + 6),
    ('70000000-0000-7000-8000-000000000004', '10000000-0000-7000-8000-000000000002', 'Pionniers du Levant', 21, 4, 4, 2, 2, 'pionniers-caravelles', CURRENT_DATE + 1, CURRENT_DATE + 8),
    ('70000000-0000-7000-8000-000000000005', '10000000-0000-7000-8000-000000000003', 'Unité rentrée à la maison', 14, 3, 1, 0, 1, 'scouts-guides', CURRENT_DATE - 8, CURRENT_DATE - 1);

INSERT INTO scout_market.utilisateur
    (id, groupe_id, email, mot_de_passe, prenom, nom, roles, actif, changement_mot_de_passe_requis)
VALUES
    ('71000000-0000-7000-8000-000000000001', NULL, 'gestionnaire@scout-market.local', '$2y$13$c7t8yYrYN.Uc2XT9rs9cReNewx29NZ8Hl0XeG/p1kozsGbIz9tHl2', 'Camille', 'Gestionnaire', '["ROLE_GESTIONNAIRE"]'::jsonb, TRUE, FALSE),
    ('71000000-0000-7000-8000-000000000002', '70000000-0000-7000-8000-000000000001', 'farfadets@scout-market.local', '$2y$13$c7t8yYrYN.Uc2XT9rs9cReNewx29NZ8Hl0XeG/p1kozsGbIz9tHl2', 'Alice', 'Farfadets', '["ROLE_GROUPE"]'::jsonb, TRUE, FALSE),
    ('71000000-0000-7000-8000-000000000003', '70000000-0000-7000-8000-000000000003', 'scouts@scout-market.local', '$2y$13$c7t8yYrYN.Uc2XT9rs9cReNewx29NZ8Hl0XeG/p1kozsGbIz9tHl2', 'Sam', 'Scouts-Guides', '["ROLE_GROUPE"]'::jsonb, TRUE, FALSE);

INSERT INTO scout_market.groupe_repas (groupe_id, menu_id, mode)
VALUES
    ('70000000-0000-7000-8000-000000000001', '60000000-0000-7000-8000-000000000005', 'PIQUE_NIQUE_1'),
    ('70000000-0000-7000-8000-000000000002', '60000000-0000-7000-8000-000000000005', 'EXPLO'),
    ('70000000-0000-7000-8000-000000000003', '60000000-0000-7000-8000-000000000015', 'NON_PRIS'),
    ('70000000-0000-7000-8000-000000000004', '60000000-0000-7000-8000-000000000014', 'PIQUE_NIQUE_2'),
    ('70000000-0000-7000-8000-000000000005', '60000000-0000-7000-8000-000000000020', 'EXPLO');

INSERT INTO scout_market.mouvement_stock
    (id, utilisateur_id, type_mouvement_id, origine_mouvement_id, date_mouvement)
VALUES (
    '80000000-0000-7000-8000-000000000001',
    '71000000-0000-7000-8000-000000000001',
    (SELECT id FROM scout_market.type_mouvement WHERE code = 'ENTREE'),
    (SELECT id FROM scout_market.origine_mouvement WHERE code = 'INVENTAIRE'),
    CURRENT_TIMESTAMP - INTERVAL '2 days'
);

INSERT INTO scout_market.mouvement_stock_ligne
    (mouvement_stock_id, denree_id, conditionnement_saisie_id, quantite_saisie, numero_lot)
SELECT
    '80000000-0000-7000-8000-000000000001',
    denree_id::uuid,
    (SELECT id FROM scout_market.unite WHERE symbole = unite_symbole),
    quantite,
    lot
FROM (VALUES
    ('30000000-0000-7000-8000-000000000001', 'L', 48.000, 'LAIT-DEMO-01'),
    ('30000000-0000-7000-8000-000000000002', 'kg', 18.000, 'AVOINE-DEMO-01'),
    ('30000000-0000-7000-8000-000000000003', 'kg', 35.000, 'PATES-DEMO-01'),
    ('30000000-0000-7000-8000-000000000004', 'kg', 24.000, 'TOMATES-DEMO-01'),
    ('30000000-0000-7000-8000-000000000005', 'kg', 16.000, 'BOEUF-DEMO-01'),
    ('30000000-0000-7000-8000-000000000006', 'kg', 22.000, 'LENTILLES-DEMO-01'),
    ('30000000-0000-7000-8000-000000000007', 'kg', 30.000, 'POMMES-DEMO-01'),
    ('30000000-0000-7000-8000-000000000008', 'pc', 96.000, 'BANANES-DEMO-01'),
    ('30000000-0000-7000-8000-000000000009', 'kg', 28.000, 'PAIN-DEMO-01'),
    ('30000000-0000-7000-8000-000000000010', 'kg', 30.000, 'RIZ-DEMO-01'),
    ('30000000-0000-7000-8000-000000000011', 'kg', 20.000, 'CAROTTES-DEMO-01'),
    ('30000000-0000-7000-8000-000000000012', 'pc', 120.000, 'YAOURTS-DEMO-01')
) AS inventaire(denree_id, unite_symbole, quantite, lot);

INSERT INTO scout_market.mouvement_stock
    (id, utilisateur_id, groupe_id, menu_id, type_mouvement_id, origine_mouvement_id, date_mouvement)
VALUES (
    '80000000-0000-7000-8000-000000000002',
    '71000000-0000-7000-8000-000000000001',
    '70000000-0000-7000-8000-000000000001',
    '60000000-0000-7000-8000-000000000002',
    (SELECT id FROM scout_market.type_mouvement WHERE code = 'SORTIE'),
    (SELECT id FROM scout_market.origine_mouvement WHERE code = 'DISTRIBUTION'),
    CURRENT_TIMESTAMP - INTERVAL '1 hour'
);

INSERT INTO scout_market.mouvement_stock_ligne
    (mouvement_stock_id, denree_id, conditionnement_saisie_id, quantite_saisie)
VALUES
    ('80000000-0000-7000-8000-000000000002', '30000000-0000-7000-8000-000000000003', (SELECT id FROM scout_market.unite WHERE symbole = 'kg'), 2.200),
    ('80000000-0000-7000-8000-000000000002', '30000000-0000-7000-8000-000000000004', (SELECT id FROM scout_market.unite WHERE symbole = 'kg'), 1.800);

UPDATE scout_market.configuration_distribution
SET distribution_publique_active = TRUE,
    distribuer_gouter_dejeuner = TRUE,
    updated_at = CURRENT_TIMESTAMP;
