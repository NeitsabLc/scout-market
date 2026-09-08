--liquibase formatted sql

--changeset scout-market:V001-schema splitStatements:true endDelimiter:;
--comment: Schéma initial autonome de Scout Market

CREATE SCHEMA IF NOT EXISTS scout_market;
COMMENT ON SCHEMA scout_market IS 'Schéma principal de Scout Market';

CREATE TABLE scout_market.unite (
    id UUID NOT NULL DEFAULT uuidv7(), nom VARCHAR(50) NOT NULL, symbole VARCHAR(10) NOT NULL,
    utilisable_conditionnement BOOLEAN NOT NULL DEFAULT TRUE, actif BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_unite PRIMARY KEY (id), CONSTRAINT uq_unite_nom UNIQUE (nom), CONSTRAINT uq_unite_symbole UNIQUE (symbole)
);

CREATE TABLE scout_market.type_repas (
    id UUID NOT NULL DEFAULT uuidv7(), code VARCHAR(50) NOT NULL, libelle VARCHAR(150) NOT NULL, ordre SMALLINT NOT NULL DEFAULT 0,
    actif BOOLEAN NOT NULL DEFAULT TRUE, created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_type_repas PRIMARY KEY (id), CONSTRAINT uq_type_repas_code UNIQUE (code), CONSTRAINT chk_type_repas_ordre CHECK (ordre >= 0)
);

CREATE TABLE scout_market.public_cible (
    id UUID NOT NULL DEFAULT uuidv7(), code VARCHAR(50) NOT NULL, libelle VARCHAR(150) NOT NULL, ordre SMALLINT NOT NULL DEFAULT 0,
    actif BOOLEAN NOT NULL DEFAULT TRUE, created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_public_cible PRIMARY KEY (id), CONSTRAINT uq_public_cible_code UNIQUE (code), CONSTRAINT chk_public_cible_ordre CHECK (ordre >= 0)
);

CREATE TABLE scout_market.type_mouvement (
    id UUID NOT NULL DEFAULT uuidv7(), code VARCHAR(50) NOT NULL, libelle VARCHAR(150) NOT NULL, ordre SMALLINT NOT NULL DEFAULT 0,
    actif BOOLEAN NOT NULL DEFAULT TRUE, created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_type_mouvement PRIMARY KEY (id), CONSTRAINT uq_type_mouvement_code UNIQUE (code), CONSTRAINT chk_type_mouvement_ordre CHECK (ordre >= 0)
);

CREATE TABLE scout_market.origine_mouvement (
    id UUID NOT NULL DEFAULT uuidv7(), code VARCHAR(50) NOT NULL, libelle VARCHAR(150) NOT NULL, ordre SMALLINT NOT NULL DEFAULT 0,
    actif BOOLEAN NOT NULL DEFAULT TRUE, created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_origine_mouvement PRIMARY KEY (id), CONSTRAINT uq_origine_mouvement_code UNIQUE (code), CONSTRAINT chk_origine_mouvement_ordre CHECK (ordre >= 0)
);

CREATE TABLE scout_market.configuration_distribution (
    id UUID NOT NULL DEFAULT uuidv7(), distribution_publique_active BOOLEAN NOT NULL DEFAULT TRUE,
    distribuer_gouter_dejeuner BOOLEAN NOT NULL DEFAULT FALSE, jeton_distribution_publique UUID NOT NULL DEFAULT uuidv7(),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_configuration_distribution PRIMARY KEY (id), CONSTRAINT uq_configuration_distribution_jeton UNIQUE (jeton_distribution_publique)
);

CREATE TABLE scout_market.grille_menu (
    id UUID NOT NULL DEFAULT uuidv7(), label VARCHAR(150) NOT NULL, date_debut DATE NOT NULL, date_fin DATE NOT NULL,
    actif BOOLEAN NOT NULL DEFAULT TRUE, created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_grille_menu PRIMARY KEY (id), CONSTRAINT uq_grille_menu_label UNIQUE (label), CONSTRAINT chk_grille_menu_dates CHECK (date_fin >= date_debut)
);
CREATE INDEX idx_grille_menu_dates ON scout_market.grille_menu(date_debut, date_fin);

