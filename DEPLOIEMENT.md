# Déploiement — Scout Market

Ce document donne les bases pour installer Scout Market sur un serveur, à partir
des images publiées ou en construisant les images localement. Les détails propres
à une installation donnée (machines, domaines, accès) ne sont pas versionnés ici.

## 01 Architecture déployée

| Service | Image | Rôle |
|---|---|---|
| `nginx` | `scout-market-nginx` | Serveur web, assets compilés, seul port exposé |
| `php` | `scout-market-php` | Application Symfony (PHP-FPM) |
| `database` | `scout-market-postgres` | PostgreSQL 18 avec rôles limités |
| `liquibase` | `scout-market-liquibase` | Migrations du schéma (lancé à la demande) |
| `maintenance` | `scout-market-php` | Purge des données expirées (à planifier) |
| `backup` | `scout-market-backup` | Sauvegarde chiffrée de la base (à planifier) |

Prérequis du serveur : Docker avec Compose v2, GNU Make, un reverse proxy HTTPS
devant `nginx` et un serveur SMTP pour les invitations et les mots de passe.

## 02 Configuration

Les valeurs ne sont jamais versionnées. Partir des modèles :

| Modèle | Usage |
|---|---|
| `.env.simple-prod.example` → `.env` | Variables de l’application et de PostgreSQL |
| `.env.release.example` → `.env.release` | Références des images publiées (par digest) |

Points d’attention :

- `APP_SECRET` : générer avec `openssl rand -hex 32` ;
- `DEFAULT_URI` et `APP_PUBLIC_URL` : URL publique en HTTPS ;
- `TRUSTED_PROXIES` et `TRUSTED_HOST_PATTERN` : adaptés au reverse proxy ;
- `POSTGRES_*` : un mot de passe distinct par rôle (amorçage, administration, application,
  migrateur, sauvegarde, contrôle de santé) ;
- `BACKUP_AGE_RECIPIENT` : clé **publique** `age` ; la clé privée reste hors du serveur.

## 03 Installation par images publiées (recommandé)

Les images sont publiées dans GHCR à chaque version, signées et consommées par digest.

```bash
make release-config        # valide la configuration Compose
make release-verify        # vérifie digests et signatures Sigstore
make release-pull          # télécharge les images vérifiées
make release-db-update     # applique les migrations Liquibase
make release-up            # démarre database, php et nginx
make release-ps            # contrôle l'état des conteneurs
```

Pour une base neuve, la première migration utilise le rôle d’amorçage
(`make prod-db-bootstrap`), puis `make prod-db-roles-prepare` crée les rôles limités.

## 04 Installation en construisant les images

```bash
make prod-config
make prod-build
make prod-db-update
make prod-up
make prod-ps
```

`make production-smoke` rejoue ce parcours sur une installation jetable.

## 05 Mise à jour

1. Sauvegarder : `make release-backup-now`.
2. Mettre à jour `.env.release` avec les digests de la nouvelle version.
3. `make release-verify`, `make release-db-status`, puis `make release-db-update`.
4. `make release-up`.

Un changeset Liquibase appliqué n’est jamais modifié ; un retour arrière passe par
la restauration de la sauvegarde.

## 06 Tâches planifiées

| Tâche | Commande | Fréquence conseillée |
|---|---|---|
| Purge et anonymisation (voir [confidentialite.md](docs/context/confidentialite.md)) | `make release-maintenance-now` | quotidienne |
| Sauvegarde chiffrée | `make release-backup-now` | quotidienne, avant chaque mise à jour |

La rotation des sauvegardes et leur copie hors du serveur relèvent de l’hébergeur.
`make backup-restore-test` vérifie qu’une sauvegarde est restaurable.
