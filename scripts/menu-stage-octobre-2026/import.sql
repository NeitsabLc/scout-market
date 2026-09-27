-- Import du menu des stages du 17 au 24 octobre 2026.
-- Source : « Menu + Fiches recettes STAGE.pdf » et liste fournisseur XLSX.
-- Ce fichier est exécuté dans une transaction par importer.sh.

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'scout_market'
          AND table_name = 'grille_menu'
          AND column_name = 'type_distribution'
    ) THEN
        RAISE EXCEPTION 'Le schéma V002 ou supérieur est requis.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM scout_market.public_cible WHERE code = 'ADULTE' AND actif) THEN
        RAISE EXCEPTION 'Le public ADULTE actif est introuvable.';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM scout_market.fournisseur WHERE lower(nom) = 'pro à pro' AND actif) THEN
        RAISE EXCEPTION 'Le fournisseur actif Pro à pro est introuvable.';
    END IF;
END
$$;

-- Unités présentes dans les documents mais absentes du socle initial.
INSERT INTO scout_market.unite (nom, symbole, utilisable_conditionnement)
VALUES ('conserve', 'conserve', TRUE), ('plaquette', 'plaquette', TRUE), ('tranche', 'tr', TRUE)
ON CONFLICT (symbole) DO NOTHING;

CREATE FUNCTION pg_temp.normaliser(texte text) RETURNS text
LANGUAGE sql IMMUTABLE STRICT AS $$
    SELECT regexp_replace(
        translate(lower($1),
            'àâäáãåçéèêëíìîïñóòôöõúùûüýÿœæ’''-',
            'aaaaaaceeeeiiiinooooouuuuyyoeae   '),
        '[^a-z0-9]+', '', 'g'
    )
$$;

CREATE TEMP TABLE _denree_spec (
    cle text PRIMARY KEY,
    nom text NOT NULL,
    type text NOT NULL,
    unite_reference text NOT NULL,
    unite_inventaire text NOT NULL
) ON COMMIT DROP;

INSERT INTO _denree_spec VALUES
('ail','Ail','FRUITS_LEGUMES','g','kg'),
('banane','Banane','FRUITS_LEGUMES','pc','pc'),
('betterave','Betterave','FRUITS_LEGUMES','g','kg'),
('beurre_doux','Beurre doux','FRAIS','g','kg'),
('beurre_sale','Beurre salé','FRAIS','g','kg'),
('ble','Blé','SEC','g','kg'),
('bouillon','Bouillon de légumes','SEC','g','kg'),
('brie','Brie','FRAIS','g','kg'),
('brioche','Brioche','SEC','tr','sachet'),
('brownie','Brownie','SEC','pc','pc'),
('butternut','Butternut','FRUITS_LEGUMES','g','kg'),
('camembert','Camembert','FRAIS','pc','pc'),
('carotte','Carottes','FRUITS_LEGUMES','g','kg'),
('chapelure','Chapelure','SEC','g','kg'),
('champignon','Champignons','FRUITS_LEGUMES','g','kg'),
('chevre','Fromage de chèvre','FRAIS','g','kg'),
('chocolat','Chocolat','SEC','plaquette','plaquette'),
('chou_rouge','Chou rouge','FRUITS_LEGUMES','g','kg'),
('chips','Chips','SEC','g','kg'),
('citron','Citron','FRUITS_LEGUMES','kg','kg'),
('clementine','Clémentine','FRUITS_LEGUMES','pc','pc'),
('compote','Compote de pomme','SEC','g','kg'),
('cookie','Cookie','SEC','pc','pc'),
('cornichon','Cornichons','SEC','conserve','conserve'),
('courgette','Courgette','FRUITS_LEGUMES','g','kg'),
('creme_choco','Crème dessert chocolat','FRAIS','pc','pc'),
('creme_fraiche','Crème fraîche','FRAIS','g','kg'),
('creme_marron','Crème de marron','SEC','g','kg'),
('crepe','Crêpe','SEC','pc','pc'),
('croutons','Croûtons','SEC','g','kg'),
('crozets','Crozets','SEC','g','kg'),
('edam','Edam','FRAIS','g','kg'),
('emmental_rape','Emmental râpé','FRAIS','g','kg'),
('endive','Endive','FRUITS_LEGUMES','g','kg'),
('epices_chili','Épices chili','SEC','g','kg'),
('farine','Farine de blé','SEC','g','kg'),
('falafels','Falafels','FRAIS','g','kg'),
('flamby','Flamby','FRAIS','pc','pc'),
('flan','Flan','FRAIS','pc','pc'),
('fromage_blanc','Fromage blanc','FRAIS','g','kg'),
('fromage_tartiflette','Fromage à tartiflette','FRAIS','g','kg'),
('fromage_tartiner','Fromage à tartiner','FRAIS','pc','pc'),
('gnocchi','Gnocchi','FRAIS','g','kg'),
('haricots_rouges','Haricots rouges','SEC','g','kg'),
('haricots_verts','Haricots verts','SEC','g','kg'),
('huile_olive','Huile d''olive','SEC','bouteille','bouteille'),
('lait','Lait UHT','FRAIS','brique','brique'),
('lait_coco','Lait de coco','SEC','mL','L'),
('lentilles_corail','Lentilles corail','SEC','g','kg'),
('legumes_couscous','Légumes pour couscous','SEC','conserve','conserve'),
('levure_boulanger','Levure boulangère','SEC','sachet','sachet'),
('levure_chimique','Levure chimique','SEC','g','kg'),
('lieu','Lieu surg','FRAIS','g','kg'),
('mache','Mâche','FRUITS_LEGUMES','g','kg'),
('mais','Maïs','SEC','g','kg'),
('marbre','Marbré','SEC','pc','pc'),
('merguez','Merguez','FRAIS','pc','pc'),
('mimolette','Mimolette','FRAIS','g','kg'),
('mousse_choco','Mousse au chocolat','FRAIS','pc','pc'),
('mozzarella','Mozzarella','FRAIS','g','kg'),
('navet','Navet','FRUITS_LEGUMES','g','kg'),
('oeuf','Œuf','FRAIS','pc','pc'),
('oignon','Oignons','FRUITS_LEGUMES','g','kg'),
('orge','Orge perlé','SEC','g','kg'),
('pain_400','Pain de 400 g','FRAIS','pc','pc'),
('pain_baguette','Baguette','FRAIS','pc','pc'),
('pain_epices','Pain d''épices','SEC','pc','pc'),
('palet_vege','Palet végé surg','FRAIS','pc','pc'),
('parmesan','Parmesan','FRAIS','g','kg'),
('pates','Pâtes','SEC','g','kg'),
('pesto','Pesto','SEC','g','kg'),
('poire','Poire','FRUITS_LEGUMES','pc','pc'),
('poireau','Poireau','FRUITS_LEGUMES','g','kg'),
('pois_chiches','Pois chiches','SEC','g','kg'),
('poivron','Poivron','FRUITS_LEGUMES','g','kg'),
('pomme','Pomme','FRUITS_LEGUMES','pc','pc'),
('pomme_terre','Pommes de terre','FRUITS_LEGUMES','g','kg'),
('potiron','Potiron','FRUITS_LEGUMES','g','kg'),
('prune','Prunes','FRUITS_LEGUMES','g','kg'),
('raisin','Raisin','FRUITS_LEGUMES','g','kg'),
('ravioles','Ravioles','FRAIS','pc','pc'),
('riz','Riz','SEC','g','kg'),
('riz_risotto','Riz à risotto','SEC','g','kg'),
('salade_verte','Salade verte','FRUITS_LEGUMES','g','kg'),
('saucisse_vege','Saucisse végétale','FRAIS','g','kg'),
('semoule','Semoule moyenne','SEC','g','kg'),
('sel','Sel','SEC','g','kg'),
('steak_hache','Steak haché','FRAIS','pc','pc'),
('sucre','Sucre','SEC','g','kg'),
('taboule','Taboulé','FRAIS','g','kg'),
('thon','Miettes de thon','SEC','conserve','conserve'),
('tomate_fraiche','Tomates','FRUITS_LEGUMES','g','kg'),
('tomate_pelee','Tomates entières pelées','SEC','g','kg'),
('tortilla','Tortillas','SEC','pc','pc'),
('veloute_7','Velouté 7 légumes','SEC','brique','brique'),
('veloute_champignon','Velouté de champignon','SEC','brique','brique'),
('veloute_potiron','Velouté de potiron','SEC','brique','brique'),
('veloute_tomate','Velouté de tomate','SEC','brique','brique'),
('veloute_vert','Velouté de légumes verts','SEC','brique','brique'),
('viande_hachee','Viande hachée','FRAIS','g','kg'),
('vinaigre','Vinaigre balsamique','SEC','bouteille','bouteille'),
('yaourt_compote','Yaourt compote','FRAIS','pc','pc'),
('yaourt_nature','Yaourt nature','FRAIS','pc','pc');