CREATE TABLE scout_market.fournisseur (
    id UUID NOT NULL DEFAULT uuidv7(), nom VARCHAR(150) NOT NULL, telephone VARCHAR(30), email VARCHAR(150), adresse TEXT,
    actif BOOLEAN NOT NULL DEFAULT TRUE, created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_fournisseur PRIMARY KEY (id), CONSTRAINT uq_fournisseur_nom UNIQUE (nom)
);

CREATE TABLE scout_market.denree (
    id UUID NOT NULL DEFAULT uuidv7(), nom VARCHAR(150) NOT NULL, type VARCHAR(30) NOT NULL, unite_reference_id UUID NOT NULL, unite_inventaire_id UUID NOT NULL,
    actif BOOLEAN NOT NULL DEFAULT TRUE, created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_denree PRIMARY KEY (id), CONSTRAINT uq_denree_nom UNIQUE (nom),
    CONSTRAINT chk_denree_type CHECK (type IN ('SEC','FRUITS_LEGUMES','FRAIS')),
    CONSTRAINT fk_denree_unite_reference FOREIGN KEY (unite_reference_id) REFERENCES scout_market.unite(id) ON DELETE RESTRICT,
    CONSTRAINT fk_denree_unite_inventaire FOREIGN KEY (unite_inventaire_id) REFERENCES scout_market.unite(id) ON DELETE RESTRICT
);
CREATE INDEX idx_denree_unite_reference ON scout_market.denree(unite_reference_id);
CREATE INDEX idx_denree_unite_inventaire ON scout_market.denree(unite_inventaire_id);

CREATE TABLE scout_market.denree_fournisseur (
    id UUID NOT NULL DEFAULT uuidv7(), fournisseur_id UUID NOT NULL, denree_id UUID NOT NULL, reference VARCHAR(100), principal BOOLEAN NOT NULL DEFAULT FALSE,
    actif BOOLEAN NOT NULL DEFAULT TRUE, created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_denree_fournisseur PRIMARY KEY (id), CONSTRAINT uq_denree_fournisseur UNIQUE (fournisseur_id, reference),
    CONSTRAINT fk_denree_fournisseur_fournisseur FOREIGN KEY (fournisseur_id) REFERENCES scout_market.fournisseur(id) ON DELETE RESTRICT,
    CONSTRAINT fk_denree_fournisseur_denree FOREIGN KEY (denree_id) REFERENCES scout_market.denree(id) ON DELETE RESTRICT
);
CREATE INDEX idx_denree_fournisseur_fournisseur ON scout_market.denree_fournisseur(fournisseur_id);
CREATE INDEX idx_denree_fournisseur_denree ON scout_market.denree_fournisseur(denree_id);
CREATE UNIQUE INDEX uq_denree_fournisseur_principal ON scout_market.denree_fournisseur(denree_id) WHERE principal AND actif;

CREATE TABLE scout_market.denree_fournisseur_conditionnement (
    id UUID NOT NULL DEFAULT uuidv7(), reference_fournisseur_id UUID NOT NULL, ordre SMALLINT NOT NULL, libelle VARCHAR(50) NOT NULL,
    quantite_contenu NUMERIC(12,3) NOT NULL, libelle_contenu VARCHAR(50), unite_contenu_id UUID, conditionnement_id UUID NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_denree_fournisseur_conditionnement PRIMARY KEY (id), CONSTRAINT uq_denree_fournisseur_conditionnement UNIQUE (reference_fournisseur_id, ordre),
    CONSTRAINT chk_conditionnement_ordre CHECK (ordre > 0), CONSTRAINT chk_conditionnement_quantite CHECK (quantite_contenu > 0),
    CONSTRAINT chk_conditionnement_contenu CHECK ((libelle_contenu IS NULL) <> (unite_contenu_id IS NULL)),
    CONSTRAINT fk_conditionnement_reference FOREIGN KEY (reference_fournisseur_id) REFERENCES scout_market.denree_fournisseur(id) ON DELETE CASCADE,
    CONSTRAINT fk_conditionnement_type FOREIGN KEY (conditionnement_id) REFERENCES scout_market.unite(id) ON DELETE RESTRICT,
    CONSTRAINT fk_conditionnement_unite_contenu FOREIGN KEY (unite_contenu_id) REFERENCES scout_market.unite(id) ON DELETE RESTRICT
);
CREATE INDEX idx_conditionnement_reference ON scout_market.denree_fournisseur_conditionnement(reference_fournisseur_id);

