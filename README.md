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
make lint-php
make analyse-statique
make test
make test-accessibility
make test-e2e
make production-smoke
```

`make test` recrée la base PostgreSQL isolée `scout_market_test`, applique les migrations et exécute PHPUnit. Les suites navigateur utilisent Playwright et Axe pour les parcours fonctionnels et l’accessibilité.

GitHub Actions exécute sur chaque pull request vers `main` la validation du titre, de Docker Compose, Composer, Liquibase et Doctrine, puis PHPStan, le style, PHPUnit, la compilation des assets, l’accessibilité, les parcours E2E, la recherche de secrets et l’analyse des vulnérabilités. Un smoke test vérifie également la configuration de production, les rôles PostgreSQL, la sauvegarde-restauration et le durcissement des conteneurs. Les releases publient des images GHCR signées, accompagnées d’un SBOM et d’une provenance.