CREATE TEMP TABLE _alias (cle text, nom text, priorite int) ON COMMIT DROP;
INSERT INTO _alias
SELECT cle, nom, 1 FROM _denree_spec;
INSERT INTO _alias VALUES
('banane','Bananes',2),('carotte','Carotte',2),('clementine','Clémentines',2),
('compote','Compote de pommes',2),('cookie','Cookies',2),('courgette','Courgettes',2),
('creme_choco','Crème chocolat',2),('crozets','Crozet',2),
('emmental_rape','Fromage râpé',2),('farine','Farine',2),
('fromage_tartiflette','Fromage tartiflette',2),('fromage_tartiner','Fromage à tartiner portion',2),
('haricots_rouges','Haricots rouges cuits',2),('lait','Lait',2),
('legumes_couscous','Légumes couscous',2),('lieu','Filet de lieu',2),
('mache','Mâche salade',2),('mais','Maïs conserve',2),('mousse_choco','Mousse chocolat',2),
('oeuf','Œufs',2),('oignon','Oignon',2),('pain_400','Pain 400g',2),
('palet_vege','Steak VG',2),('poire','Poires',2),('poireau','Poireaux',2),
('pois_chiches','Pois chiches cuits',2),('pomme','Pommes',2),
('pomme_terre','Pomme de terre',2),('prune','Prune',2),('ravioles','Raviole',2),
('riz_risotto','Riz rond à risotto',2),('saucisse_vege','Saucisses VG',2),
('taboule','Taboulay',2),('thon','Thon',2),('tomate_pelee','Tomates pelées',2),
('tortilla','Galettes fajitas',2),('veloute_7','Velouté de 7 légumes',2),
('veloute_vert','Velouté de poireaux',2),('viande_hachee','Bœuf haché',3),
('yaourt_nature','Yaourts nature',2);

CREATE TEMP TABLE _denree_resolue ON COMMIT DROP AS
SELECT s.*, COALESCE(existant.id, uuidv7()) AS id, existant.id IS NULL AS a_creer
FROM _denree_spec s
LEFT JOIN LATERAL (
    SELECT d.id
    FROM _alias a
    JOIN scout_market.denree d ON pg_temp.normaliser(d.nom) = pg_temp.normaliser(a.nom)
    WHERE a.cle = s.cle
    ORDER BY a.priorite, d.created_at, d.id
    LIMIT 1
) existant ON TRUE;

INSERT INTO scout_market.denree (id, nom, type, unite_reference_id, unite_inventaire_id)
SELECT r.id, r.nom, r.type, ur.id, ui.id
FROM _denree_resolue r
JOIN scout_market.unite ur ON ur.symbole = r.unite_reference
JOIN scout_market.unite ui ON ui.symbole = r.unite_inventaire
WHERE r.a_creer;

