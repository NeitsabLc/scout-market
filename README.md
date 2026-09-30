# Scout Market

[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Symfony 8.1](https://img.shields.io/badge/Symfony-8.1-000000?logo=symfony&logoColor=white)](https://symfony.com/)
[![PostgreSQL 18](https://img.shields.io/badge/PostgreSQL-18-4169E1?logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![CI](https://github.com/NeitsabLc/scout-market/actions/workflows/ci.yaml/badge.svg)](https://github.com/NeitsabLc/scout-market/actions/workflows/ci.yaml)
[![Licence Apache 2.0](https://img.shields.io/badge/Licence-Apache%202.0-D22128?logo=apache&logoColor=white)](LICENSE)

Scout Market est l’application de gestion quotidienne du Scout Market de Jambville. Elle couvre le catalogue, les recettes, les menus, les unités participantes, les stocks, les distributions et les commandes.

## Fonctions principales

- fournisseurs, denrées, conditionnements et recettes ;
- grilles de menus, effectifs et régimes alimentaires ;
- mouvements de stock et distributions par lien ou QR code ;
- calcul des commandes, exports Excel et documents PDF ;
- comptes administrateur, gestionnaire et unité participante.

## Démarrage local

Prérequis : Git, Docker Compose v2 et GNU Make.

```bash
git clone https://github.com/NeitsabLc/scout-market.git
cd scout-market
cp .env.example .env
cp app/.env.example app/.env
```

Renseigner au minimum `APP_SECRET` et `POSTGRES_PASSWORD` dans `.env`, puis lancer :

```bash
make install
```

L’application est alors disponible sur <http://localhost:8080>. `make reset` supprime les conteneurs, les volumes et la base locale.

## Contrôles

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

Liquibase est l’unique source de vérité du schéma. Un changeset déjà appliqué ne doit jamais être modifié.

## Contribution et exploitation

- [CONTRIBUTING.md](CONTRIBUTING.md) décrit le workflow de contribution ;
- [GITHUB_SETUP.md](GITHUB_SETUP.md) décrit la forge, les releases et GHCR ;
- le guide utilisateur PDF est accessible depuis l’interface ;
- les dossiers d’architecture, d’installation et d’exploitation sont maintenus séparément du dépôt.

Le projet est distribué sous licence [Apache 2.0](LICENSE).
