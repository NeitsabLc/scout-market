# Scout Market — contexte du projet

## Objectif et périmètre

Scout Market est une application Symfony dédiée exclusivement à l’intendance. Elle
fonctionne en permanence : aucune donnée n’est rattachée à un séjour et aucun séjour
actif n’est sélectionné.

La navigation conserve le design de Campement et regroupe les fonctions en quatre
ensembles :

1. **Catalogue** : fournisseurs et denrées ;
2. **Préparation** : recettes et menus ;
3. **Flux** : mouvements de stock, distribution et commande ;
4. **Gestion** : unités participantes et utilisateurs.

Le tableau de bord présente les unités présentes à la date du jour, leurs effectifs,
régimes et allergènes, ainsi que le menu du jour.

## Modèle métier

Les fournisseurs, denrées, recettes, unités de mesure, types de repas, publics cibles,
types et origines de mouvements sont globaux. Une denrée possède un type logistique :
`SEC`, `FRUITS_LEGUMES` ou `FRAIS`.

Une `GrilleMenu` porte un libellé unique, une date de début, une date de fin et un
statut actif. Un `Menu` appartient à une grille, représente un repas daté ou un menu
spécial et porte le mode `SCOUT_MARKET` ou `EN_CAISSE`. Les quantités ciblent un
`PublicCible` et peuvent provenir directement d’une denrée ou d’une recette.

Un `Groupe` représente une unité participante. Il porte sa période de présence, ses
effectifs, régimes et allergènes, sa grille ainsi que ses déclarations de repas explo,
pique-nique ou non pris. Cette période pilote les besoins sans réintroduire la notion
de séjour.

## Distribution et commande

La page **Scout Market** agrège, repas par repas, toutes les denrées des menus utilisant
ce mode. La page **En caisse** contient :

- une section repliable de produits secs à livrer sur l’ensemble de la période ;
- une carte repliable par journée ;
- dans chaque journée, une sous-section par grille puis par unité contenant les fruits,
  légumes et produits frais, sans découpage par repas.

Le mode de distribution ne change jamais les règles explo, pique-nique ou repas non
pris ; il ne fait que choisir la présentation logistique.

Le calcul de commande propose deux ajustements indépendants :

- **Sec en caisse déjà livrée** exclut les produits secs des grilles en caisse des
  besoins et de la déduction de stock ;
- **Frais de la journée déjà livré** exclut tous les repas de la journée du premier
  repas à déduire lors du calcul du stock prévisionnel.

## Données et migrations

Le schéma applicatif est `scout_market`. Le projet contient une seule migration source,
`database/changelog/V001__initialisation_scout_market.sql`, incluant structure,
contraintes, index et référentiels. Les migrations Campement ne sont pas conservées.

Le jeu de développement est chargé par `app:dev:charger-jeu-donnees` à partir de
`app/resources/dev/jeu_donnees_scout_market.sql`. La commande refuse de s’exécuter en
production et recalcule les dates à chaque chargement.

La reprise d’inventaire Campement reste lisible, auditée, rejouable et indépendante de
Liquibase. Voir [`docs/reprise-inventaire-production.md`](docs/reprise-inventaire-production.md).

## Sécurité et rôles

- `ROLE_ADMIN` hérite de `ROLE_GESTIONNAIRE` ;
- `ROLE_ADMIN` gère les comptes utilisateurs ;
- `ROLE_GESTIONNAIRE` administre l’intendance et les unités participantes, sans accès à la gestion des comptes ;
- `ROLE_GROUPE` consulte la grille de son unité ;
- `ROLE_TECHNIQUE` attribue les distributions publiques sans pouvoir se connecter.

La production sépare les rôles PostgreSQL applicatif, migrateur, sauvegarde et contrôle.
Les conteneurs sont non privilégiés, en lecture seule hors volumes temporaires, et les
sauvegardes PostgreSQL sont chiffrées avec `age`.

## Contrôles attendus

Avant chaque livraison : validation Liquibase, mapping Doctrine, style PHP, analyse
PHPStan, PHPUnit sur une base recréée, tests Playwright E2E et accessibilité, validation
Compose de production et smoke test de production avec restauration de sauvegarde.

La CI de qualité s’exécute uniquement sur les pull requests visant `dev` et
publie le statut stable `Qualite et tests`. Une pull request visant `main`
exécute uniquement `production-smoke.yaml` et publie `Configuration de
production`, afin d’éviter les doublons et de toujours créer le statut requis.
Les deux branches doivent exiger leur statut avec une base strictement à jour.

Chaque commit de `main` construit et signe avec Sigstore cinq images candidates
GHCR immuables, accompagnées de leur SBOM et provenance, puis teste ces digests.
Un tag signé `vX.Y.Z` attend la validation candidate du même SHA et promeut les
mêmes digests sans reconstruction. La livraison sur le serveur reste manuelle
via `.env.release`, `compose.release.yaml` et les commandes `make release-*`.