-- Le lieu existant passe explicitement en grammes puis kilogrammes.
UPDATE scout_market.denree d
SET unite_reference_id = (SELECT id FROM scout_market.unite WHERE symbole = 'g'),
    unite_inventaire_id = (SELECT id FROM scout_market.unite WHERE symbole = 'kg'),
    updated_at = CURRENT_TIMESTAMP
FROM _denree_resolue r
WHERE r.cle = 'lieu' AND d.id = r.id;

CREATE TEMP TABLE _ref_fournisseur (
    cle text PRIMARY KEY,
    reference text NOT NULL,
    fournisseur_impose text
) ON COMMIT DROP;
INSERT INTO _ref_fournisseur VALUES
('falafels','161318',NULL),('yaourt_compote','166284',NULL),('saucisse_vege','167192',NULL),
('cornichon','104968',NULL),('thon','100085',NULL),('chips','160856',NULL),
('veloute_7','31903',NULL),('croutons','60439',NULL),('pates','100956',NULL),
('tomate_pelee','01743','Pro à pro'),('huile_olive','22452',NULL),('vinaigre','104216',NULL),
('tortilla','17282',NULL),('haricots_rouges','01468',NULL),('mais','10384',NULL),
('farine','13005',NULL),('sucre','53146',NULL),('marbre','112067',NULL),
('veloute_vert','31317',NULL),('lentilles_corail','11311',NULL),('lait_coco','161035',NULL),
('riz','11139',NULL),('pois_chiches','05039',NULL),('brioche','100678',NULL),
('chocolat','71198',NULL),('veloute_tomate','31657',NULL),('semoule','11936',NULL),
('bouillon','107046',NULL),('veloute_champignon','74059',NULL),('creme_marron','166071',NULL),
('brownie','64526',NULL),('haricots_verts','10387',NULL),('riz_risotto','11027',NULL),
('orge','167474',NULL),('compote','153852',NULL),('crepe','157585',NULL),
('ble','160962',NULL),('chapelure','113630',NULL),('creme_choco','167390',NULL),
('legumes_couscous','105008',NULL),('pain_epices','64994',NULL),('veloute_potiron','34320',NULL),
('crozets','17144',NULL),('pesto','155431',NULL),('taboule','160020',NULL),
('mimolette','36799',NULL),('emmental_rape','157541',NULL),('yaourt_nature','161204',NULL),
('beurre_sale','67317',NULL),('beurre_doux','67156',NULL),('fromage_blanc','39344',NULL),
('chevre','151882',NULL),('flamby','27655',NULL),('mozzarella','110348',NULL),
('edam','157466',NULL),('merguez','158224',NULL),('flan','165152',NULL),
('creme_fraiche','39140',NULL),('mousse_choco','40478',NULL),('parmesan','55987',NULL),
('camembert','59017',NULL),('fromage_tartiner','67276',NULL),('fromage_tartiflette','102364',NULL),
('brie','38916',NULL),('cookie','167694',NULL),('viande_hachee','152006',NULL),
('poulet','152153',NULL),('gnocchi','155607',NULL),('ravioles','153271',NULL),
('steak_hache','157473',NULL),('lieu','154765','Pro à pro');

-- Le poulet est ajouté séparément afin de conserver un libellé existant fréquent.
INSERT INTO _denree_spec VALUES ('poulet','Blanc de poulet','FRAIS','g','kg');
INSERT INTO _alias VALUES ('poulet','Blanc de poulet',1),('poulet','Blancs de poulet',2);
INSERT INTO _denree_resolue
SELECT s.*, COALESCE(existant.id, uuidv7()), existant.id IS NULL
FROM _denree_spec s
LEFT JOIN LATERAL (
    SELECT d.id FROM _alias a
    JOIN scout_market.denree d ON pg_temp.normaliser(d.nom) = pg_temp.normaliser(a.nom)
    WHERE a.cle = s.cle ORDER BY a.priorite, d.created_at, d.id LIMIT 1
) existant ON TRUE
WHERE s.cle = 'poulet';
INSERT INTO scout_market.denree (id, nom, type, unite_reference_id, unite_inventaire_id)
SELECT r.id, r.nom, r.type, ur.id, ui.id
FROM _denree_resolue r
JOIN scout_market.unite ur ON ur.symbole=r.unite_reference
JOIN scout_market.unite ui ON ui.symbole=r.unite_inventaire
WHERE r.cle='poulet' AND r.a_creer;

DO $$
DECLARE
    ligne record;
    denree_uuid uuid;
    fournisseur_uuid uuid;
    reference_uuid uuid;
    nombre_liens integer;