CREATE TABLE scout_market.recette (
    id UUID NOT NULL DEFAULT uuidv7(), nom VARCHAR(150) NOT NULL, categorie VARCHAR(20) NOT NULL DEFAULT 'PLAT', actif BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_recette PRIMARY KEY (id), CONSTRAINT uq_recette_nom UNIQUE (nom),
    CONSTRAINT chk_recette_categorie CHECK (categorie IN ('PETIT_DEJEUNER','ENTREE','PLAT','FROMAGE','DESSERT','GOUTER'))
);

CREATE TABLE scout_market.recette_denree (
    id UUID NOT NULL DEFAULT uuidv7(), recette_id UUID NOT NULL, denree_id UUID NOT NULL, conditionnement_id UUID NOT NULL,
    regime VARCHAR(20), ordre SMALLINT NOT NULL DEFAULT 0, created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_recette_denree PRIMARY KEY (id), CONSTRAINT chk_recette_denree_ordre CHECK (ordre >= 0),
    CONSTRAINT chk_recette_denree_regime CHECK (regime IS NULL OR regime IN ('VEGETARIEN','SANS_LACTOSE','SANS_GLUTEN')),
    CONSTRAINT fk_recette_denree_recette FOREIGN KEY (recette_id) REFERENCES scout_market.recette(id) ON DELETE CASCADE,
    CONSTRAINT fk_recette_denree_denree FOREIGN KEY (denree_id) REFERENCES scout_market.denree(id) ON DELETE RESTRICT,
    CONSTRAINT fk_recette_denree_conditionnement FOREIGN KEY (conditionnement_id) REFERENCES scout_market.unite(id) ON DELETE RESTRICT
);

CREATE TABLE scout_market.recette_denree_quantite (
    id UUID NOT NULL DEFAULT uuidv7(), recette_denree_id UUID NOT NULL, public_cible_id UUID NOT NULL, quantite_individuelle NUMERIC(12,3) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_recette_denree_quantite PRIMARY KEY (id), CONSTRAINT uq_recette_denree_quantite UNIQUE (recette_denree_id, public_cible_id),
    CONSTRAINT chk_recette_denree_quantite CHECK (quantite_individuelle >= 0),
    CONSTRAINT fk_recette_quantite_ligne FOREIGN KEY (recette_denree_id) REFERENCES scout_market.recette_denree(id) ON DELETE CASCADE,
    CONSTRAINT fk_recette_quantite_public FOREIGN KEY (public_cible_id) REFERENCES scout_market.public_cible(id) ON DELETE RESTRICT
);

