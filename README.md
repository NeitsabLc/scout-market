# Scout Market

[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Symfony 8.1](https://img.shields.io/badge/Symfony-8.1-000000?logo=symfony&logoColor=white)](https://symfony.com/)
[![PostgreSQL 18](https://img.shields.io/badge/PostgreSQL-18-4169E1?logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![Docker Compose](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)](https://docs.docker.com/compose/)
[![CI](https://github.com/NeitsabLc/scout-market/actions/workflows/ci.yaml/badge.svg)](https://github.com/NeitsabLc/scout-market/actions/workflows/ci.yaml)
[![Licence Apache 2.0](https://img.shields.io/badge/Licence-Apache%202.0-D22128?logo=apache&logoColor=white)](LICENSE)

## Description

Scout Market est l’application de gestion quotidienne du Scout Market de Jambville. Elle permet de gérer les menus, les stocks, les distributions et les commandes.

L’application repose sur Symfony, PostgreSQL, Liquibase, Nginx et Docker Compose.

Le dépôt de référence est [NeitsabLc/scout-market sur GitHub](https://github.com/NeitsabLc/scout-market). Les contributions passent par des pull requests ; l’ancien dépôt GitLab n’est plus la forge active. Voir [CONTRIBUTING.md](CONTRIBUTING.md) pour le workflow de contribution et [GITHUB_SETUP.md](GITHUB_SETUP.md) pour la configuration de la forge, des releases et de GHCR.

## Fonctionnalités principales

- catalogue global des fournisseurs, denrées et conditionnements ;
- classification des produits secs, frais, fruits et légumes ;
- création des recettes et des grilles de menus datées ;
- gestion des unités, effectifs, régimes, allergènes et repas particuliers ;
- suivi des mouvements de stock ;
- préparation des distributions par repas ou en caisse par journée ;
- calcul des commandes et génération des listes de courses ;
- gestion des comptes administrateur, gestionnaire et unité participante.

## Environnement local

### Prérequis

- Git ;
- Docker avec Docker Compose v2 ;
- GNU Make.

PHP, Composer, PostgreSQL, Liquibase et Nginx sont fournis par les conteneurs.

### Installation

```bash
git clone https://github.com/NeitsabLc/scout-market.git
cd scout-market
cp .env.example .env
cp app/.env.example app/.env
make install
```

Ne jamais versionner les fichiers `.env`. Avec les valeurs par défaut, l’application est accessible sur <http://localhost:8080>.

Commandes courantes :

```bash
make up
make down
make ps
make logs
```

### Base de données

Liquibase est l’unique source de vérité du schéma PostgreSQL `scout_market`. Les données locales sont chargées séparément des migrations de production.

```bash
make db-validate
make db-status-dev
make db-sql-dev
make db-update-dev
make dev-data
make db-shell
```

`make dev-data` recharge un jeu de démonstration daté. Il ne doit être utilisé qu’en développement ou en test. Un changeset déjà appliqué ne doit jamais être modifié ; toute évolution crée un nouveau changeset versionné. `make reset` détruit les conteneurs, les volumes et la base locale.

En production, `pg_stat_statements` agrège les requêtes normalisées depuis le dernier redémarrage ou la dernière remise à zéro des statistiques. Le rapport ci-dessous utilise exclusivement le rôle d’administration PostgreSQL et n'accorde aucun droit supplémentaire au rôle applicatif :

```bash
make db-performance-report
```

Laisser idéalement la collecte couvrir sept jours représentatifs avant d'interpréter le temps total, le temps moyen, le maximum et l'utilisation des index. Le rapport ne remet jamais les statistiques à zéro et sa sortie, qui décrit les requêtes exécutées, ne doit pas être publiée telle quelle. Une requête candidate à l'optimisation doit ensuite être vérifiée avec `EXPLAIN (ANALYZE, BUFFERS)` sur une copie ou pendant une plage maîtrisée ; `ANALYZE` exécute réellement la requête.

## Déploiement sur un serveur

La procédure générale consiste à :

1. préparer un serveur Linux avec Docker Compose, un nom de domaine, TLS et un stockage persistant pour PostgreSQL et les sauvegardes ;
2. récupérer une version publiée et copier `.env.release.example` vers `.env.release` ;
3. injecter les secrets hors de Git et renseigner les images GHCR par digest ;
4. s’authentifier auprès de GHCR si nécessaire, puis vérifier les signatures et télécharger les images ;
5. réaliser une sauvegarde chiffrée avant toute migration ;
6. contrôler puis appliquer les changesets Liquibase ;
7. démarrer les services, exécuter la maintenance et vérifier l’état, les journaux et le parcours de connexion ;
8. conserver la version précédente et une sauvegarde restaurable pour permettre un retour arrière.

```bash
make release-config
make release-verify
make release-pull
make release-backup-now
make release-db-status
make release-db-update
make release-up
make release-ps
```

Le proxy inverse, les certificats, les secrets, les sauvegardes et la supervision relèvent de la configuration du serveur et ne doivent pas être stockés dans le dépôt.

### Premier déploiement simplifié

Le fichier `.env.simple-prod.example` est réservé à l’amorçage temporaire d’un premier serveur. Il réutilise un même rôle PostgreSQL pour plusieurs usages et laisse la sauvegarde chiffrée désactivée. Il ne constitue donc pas la configuration de production cible.

Pour l’utiliser, le copier vers `.env`, renseigner tous les secrets, limiter ses permissions avec `chmod 600 .env`, puis valider la configuration avec `make prod-config`. Avant d’importer des données réelles, migrer vers les rôles PostgreSQL dédiés décrits dans `.env.example` et configurer une sauvegarde chiffrée restaurable.

## Tests et CI

Les contrôles disponibles localement sont :

```bash
make db-validate
make doctrine-validate
make style
make analyse-statique
make test
make test-accessibility
make test-e2e
make production-smoke
```

`make test` recrée la base PostgreSQL isolée `scout_market_test`, applique les migrations et exécute PHPUnit. Les suites navigateur utilisent Playwright et Axe pour les parcours fonctionnels et l’accessibilité.

GitHub Actions exécute sur chaque pull request vers `main` la validation du titre, de Docker Compose, Composer, Liquibase et Doctrine, puis PHPStan, le style, PHPUnit, la compilation des assets, l’accessibilité, les parcours E2E, la recherche de secrets avec Trivy et Betterleaks, et l’analyse des vulnérabilités. Un smoke test vérifie également la configuration de production, les rôles PostgreSQL, la sauvegarde-restauration et le durcissement des conteneurs.

Release Please maintient automatiquement une pull request de version à partir des titres Conventional Commits. Sa fusion crée le tag et la GitHub Release, puis GitHub Actions construit les cinq images GHCR, produit leur SBOM et leur provenance, les signe avec Sigstore, teste exactement leurs digests et déclenche le déploiement en recette. Une version existante peut être retestée et repromue depuis le workflow de publication des images ; le déploiement en production reste manuel.