BEGIN
    FOR ligne IN
        SELECT rf.*, dr.id AS denree_id
        FROM _ref_fournisseur rf JOIN _denree_resolue dr USING (cle)
        ORDER BY rf.cle
    LOOP
        denree_uuid := ligne.denree_id;
        IF EXISTS (
            SELECT 1 FROM scout_market.denree_fournisseur df
            JOIN scout_market.fournisseur f ON f.id=df.fournisseur_id
            WHERE df.reference=ligne.reference AND df.denree_id<>denree_uuid
              AND (ligne.fournisseur_impose IS NULL OR lower(f.nom)=lower(ligne.fournisseur_impose))
        ) THEN
            RAISE EXCEPTION 'Référence fournisseur % déjà rattachée à une autre denrée.', ligne.reference;
        END IF;

        IF ligne.fournisseur_impose IS NOT NULL THEN
            SELECT id INTO STRICT fournisseur_uuid FROM scout_market.fournisseur
            WHERE lower(nom)=lower(ligne.fournisseur_impose) AND actif;
            SELECT id INTO reference_uuid FROM scout_market.denree_fournisseur
            WHERE denree_id=denree_uuid AND fournisseur_id=fournisseur_uuid
            ORDER BY actif DESC, created_at LIMIT 1;

            IF reference_uuid IS NULL AND ligne.cle='lieu' THEN
                SELECT count(*), min(id::text)::uuid INTO nombre_liens, reference_uuid
                FROM scout_market.denree_fournisseur WHERE denree_id=denree_uuid AND actif;
                IF nombre_liens > 1 THEN
                    RAISE EXCEPTION 'Le lieu possède % fournisseurs actifs ; rattachement automatique refusé.', nombre_liens;
                END IF;
                IF reference_uuid IS NOT NULL THEN
                    UPDATE scout_market.denree_fournisseur
                    SET fournisseur_id=fournisseur_uuid, updated_at=CURRENT_TIMESTAMP
                    WHERE id=reference_uuid;
                END IF;
            END IF;
        ELSE
            SELECT count(*), min(id::text)::uuid, min(fournisseur_id::text)::uuid
            INTO nombre_liens, reference_uuid, fournisseur_uuid
            FROM scout_market.denree_fournisseur
            WHERE denree_id=denree_uuid AND actif;
            IF nombre_liens > 1 THEN
                RAISE EXCEPTION 'La denrée « % » possède % fournisseurs actifs ; cadrage requis.', ligne.cle, nombre_liens;
            END IF;
        END IF;

        IF reference_uuid IS NULL THEN
            IF fournisseur_uuid IS NULL THEN
                SELECT id INTO STRICT fournisseur_uuid FROM scout_market.fournisseur
                WHERE lower(nom)='pro à pro' AND actif;
            END IF;
            INSERT INTO scout_market.denree_fournisseur
                (fournisseur_id, denree_id, reference, principal)
            VALUES (
                fournisseur_uuid, denree_uuid, ligne.reference,
                NOT EXISTS (SELECT 1 FROM scout_market.denree_fournisseur WHERE denree_id=denree_uuid AND actif AND principal)
            ) RETURNING id INTO reference_uuid;
        ELSE
            UPDATE scout_market.denree_fournisseur
            SET reference=ligne.reference, actif=TRUE, updated_at=CURRENT_TIMESTAMP
            WHERE id=reference_uuid;
        END IF;
        reference_uuid := NULL;
        fournisseur_uuid := NULL;
    END LOOP;
END
$$;

-- Garantit la conversion kg -> g du lieu sans inventer un poids de colis fournisseur.
INSERT INTO scout_market.denree_fournisseur_conditionnement
    (reference_fournisseur_id, ordre, libelle, quantite_contenu, unite_contenu_id, conditionnement_id)
SELECT df.id, COALESCE((SELECT max(c.ordre)+1 FROM scout_market.denree_fournisseur_conditionnement c WHERE c.reference_fournisseur_id=df.id),1),
       'Kilogramme', 1000, ug.id, uk.id
FROM _denree_resolue dr
JOIN scout_market.denree_fournisseur df ON df.denree_id=dr.id AND df.actif
JOIN scout_market.fournisseur f ON f.id=df.fournisseur_id AND lower(f.nom)='pro à pro'
JOIN scout_market.unite ug ON ug.symbole='g'
JOIN scout_market.unite uk ON uk.symbole='kg'
WHERE dr.cle='lieu'
  AND NOT EXISTS (
      SELECT 1 FROM scout_market.denree_fournisseur_conditionnement c
      WHERE c.reference_fournisseur_id=df.id AND c.conditionnement_id=uk.id
  );

CREATE TEMP TABLE _recette (nom text PRIMARY KEY, categorie text NOT NULL) ON COMMIT DROP;
INSERT INTO _recette VALUES
('Bâtonnets de carotte','ENTREE'),('Taboulé au thon et chips','PLAT'),('Mimolette','FROMAGE'),
('Baguette','FROMAGE'),('Raisin - 150 g','DESSERT'),('Cookie','GOUTER'),('Clémentines','DESSERT'),
('Velouté de potiron, fromage et croûtons','ENTREE'),('Pâtes bolognaises','PLAT'),('Pomme','DESSERT'),
('Betterave vinaigrette','ENTREE'),('Fajitas','PLAT'),('Yaourt nature','DESSERT'),('Poire','DESSERT'),
('Marbré','GOUTER'),('Banane','DESSERT'),('Velouté de légumes verts','ENTREE'),
('Dahl de lentilles','PLAT'),('Fromage blanc sucré','DESSERT'),('Chèvres chauds','ENTREE'),
('Curry de légumes d’automne','PLAT'),('Flamby','DESSERT'),('Brioche','GOUTER'),
('Chocolat','GOUTER'),('Prunes','GOUTER'),('Velouté de tomate','ENTREE'),
('Gnocchi milanaise','PLAT'),('Edam','FROMAGE'),('Chou rouge aux pommes','ENTREE'),
('Couscous saucisse','PLAT'),('Pommes caramélisées','DESSERT'),('Flan','GOUTER'),
('Raisin - 100 g','GOUTER'),('Velouté de champignon','ENTREE'),
('Gratin de courgettes et ravioles','PLAT'),('Mousse au chocolat','DESSERT'),
('Salade mâche, endive et pomme','ENTREE'),('Steak, haricots verts et pommes de terre','PLAT'),
('Fromage blanc - crème de marron','DESSERT'),('Brownie','GOUTER'),
('Risotto champignon-butternut','PLAT'),('Camembert','FROMAGE'),('Compote de pomme','DESSERT'),
('Cheese naan','ENTREE'),('Chili con carne','PLAT'),('Crumble aux poires','DESSERT'),
('Crêpe','GOUTER'),('Velouté de 7 légumes, fromage et croûtons','ENTREE'),
('Blé, nuggets de pois chiches et fondue de poireaux','PLAT'),('Crème dessert chocolat','DESSERT'),
('Houmous carotte','ENTREE'),('Filet de lieu en papillote','PLAT'),('Pain perdu','DESSERT'),
('Pain d’épices','GOUTER'),('Velouté de potiron','ENTREE'),('Croziflette','PLAT'),
('Banane - chocolat','DESSERT'),('Sandwich pesto, mâche et brie','PLAT'),('Deux pommes','DESSERT');