CREATE TABLE scout_market.menu (
    id UUID NOT NULL DEFAULT uuidv7(), grille_menu_id UUID NOT NULL, type_repas_id UUID, date_menu DATE, special_code VARCHAR(20), nom VARCHAR(150),
    type_distribution VARCHAR(30) NOT NULL DEFAULT 'SCOUT_MARKET',
    actif BOOLEAN NOT NULL DEFAULT TRUE, created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_menu PRIMARY KEY (id), CONSTRAINT chk_menu_type_distribution CHECK (type_distribution IN ('SCOUT_MARKET','EN_CAISSE')), CONSTRAINT chk_menu_identite CHECK (
        (special_code IS NULL AND date_menu IS NOT NULL AND type_repas_id IS NOT NULL)
        OR (special_code IN ('EXPLO','PIQUE_NIQUE_1','PIQUE_NIQUE_2') AND date_menu IS NULL AND type_repas_id IS NULL)
    ), CONSTRAINT fk_menu_grille FOREIGN KEY (grille_menu_id) REFERENCES scout_market.grille_menu(id) ON DELETE CASCADE,
    CONSTRAINT fk_menu_type_repas FOREIGN KEY (type_repas_id) REFERENCES scout_market.type_repas(id) ON DELETE RESTRICT
);
CREATE UNIQUE INDEX uq_menu_date_type ON scout_market.menu(grille_menu_id, date_menu, type_repas_id) WHERE special_code IS NULL;
CREATE UNIQUE INDEX uq_menu_special ON scout_market.menu(grille_menu_id, special_code) WHERE special_code IS NOT NULL;
CREATE INDEX idx_menu_date ON scout_market.menu(date_menu);

CREATE TABLE scout_market.menu_denree (
    id UUID NOT NULL DEFAULT uuidv7(), menu_id UUID NOT NULL, denree_id UUID NOT NULL, conditionnement_id UUID NOT NULL,
    regime VARCHAR(20), recette_id UUID, recette_instance_id UUID, categorie VARCHAR(20), ordre SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_menu_denree PRIMARY KEY (id), CONSTRAINT chk_menu_denree_ordre CHECK (ordre >= 0),
    CONSTRAINT chk_menu_denree_categorie CHECK (categorie IS NULL OR categorie IN ('ENTREE','PLAT','FROMAGE','DESSERT')),
    CONSTRAINT chk_menu_denree_regime CHECK (regime IS NULL OR regime IN ('VEGETARIEN','SANS_LACTOSE','SANS_GLUTEN')),
    CONSTRAINT chk_menu_denree_recette CHECK ((recette_id IS NULL) = (recette_instance_id IS NULL)),
    CONSTRAINT fk_menu_denree_menu FOREIGN KEY (menu_id) REFERENCES scout_market.menu(id) ON DELETE CASCADE,
    CONSTRAINT fk_menu_denree_denree FOREIGN KEY (denree_id) REFERENCES scout_market.denree(id) ON DELETE RESTRICT,
    CONSTRAINT fk_menu_denree_conditionnement FOREIGN KEY (conditionnement_id) REFERENCES scout_market.unite(id) ON DELETE RESTRICT,
    CONSTRAINT fk_menu_denree_recette FOREIGN KEY (recette_id) REFERENCES scout_market.recette(id) ON DELETE RESTRICT
);

CREATE TABLE scout_market.menu_denree_quantite (
    id UUID NOT NULL DEFAULT uuidv7(), menu_denree_id UUID NOT NULL, public_cible_id UUID NOT NULL, quantite_individuelle NUMERIC(12,3) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_menu_denree_quantite PRIMARY KEY (id), CONSTRAINT uq_menu_denree_quantite UNIQUE (menu_denree_id, public_cible_id),
    CONSTRAINT chk_menu_denree_quantite CHECK (quantite_individuelle >= 0),
    CONSTRAINT fk_menu_quantite_ligne FOREIGN KEY (menu_denree_id) REFERENCES scout_market.menu_denree(id) ON DELETE CASCADE,
    CONSTRAINT fk_menu_quantite_public FOREIGN KEY (public_cible_id) REFERENCES scout_market.public_cible(id) ON DELETE RESTRICT
);

