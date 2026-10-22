# Architecture — Scout Market

> Organisation du projet, stack technique, commandes et gestion des données.
> Versions exactes : `app/composer.json`, `composer.lock`, Dockerfiles et fichiers Compose.

## 01 Vue d'ensemble

```text
Navigateur ──HTTPS──▶ Nginx ──FastCGI──▶ PHP-FPM (Symfony)
                                            │
                                            ├──▶ PostgreSQL 18   (schéma : Liquibase)
                                            └──▶ SMTP            (invitations, mots de passe)

Service maintenance : purge des données expirées et des jetons de réinitialisation (tâches planifiées)
Service backup      : sauvegardes chiffrées de la base
```

## 02 Stack

| Couche | Technologie |
|---|---|
| Langage | PHP 8.4 |
| Framework | Symfony 8.1, Doctrine ORM (mapping et requêtes) |
| Interface | Twig, AssetMapper, Symfony UX (Turbo, Stimulus), Dompdf (PDF), export Excel |
| Base de données | PostgreSQL 18 |
| Migrations | Liquibase (seule source du schéma) |
| Tests | PHPUnit, Playwright + Axe |
| Qualité | PHP-CS-Fixer (`make style`), PHPStan (`make analyse-statique`) |
| Exécution | Docker Compose : `php`, `nginx`, `database`, `liquibase`, `maintenance`, `backup` |

## 03 Commandes `make`

| Usage | Commandes |
|---|---|
| Inventaire | `make help` |
| Installation | `make install`, `make build`, `make rebuild`, `make composer-install` |
| Environnement | `make up`, `make down`, `make restart`, `make ps`, `make logs SERVICE=php`, `make shell` |
| Symfony | `make console ARGS="about"`, `make composer ARGS="…"`, `make cache-clear`, `make assets-compile` |
| Base | `make db-validate`, `make db-status`, `make db-sql`, `make db-update`, `make db-update-dev`, `make dev-data`, `make db-history`, `make db-shell` |
| Qualité | `make style`, `make style-fix`, `make analyse-statique`, `make doctrine-validate` |
| Tests | `make test`, `make test-db-reset`, `make test-accessibility`, `make test-e2e`, `make production-smoke` |
| Livraison | `make prod-config`, `make release-config`, `make release-verify`, `make release-db-status` |
| Exploitation | `make backup-now`, `make backup-restore-test`, `make maintenance-now`, `make db-performance-report` |
| Destructif (accord requis) | `make reset`, `make prod-db-roles-finalize` |

## 04 Structure du projet

```text
scout-market/
├─ app/                      application Symfony
│  ├─ src/
│  │  ├─ Controller/         orchestration HTTP, aucune règle métier
│  │  ├─ Service/            règles métier (calculs de commande, stock, distribution, PDF, Excel…)
│  │  ├─ Entity/ Repository/ Enum/   modèle Doctrine et requêtes
│  │  ├─ Security/           contrôle des comptes, CSP
│  │  ├─ Command/            purges, jeu de données de développement
│  │  └─ Twig/ Logging/ Doctrine/
│  ├─ templates/             gabarits par domaine (denree, menu, commande, distribution…) + components/
│  ├─ assets/                CSS, contrôleurs Stimulus, images, polices
│  ├─ config/                configuration Symfony
│  └─ tests/                 PHPUnit
├─ database/changelog/       Liquibase : V001…V006 à la racine + master
├─ docker/                   images php, nginx, postgres, liquibase (+ maintenance.sh)
├─ tests/                    Playwright : accessibility/, e2e/, infrastructure/
├─ scripts/                  CI, smoke test, jeu de données de recette, vérification des images
├─ compose*.yaml             dev, production, livraison par images (release)
└─ Makefile                  point d'entrée des commandes
```

## 05 Configuration importante

- Modèles : `.env.example` (Compose) et `app/.env.example` (Symfony) ; valeurs réelles hors Git.
- Variables clés : `APP_ENV`, `APP_SECRET`, `APP_PUBLIC_URL`, `DEFAULT_URI`, `MAILER_DSN`, `MESSENGER_TRANSPORT_DSN`,
  `POSTGRES_*` (rôles admin, application, migrateur, sauvegarde, healthcheck), `TRUSTED_PROXIES`, `TRUSTED_HOST_PATTERN`,
  `BACKUP_AGE_RECIPIENT` (clé publique de chiffrement des sauvegardes).
- Images : versions et digests épinglés (`*_VERSION`, `*_IMAGE_DIGEST`) ; la livraison utilise des images GHCR par digest.
- Sécurité : `app/config/packages/security.yaml`, CSP dans `Security/ContentSecurityPolicySubscriber.php`.

## 06 Données

- Liquibase possède le schéma ; Doctrine ne génère jamais de migration (`doctrine-validate` utilise `--skip-sync`).
- Changesets `Vxxx__description.sql` dans `database/changelog/`, immuables une fois appliqués ; toute évolution = nouveau fichier.
- Chaque nouveau changeset est inclus explicitement dans `db.changelog-master.yaml` (pas d'`includeAll`).
- Données de développement : `make dev-data` (commande `ChargerJeuDonneesDeveloppementCommand`), jamais hors local.
- Vérifier avec `make db-validate` et `make db-sql` ; sauvegarde restaurable avant toute migration durable.
- Rôles PostgreSQL limités : l'application n'a pas les droits de modifier le schéma.

## 07 Performance et scalabilité

| Sujet | Règle |
|---|---|
| Cache | Pas de cache applicatif aujourd'hui. Si un calcul coûteux est réutilisé (besoins par grille, stock dynamique) : pool Symfony `cache.app`, invalidé à l'écriture. Assets compilés et servis par Nginx. |
| Tâches longues | Hors requête : commande Symfony lancée par le service `maintenance`, ou message Messenger `async`. Aujourd'hui `MESSENGER_TRANSPORT_DSN=sync://` : prévoir un worker avant de passer en asynchrone. |
| Requêtes | Index et observabilité ajoutés en V005 ; `make db-performance-report` liste les requêtes coûteuses. Éviter les N+1. |
| PDF et exports | Générés à la demande (fiches recettes, listes de courses, Excel) ; surveiller le volume avant d'ajouter un export lourd. |
| Modularité | Une fonctionnalité = contrôleur + service + dossier de gabarits dédié. |