INSERT INTO scout_market.recette (nom,categorie,actif)
SELECT nom,categorie,TRUE FROM _recette
ON CONFLICT (nom) DO UPDATE SET categorie=EXCLUDED.categorie, actif=TRUE, updated_at=CURRENT_TIMESTAMP;

CREATE TEMP TABLE _ligne_recette (
    recette text, cle text, unite text, regime text, quantite numeric(12,3), ordre smallint
) ON COMMIT DROP;

INSERT INTO _ligne_recette VALUES
('Bâtonnets de carotte','carotte','g',NULL,120,10),
('Taboulé au thon et chips','taboule','g',NULL,200,10),('Taboulé au thon et chips','cornichon','conserve',NULL,0.060,20),('Taboulé au thon et chips','thon','conserve',NULL,0.100,30),('Taboulé au thon et chips','chips','g',NULL,40,40),
('Mimolette','mimolette','g',NULL,30,10),('Baguette','pain_baguette','pc',NULL,0.120,10),
('Raisin - 150 g','raisin','g',NULL,150,10),('Cookie','cookie','pc',NULL,1,10),('Clémentines','clementine','pc',NULL,2,10),
('Velouté de potiron, fromage et croûtons','veloute_potiron','brique',NULL,0.160,10),('Velouté de potiron, fromage et croûtons','emmental_rape','g',NULL,25,20),('Velouté de potiron, fromage et croûtons','croutons','g',NULL,30,30),
('Pâtes bolognaises','pates','g',NULL,100,10),('Pâtes bolognaises','viande_hachee','g',NULL,100,20),('Pâtes bolognaises','oignon','g',NULL,40,30),('Pâtes bolognaises','ail','g',NULL,3,40),('Pâtes bolognaises','tomate_pelee','g',NULL,130,50),
('Pomme','pomme','pc',NULL,1,10),('Betterave vinaigrette','betterave','g',NULL,120,10),
('Fajitas','tortilla','pc',NULL,2,10),('Fajitas','poulet','g',NULL,160,20),('Fajitas','falafels','g','VEGETARIEN',150,30),('Fajitas','haricots_rouges','g',NULL,100,40),('Fajitas','mais','g',NULL,60,50),('Fajitas','poivron','g',NULL,80,60),('Fajitas','tomate_fraiche','g',NULL,100,70),('Fajitas','oignon','g',NULL,40,80),('Fajitas','emmental_rape','g',NULL,30,90),
('Yaourt nature','yaourt_nature','pc',NULL,1,10),('Poire','poire','pc',NULL,1,10),('Marbré','marbre','pc',NULL,0.125,10),('Banane','banane','pc',NULL,1,10),
('Velouté de légumes verts','veloute_vert','brique',NULL,0.160,10),
('Dahl de lentilles','lentilles_corail','g',NULL,100,10),('Dahl de lentilles','carotte','g',NULL,50,20),('Dahl de lentilles','oignon','g',NULL,40,30),('Dahl de lentilles','tomate_pelee','g',NULL,150,40),('Dahl de lentilles','lait_coco','mL',NULL,80,50),('Dahl de lentilles','ail','g',NULL,3,60),
('Fromage blanc sucré','fromage_blanc','g',NULL,180,10),
('Chèvres chauds','pain_400','pc',NULL,0.125,10),('Chèvres chauds','chevre','g',NULL,40,20),
('Curry de légumes d’automne','riz','g',NULL,100,10),('Curry de légumes d’automne','pois_chiches','g',NULL,150,20),('Curry de légumes d’automne','potiron','g',NULL,120,30),('Curry de légumes d’automne','carotte','g',NULL,80,40),('Curry de légumes d’automne','poireau','g',NULL,70,50),('Curry de légumes d’automne','oignon','g',NULL,40,60),('Curry de légumes d’automne','tomate_pelee','g',NULL,100,70),
('Flamby','flamby','pc',NULL,1,10),('Brioche','brioche','sachet',NULL,0.125,10),('Chocolat','chocolat','plaquette',NULL,0.250,10),('Prunes','prune','g',NULL,48,10),
('Velouté de tomate','veloute_tomate','brique',NULL,0.160,10),
('Gnocchi milanaise','gnocchi','g',NULL,300,10),('Gnocchi milanaise','tomate_pelee','g',NULL,180,20),('Gnocchi milanaise','mozzarella','g',NULL,60,30),('Gnocchi milanaise','oignon','g',NULL,40,40),('Gnocchi milanaise','ail','g',NULL,3,50),('Gnocchi milanaise','huile_olive','bouteille',NULL,0.010,60),
('Edam','edam','g',NULL,30,10),
('Chou rouge aux pommes','chou_rouge','g',NULL,120,10),('Chou rouge aux pommes','pomme','pc',NULL,0.533,20),('Chou rouge aux pommes','vinaigre','bouteille',NULL,0.004,30),
('Couscous saucisse','semoule','g',NULL,100,10),('Couscous saucisse','merguez','pc',NULL,2,20),('Couscous saucisse','saucisse_vege','g','VEGETARIEN',160,30),('Couscous saucisse','pois_chiches','g',NULL,80,40),('Couscous saucisse','carotte','g',NULL,100,50),('Couscous saucisse','courgette','g',NULL,100,60),('Couscous saucisse','navet','g',NULL,80,70),('Couscous saucisse','tomate_pelee','g',NULL,100,80),('Couscous saucisse','oignon','g',NULL,40,90),
('Pommes caramélisées','pomme','pc',NULL,1,10),('Flan','flan','pc',NULL,0.063,10),('Raisin - 100 g','raisin','g',NULL,100,10),
('Velouté de champignon','veloute_champignon','brique',NULL,0.160,10),
('Gratin de courgettes et ravioles','courgette','g',NULL,250,10),('Gratin de courgettes et ravioles','ravioles','pc',NULL,0.200,20),('Gratin de courgettes et ravioles','emmental_rape','g',NULL,60,30),('Gratin de courgettes et ravioles','creme_fraiche','g',NULL,80,40),('Gratin de courgettes et ravioles','oignon','g',NULL,40,50),
('Mousse au chocolat','mousse_choco','pc',NULL,1,10),
('Salade mâche, endive et pomme','mache','g',NULL,60,10),('Salade mâche, endive et pomme','endive','g',NULL,100,20),('Salade mâche, endive et pomme','pomme','pc',NULL,0.667,30),
('Steak, haricots verts et pommes de terre','steak_hache','pc',NULL,1,10),('Steak, haricots verts et pommes de terre','palet_vege','pc','VEGETARIEN',1,20),('Steak, haricots verts et pommes de terre','haricots_verts','g',NULL,150,30),('Steak, haricots verts et pommes de terre','pomme_terre','g',NULL,180,40),('Steak, haricots verts et pommes de terre','oignon','g',NULL,25,50),
('Fromage blanc - crème de marron','fromage_blanc','g',NULL,120,10),('Fromage blanc - crème de marron','creme_marron','g',NULL,50,20),
('Brownie','brownie','pc',NULL,0.125,10),
('Risotto champignon-butternut','riz_risotto','g',NULL,65,10),('Risotto champignon-butternut','orge','g',NULL,30,20),('Risotto champignon-butternut','butternut','g',NULL,120,30),('Risotto champignon-butternut','champignon','g',NULL,75,40),('Risotto champignon-butternut','oignon','g',NULL,40,50),('Risotto champignon-butternut','ail','g',NULL,3,60),('Risotto champignon-butternut','creme_fraiche','g',NULL,60,70),('Risotto champignon-butternut','parmesan','g',NULL,20,80),('Risotto champignon-butternut','bouillon','g',NULL,1,90),
('Camembert','camembert','pc',NULL,0.125,10),('Camembert','pain_400','pc',NULL,0.125,20),('Compote de pomme','compote','g',NULL,150,10),
('Cheese naan','farine','g',NULL,65,10),('Cheese naan','yaourt_nature','pc',NULL,1,20),('Cheese naan','huile_olive','bouteille',NULL,0.045,30),('Cheese naan','sel','g',NULL,5,40),('Cheese naan','levure_chimique','g',NULL,1,50),('Cheese naan','levure_boulanger','sachet',NULL,1,60),('Cheese naan','fromage_tartiner','pc',NULL,2,70),
('Chili con carne','viande_hachee','g',NULL,120,10),('Chili con carne','champignon','g','VEGETARIEN',150,20),('Chili con carne','haricots_rouges','g',NULL,120,30),('Chili con carne','tomate_pelee','g',NULL,150,40),('Chili con carne','mais','g',NULL,60,50),('Chili con carne','oignon','g',NULL,40,60),('Chili con carne','ail','g',NULL,1.500,70),('Chili con carne','epices_chili','g',NULL,5,80),('Chili con carne','huile_olive','bouteille',NULL,0.005,90),
('Crumble aux poires','poire','pc',NULL,2,10),('Crumble aux poires','farine','g',NULL,30,20),('Crumble aux poires','beurre_doux','g',NULL,20,30),('Crumble aux poires','sucre','g',NULL,10,40),
('Crêpe','crepe','pc',NULL,1,10),
('Velouté de 7 légumes, fromage et croûtons','veloute_7','brique',NULL,0.160,10),('Velouté de 7 légumes, fromage et croûtons','emmental_rape','g',NULL,25,20),('Velouté de 7 légumes, fromage et croûtons','croutons','g',NULL,30,30),
('Blé, nuggets de pois chiches et fondue de poireaux','ble','g',NULL,90,10),('Blé, nuggets de pois chiches et fondue de poireaux','pois_chiches','g',NULL,150,20),('Blé, nuggets de pois chiches et fondue de poireaux','poireau','g',NULL,200,30),('Blé, nuggets de pois chiches et fondue de poireaux','chapelure','g',NULL,30,40),('Blé, nuggets de pois chiches et fondue de poireaux','oeuf','pc',NULL,0.500,50),('Blé, nuggets de pois chiches et fondue de poireaux','creme_fraiche','g',NULL,40,60),('Blé, nuggets de pois chiches et fondue de poireaux','oignon','g',NULL,30,70),
('Crème dessert chocolat','creme_choco','pc',NULL,1,10),
('Houmous carotte','pois_chiches','g',NULL,60,10),('Houmous carotte','citron','g',NULL,15,20),('Houmous carotte','carotte','g',NULL,100,30),
('Filet de lieu en papillote','lieu','g',NULL,150,10),('Filet de lieu en papillote','legumes_couscous','conserve',NULL,0.250,20),('Filet de lieu en papillote','pomme_terre','g',NULL,125,30),
('Pain perdu','pain_400','pc',NULL,0.125,10),('Pain perdu','lait','brique',NULL,0.100,20),('Pain perdu','oeuf','pc',NULL,1,30),('Pain perdu','sucre','g',NULL,15,40),('Pain perdu','beurre_doux','g',NULL,10,50),
('Pain d’épices','pain_epices','pc',NULL,0.125,10),('Velouté de potiron','veloute_potiron','brique',NULL,0.160,10),
('Croziflette','crozets','g',NULL,100,10),('Croziflette','champignon','g',NULL,150,20),('Croziflette','oignon','g',NULL,40,30),('Croziflette','creme_fraiche','g',NULL,60,40),('Croziflette','fromage_tartiflette','g',NULL,80,50),('Croziflette','huile_olive','bouteille',NULL,0.005,60),('Croziflette','salade_verte','g',NULL,60,70),
('Banane - chocolat','banane','pc',NULL,1,10),('Banane - chocolat','chocolat','plaquette',NULL,0.250,20),
('Sandwich pesto, mâche et brie','pain_400','pc',NULL,0.333,10),('Sandwich pesto, mâche et brie','pesto','g',NULL,40,20),('Sandwich pesto, mâche et brie','brie','g',NULL,50,30),('Sandwich pesto, mâche et brie','mache','g',NULL,20,40),('Sandwich pesto, mâche et brie','chips','g',NULL,40,50),
('Deux pommes','pomme','pc',NULL,2,10);

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM _ligne_recette l LEFT JOIN _denree_resolue d USING(cle) WHERE d.id IS NULL) THEN
        RAISE EXCEPTION 'Au moins une denrée de recette n’a pas été résolue.';
    END IF;
    IF EXISTS (SELECT 1 FROM _ligne_recette l LEFT JOIN scout_market.unite u ON u.symbole=l.unite WHERE u.id IS NULL) THEN
        RAISE EXCEPTION 'Au moins une unité de recette est introuvable.';
    END IF;