CREATE TABLE scout_market.groupe (
    id UUID NOT NULL DEFAULT uuidv7(), grille_menu_id UUID, nom VARCHAR(150) NOT NULL, effectif_jeune INT NOT NULL DEFAULT 0,
    effectif_adulte INT NOT NULL DEFAULT 0, nombre_vegetariens INT NOT NULL DEFAULT 0, nombre_sans_lactose INT NOT NULL DEFAULT 0,
    nombre_sans_gluten INT NOT NULL DEFAULT 0, type VARCHAR(30) NOT NULL, date_debut_presence DATE NOT NULL, date_fin_presence DATE NOT NULL,
    actif BOOLEAN NOT NULL DEFAULT TRUE, created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_groupe PRIMARY KEY (id), CONSTRAINT uq_groupe_nom UNIQUE (nom), CONSTRAINT chk_groupe_dates CHECK (date_fin_presence >= date_debut_presence),
    CONSTRAINT chk_groupe_effectifs CHECK (effectif_jeune >= 0 AND effectif_adulte >= 0 AND nombre_vegetariens >= 0 AND nombre_sans_lactose >= 0 AND nombre_sans_gluten >= 0),
    CONSTRAINT fk_groupe_grille FOREIGN KEY (grille_menu_id) REFERENCES scout_market.grille_menu(id) ON DELETE SET NULL
);

CREATE TABLE scout_market.utilisateur (
    id UUID NOT NULL DEFAULT uuidv7(), groupe_id UUID, email VARCHAR(180) NOT NULL, mot_de_passe VARCHAR(255) NOT NULL,
    prenom VARCHAR(100) NOT NULL, nom VARCHAR(100) NOT NULL, roles JSONB NOT NULL, actif BOOLEAN NOT NULL DEFAULT TRUE,
    desactive_at TIMESTAMPTZ, changement_mot_de_passe_requis BOOLEAN NOT NULL DEFAULT FALSE,
    jeton_reinitialisation VARCHAR(64), expiration_jeton_reinitialisation TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_utilisateur PRIMARY KEY (id), CONSTRAINT uq_utilisateur_email UNIQUE (email),
    CONSTRAINT fk_utilisateur_groupe FOREIGN KEY (groupe_id) REFERENCES scout_market.groupe(id) ON DELETE RESTRICT
);
CREATE INDEX idx_utilisateur_groupe ON scout_market.utilisateur(groupe_id);
CREATE INDEX idx_utilisateur_jeton ON scout_market.utilisateur(jeton_reinitialisation);

CREATE TABLE scout_market.groupe_repas (
    id UUID NOT NULL DEFAULT uuidv7(), groupe_id UUID NOT NULL, menu_id UUID NOT NULL, mode VARCHAR(20) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_groupe_repas PRIMARY KEY (id), CONSTRAINT uq_groupe_repas UNIQUE (groupe_id, menu_id),
    CONSTRAINT chk_groupe_repas_mode CHECK (mode IN ('EXPLO','PIQUE_NIQUE_1','PIQUE_NIQUE_2','NON_PRIS')),
    CONSTRAINT fk_groupe_repas_groupe FOREIGN KEY (groupe_id) REFERENCES scout_market.groupe(id) ON DELETE CASCADE,
    CONSTRAINT fk_groupe_repas_menu FOREIGN KEY (menu_id) REFERENCES scout_market.menu(id) ON DELETE CASCADE
);