END
$$;

DELETE FROM scout_market.recette_denree rd
USING scout_market.recette r, _recette cible
WHERE rd.recette_id=r.id AND r.nom=cible.nom;

INSERT INTO scout_market.recette_denree
    (recette_id,denree_id,conditionnement_id,regime,ordre)
SELECT r.id,d.id,u.id,l.regime,l.ordre
FROM _ligne_recette l
JOIN scout_market.recette r ON r.nom=l.recette
JOIN _denree_resolue d USING(cle)
JOIN scout_market.unite u ON u.symbole=l.unite;

INSERT INTO scout_market.recette_denree_quantite
    (recette_denree_id,public_cible_id,quantite_individuelle)
SELECT rd.id,p.id,CASE WHEN p.code='ADULTE' THEN l.quantite ELSE 0 END
FROM _ligne_recette l
JOIN scout_market.recette r ON r.nom=l.recette
JOIN scout_market.recette_denree rd ON rd.recette_id=r.id AND rd.ordre=l.ordre
JOIN scout_market.public_cible p ON p.actif;

INSERT INTO scout_market.grille_menu (label,date_debut,date_fin,type_distribution,actif)
VALUES ('Stage octobre 2026','2026-10-17','2026-10-24','EN_CAISSE',TRUE)
ON CONFLICT (label) DO UPDATE SET date_debut=EXCLUDED.date_debut,date_fin=EXCLUDED.date_fin,
    type_distribution=EXCLUDED.type_distribution,actif=TRUE,updated_at=CURRENT_TIMESTAMP;