CREATE TABLE scout_market.mouvement_stock (
    id UUID NOT NULL DEFAULT uuidv7(), utilisateur_id UUID NOT NULL, groupe_id UUID, menu_id UUID, type_mouvement_id UUID NOT NULL,
    origine_mouvement_id UUID NOT NULL, date_mouvement TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, cle_soumission UUID,
    annule_at TIMESTAMPTZ, annule_par_id UUID, motif_annulation TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_mouvement_stock PRIMARY KEY (id), CONSTRAINT uq_mouvement_stock_cle UNIQUE (cle_soumission),
    CONSTRAINT chk_mouvement_annulation CHECK ((annule_at IS NULL AND annule_par_id IS NULL AND motif_annulation IS NULL) OR (annule_at IS NOT NULL AND motif_annulation IS NOT NULL AND btrim(motif_annulation) <> '')),
    CONSTRAINT fk_mouvement_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES scout_market.utilisateur(id) ON DELETE RESTRICT,
    CONSTRAINT fk_mouvement_groupe FOREIGN KEY (groupe_id) REFERENCES scout_market.groupe(id) ON DELETE RESTRICT,
    CONSTRAINT fk_mouvement_menu FOREIGN KEY (menu_id) REFERENCES scout_market.menu(id) ON DELETE RESTRICT,
    CONSTRAINT fk_mouvement_annule_par FOREIGN KEY (annule_par_id) REFERENCES scout_market.utilisateur(id) ON DELETE SET NULL,
    CONSTRAINT fk_mouvement_type FOREIGN KEY (type_mouvement_id) REFERENCES scout_market.type_mouvement(id) ON DELETE RESTRICT,
    CONSTRAINT fk_mouvement_origine FOREIGN KEY (origine_mouvement_id) REFERENCES scout_market.origine_mouvement(id) ON DELETE RESTRICT
);
CREATE INDEX idx_mouvement_date ON scout_market.mouvement_stock(date_mouvement);
CREATE INDEX idx_mouvement_annule ON scout_market.mouvement_stock(annule_at);

CREATE TABLE scout_market.mouvement_stock_ligne (
    id UUID NOT NULL DEFAULT uuidv7(), mouvement_stock_id UUID NOT NULL, denree_id UUID NOT NULL, reference_fournisseur_id UUID,
    conditionnement_saisie_id UUID, quantite_saisie NUMERIC(12,3), numero_lot VARCHAR(100),
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_mouvement_stock_ligne PRIMARY KEY (id), CONSTRAINT uq_mouvement_ligne_denree UNIQUE (mouvement_stock_id, denree_id),
    CONSTRAINT chk_mouvement_ligne_quantite CHECK (quantite_saisie IS NULL OR quantite_saisie > 0),
    CONSTRAINT chk_mouvement_ligne_stockage CHECK ((reference_fournisseur_id IS NULL AND conditionnement_saisie_id IS NOT NULL AND quantite_saisie IS NOT NULL) OR (reference_fournisseur_id IS NOT NULL AND conditionnement_saisie_id IS NULL AND quantite_saisie IS NULL)),
    CONSTRAINT fk_mouvement_ligne_mouvement FOREIGN KEY (mouvement_stock_id) REFERENCES scout_market.mouvement_stock(id) ON DELETE CASCADE,
    CONSTRAINT fk_mouvement_ligne_denree FOREIGN KEY (denree_id) REFERENCES scout_market.denree(id) ON DELETE RESTRICT,
    CONSTRAINT fk_mouvement_ligne_reference FOREIGN KEY (reference_fournisseur_id) REFERENCES scout_market.denree_fournisseur(id) ON DELETE RESTRICT,
    CONSTRAINT fk_mouvement_ligne_conditionnement FOREIGN KEY (conditionnement_saisie_id) REFERENCES scout_market.unite(id) ON DELETE RESTRICT
);
COMMENT ON COLUMN scout_market.mouvement_stock_ligne.numero_lot IS 'Numéro de lot relevé lors de l’entrée en stock';

CREATE TABLE scout_market.mouvement_stock_ligne_conditionnement (
    id UUID NOT NULL DEFAULT uuidv7(), mouvement_stock_ligne_id UUID NOT NULL, conditionnement_id UUID NOT NULL, quantite NUMERIC(12,3) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_mouvement_ligne_conditionnement PRIMARY KEY (id), CONSTRAINT uq_mouvement_ligne_conditionnement UNIQUE (mouvement_stock_ligne_id, conditionnement_id),
    CONSTRAINT chk_mouvement_ligne_conditionnement_quantite CHECK (quantite > 0),
    CONSTRAINT fk_detail_ligne FOREIGN KEY (mouvement_stock_ligne_id) REFERENCES scout_market.mouvement_stock_ligne(id) ON DELETE CASCADE,
    CONSTRAINT fk_detail_conditionnement FOREIGN KEY (conditionnement_id) REFERENCES scout_market.denree_fournisseur_conditionnement(id) ON DELETE RESTRICT
);