CREATE TEMP TABLE _menu_recette (
    date_menu date, type_repas text, recette text, ordre smallint
) ON COMMIT DROP;
INSERT INTO _menu_recette VALUES
('2026-10-17','DEJEUNER','Bâtonnets de carotte',10),('2026-10-17','DEJEUNER','Taboulé au thon et chips',20),('2026-10-17','DEJEUNER','Mimolette',30),('2026-10-17','DEJEUNER','Baguette',40),('2026-10-17','DEJEUNER','Raisin - 150 g',50),
('2026-10-17','GOUTER','Cookie',10),('2026-10-17','GOUTER','Clémentines',20),
('2026-10-17','DINER','Velouté de potiron, fromage et croûtons',10),('2026-10-17','DINER','Pâtes bolognaises',20),('2026-10-17','DINER','Pomme',30),
('2026-10-18','DEJEUNER','Betterave vinaigrette',10),('2026-10-18','DEJEUNER','Fajitas',20),('2026-10-18','DEJEUNER','Yaourt nature',30),('2026-10-18','DEJEUNER','Poire',40),
('2026-10-18','GOUTER','Marbré',10),('2026-10-18','GOUTER','Banane',20),
('2026-10-18','DINER','Velouté de légumes verts',10),('2026-10-18','DINER','Dahl de lentilles',20),('2026-10-18','DINER','Fromage blanc sucré',30),
('2026-10-19','DEJEUNER','Chèvres chauds',10),('2026-10-19','DEJEUNER','Curry de légumes d’automne',20),('2026-10-19','DEJEUNER','Flamby',30),
('2026-10-19','GOUTER','Brioche',10),('2026-10-19','GOUTER','Chocolat',20),('2026-10-19','GOUTER','Prunes',30),
('2026-10-19','DINER','Velouté de tomate',10),('2026-10-19','DINER','Gnocchi milanaise',20),('2026-10-19','DINER','Edam',30),('2026-10-19','DINER','Clémentines',40),
('2026-10-20','DEJEUNER','Chou rouge aux pommes',10),('2026-10-20','DEJEUNER','Couscous saucisse',20),('2026-10-20','DEJEUNER','Mimolette',30),('2026-10-20','DEJEUNER','Pommes caramélisées',40),
('2026-10-20','GOUTER','Flan',10),('2026-10-20','GOUTER','Raisin - 100 g',20),
('2026-10-20','DINER','Velouté de champignon',10),('2026-10-20','DINER','Gratin de courgettes et ravioles',20),('2026-10-20','DINER','Mousse au chocolat',30),
('2026-10-21','DEJEUNER','Salade mâche, endive et pomme',10),('2026-10-21','DEJEUNER','Steak, haricots verts et pommes de terre',20),('2026-10-21','DEJEUNER','Fromage blanc - crème de marron',30),
('2026-10-21','GOUTER','Brownie',10),('2026-10-21','GOUTER','Poire',20),
('2026-10-21','DINER','Velouté de tomate',10),('2026-10-21','DINER','Risotto champignon-butternut',20),('2026-10-21','DINER','Camembert',30),('2026-10-21','DINER','Compote de pomme',40),
('2026-10-22','DEJEUNER','Cheese naan',10),('2026-10-22','DEJEUNER','Chili con carne',20),('2026-10-22','DEJEUNER','Crumble aux poires',30),
('2026-10-22','GOUTER','Crêpe',10),('2026-10-22','GOUTER','Clémentines',20),
('2026-10-22','DINER','Velouté de 7 légumes, fromage et croûtons',10),('2026-10-22','DINER','Blé, nuggets de pois chiches et fondue de poireaux',20),('2026-10-22','DINER','Crème dessert chocolat',30),
('2026-10-23','DEJEUNER','Houmous carotte',10),('2026-10-23','DEJEUNER','Filet de lieu en papillote',20),('2026-10-23','DEJEUNER','Pain perdu',30),
('2026-10-23','GOUTER','Pain d’épices',10),('2026-10-23','GOUTER','Pomme',20),
('2026-10-23','DINER','Velouté de potiron',10),('2026-10-23','DINER','Croziflette',20),('2026-10-23','DINER','Banane - chocolat',30),
('2026-10-24','DEJEUNER','Sandwich pesto, mâche et brie',10),('2026-10-24','DEJEUNER','Deux pommes',20);

INSERT INTO scout_market.menu (grille_menu_id,type_repas_id,date_menu,nom,actif)
SELECT g.id,tr.id,m.date_menu,tr.libelle,TRUE
FROM (SELECT DISTINCT date_menu,type_repas FROM _menu_recette) m
JOIN scout_market.grille_menu g ON g.label='Stage octobre 2026'
JOIN scout_market.type_repas tr ON tr.code=m.type_repas
ON CONFLICT (grille_menu_id,date_menu,type_repas_id) WHERE special_code IS NULL
DO UPDATE SET nom=EXCLUDED.nom,actif=TRUE,updated_at=CURRENT_TIMESTAMP;

DELETE FROM scout_market.menu_denree md
USING scout_market.menu m, scout_market.grille_menu g
WHERE md.menu_id=m.id AND m.grille_menu_id=g.id AND g.label='Stage octobre 2026';

CREATE TEMP TABLE _instance ON COMMIT DROP AS
SELECT mr.*,m.id AS menu_id,r.id AS recette_id,uuidv7() AS instance_id,r.categorie
FROM _menu_recette mr
JOIN scout_market.grille_menu g ON g.label='Stage octobre 2026'
JOIN scout_market.type_repas tr ON tr.code=mr.type_repas
JOIN scout_market.menu m ON m.grille_menu_id=g.id AND m.type_repas_id=tr.id AND m.date_menu=mr.date_menu
JOIN scout_market.recette r ON r.nom=mr.recette;

INSERT INTO scout_market.menu_denree
    (menu_id,denree_id,conditionnement_id,regime,recette_id,recette_instance_id,categorie,ordre)
SELECT i.menu_id,rd.denree_id,rd.conditionnement_id,rd.regime,i.recette_id,i.instance_id,
       CASE WHEN i.categorie IN ('ENTREE','PLAT','FROMAGE','DESSERT') THEN i.categorie ELSE NULL END,
       (i.ordre*100+rd.ordre)::smallint
FROM _instance i JOIN scout_market.recette_denree rd ON rd.recette_id=i.recette_id;

INSERT INTO scout_market.menu_denree_quantite
    (menu_denree_id,public_cible_id,quantite_individuelle)
SELECT md.id,rq.public_cible_id,rq.quantite_individuelle
FROM _instance i
JOIN scout_market.menu_denree md ON md.menu_id=i.menu_id AND md.recette_instance_id=i.instance_id
JOIN scout_market.recette_denree rd ON rd.recette_id=i.recette_id
    AND md.denree_id=rd.denree_id AND md.ordre=(i.ordre*100+rd.ordre)::smallint
JOIN scout_market.recette_denree_quantite rq ON rq.recette_denree_id=rd.id;

DO $$
DECLARE
    recettes_attendues integer;
    lignes_attendues integer;
    menus_attendus integer;
BEGIN
    SELECT count(*) INTO recettes_attendues FROM _recette;
    IF (SELECT count(*) FROM scout_market.recette r JOIN _recette x ON x.nom=r.nom WHERE r.actif) <> recettes_attendues THEN
        RAISE EXCEPTION 'Contrôle final des recettes en échec.';
    END IF;
    SELECT count(*) INTO lignes_attendues FROM _ligne_recette;
    IF (SELECT count(*) FROM scout_market.recette_denree rd JOIN scout_market.recette r ON r.id=rd.recette_id JOIN _recette x ON x.nom=r.nom) <> lignes_attendues THEN
        RAISE EXCEPTION 'Contrôle final des compositions en échec.';
    END IF;
    SELECT count(*) INTO menus_attendus FROM (SELECT DISTINCT date_menu,type_repas FROM _menu_recette) x;
    IF (SELECT count(*) FROM scout_market.menu m JOIN scout_market.grille_menu g ON g.id=m.grille_menu_id WHERE g.label='Stage octobre 2026' AND m.special_code IS NULL) <> menus_attendus THEN
        RAISE EXCEPTION 'Contrôle final de la grille en échec.';
    END IF;
END
$$;

SELECT 'grille' AS objet,g.label,g.date_debut::text || ' → ' || g.date_fin::text AS detail
FROM scout_market.grille_menu g WHERE g.label='Stage octobre 2026'
UNION ALL
SELECT 'recettes',count(*)::text,'actives' FROM scout_market.recette r JOIN _recette x ON x.nom=r.nom WHERE r.actif
UNION ALL
SELECT 'menus',count(*)::text,'repas datés' FROM scout_market.menu m JOIN scout_market.grille_menu g ON g.id=m.grille_menu_id WHERE g.label='Stage octobre 2026' AND m.special_code IS NULL;