CREATE TABLE scout_market.audit_mouvement_stock (
    id UUID NOT NULL DEFAULT uuidv7(), mouvement_stock_id UUID NOT NULL, utilisateur_id UUID,
    utilisateur_libelle VARCHAR(320) NOT NULL, action VARCHAR(20) NOT NULL, motif TEXT NOT NULL,
    etat_avant JSONB NOT NULL, etat_apres JSONB NOT NULL, created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT pk_audit_mouvement_stock PRIMARY KEY (id), CONSTRAINT chk_audit_action CHECK (action IN ('MODIFICATION','ANNULATION')),
    CONSTRAINT chk_audit_motif CHECK (btrim(motif) <> ''), CONSTRAINT fk_audit_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES scout_market.utilisateur(id) ON DELETE SET NULL
);
CREATE INDEX idx_audit_mouvement_stock ON scout_market.audit_mouvement_stock(mouvement_stock_id, created_at DESC);

INSERT INTO scout_market.configuration_distribution DEFAULT VALUES;
INSERT INTO scout_market.type_repas (code, libelle, ordre) VALUES ('PETIT_DEJEUNER','Petit-déjeuner',10),('DEJEUNER','Déjeuner',20),('GOUTER','Goûter',30),('DINER','Dîner',40);
INSERT INTO scout_market.public_cible (code, libelle, ordre) VALUES ('FARFADETS','Farfadets',10),('LOUVETEAUX_JEANNETTES','Louveteaux-Jeannettes',20),('SCOUTS_GUIDES','Scouts-Guides',30),('PIONNIERS_CARAVELLES','Pionniers-Caravelles',40),('ADULTE','Adultes',100);
INSERT INTO scout_market.type_mouvement (code, libelle, ordre) VALUES ('ENTREE','Entrée',10),('SORTIE','Sortie',20);
INSERT INTO scout_market.origine_mouvement (code, libelle, ordre) VALUES ('FOURNISSEUR','Livraison fournisseur',10),('DISTRIBUTION','Distribution',20),('INVENTAIRE','Inventaire',30),('POUBELLE','Mise au rebut',40),('RETOUR_ALIMENTAIRE','Retour alimentaire',50),('DONATION','Donation',60),('CORRECTION','Correction manuelle',70);
INSERT INTO scout_market.unite (nom, symbole) VALUES ('kilogramme','kg'),('gramme','g'),('litre','L'),('millilitre','mL'),('pièce','pc'),('carton','carton'),('boîte','boîte'),('sachet','sachet'),('bouteille','bouteille'),('brique','brique'),('pot','pot'),('barquette','barquette'),('paquet','paquet');

-- Compte technique utilisé exclusivement pour attribuer les distributions publiques.
INSERT INTO scout_market.utilisateur (email, mot_de_passe, prenom, nom, roles, actif)
VALUES ('saisie-consommation@scout-market.local', '$2y$13$c7t8yYrYN.Uc2XT9rs9cReNewx29NZ8Hl0XeG/p1kozsGbIz9tHl2', 'Saisie', 'Distribution', '["ROLE_TECHNIQUE"]'::JSONB, TRUE);

--rollback DROP SCHEMA scout_market CASCADE;

--changeset scout-market:V001-dev context:dev splitStatements:true endDelimiter:;
--comment: Compte administrateur réservé aux environnements locaux et tests (mot de passe: ScoutMarket?2026!)
INSERT INTO scout_market.utilisateur (email, mot_de_passe, prenom, nom, roles, actif, changement_mot_de_passe_requis)
VALUES ('admin@scout-market.local', '$2y$13$c7t8yYrYN.Uc2XT9rs9cReNewx29NZ8Hl0XeG/p1kozsGbIz9tHl2', 'Admin', 'Scout Market', '["ROLE_ADMIN"]'::JSONB, TRUE, FALSE);
