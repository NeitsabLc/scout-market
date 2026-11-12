# Infra — Scout Market

> Fonctionnement général de la CI, des versions, des images et de la livraison.
> Dépôt public : machines, adresses, domaines, accès et état déployé sont dans la documentation
> d'exploitation privée (projet Infra Homelab), jamais ici.

## 01 Cycle complet

```text
branche ──PR──▶ CI ──squash──▶ main ──▶ PR Release Please ──fusion──▶ tag vX.Y.Z + GitHub Release
                                                                           │
                     ┌─────────────────────────────────────────────────────┘
                     ▼
     Publication des images de version : 5 images ▶ SBOM + provenance ▶ signature ▶ test par digest ▶ promotion GHCR
                     │
                     ▼ automatique
               RECETTE (dépôt de déploiement privé)
                     │
                     ▼ manuel : workflow de production du dépôt de déploiement + confirmation production-VERSION
               PRODUCTION
```

## 02 Intégration continue (sur chaque PR vers `main`)

| Workflow / job | Contrôle |
|---|---|
| Titre conventionnel | Titre de PR au format Conventional Commits (workflow réutilisable) |
| Qualite rapide | Version applicative, secrets (Trivy), refus des PHP vulnérables, configuration Compose, audit npm |
| Betterleaks | Recherche de secrets dans l'historique |
| Validation Liquibase | Changelogs validés sur une base isolée |
| Tests applicatifs | Audits Composer et ImportMap, mapping Doctrine, assets, PHPStan, style, PHPUnit, Axe, Playwright |
| Image (matrice) | Construction et analyse Trivy de chaque image |
| Qualite et tests | Job de synthèse exigé par la protection de `main` |
| Verification de production (`production-smoke.yaml`) | Superposition Compose de production sur une installation isolée |

- Rapports Trivy publiés dans GitHub Security ; actions épinglées par SHA ; permissions en lecture par défaut.
- Dependabot : Composer, npm, GitHub Actions, images Docker.
- Miroir GitLab SGDF (`miroir-gitlab.yaml`) : chaque push sur `main` et chaque tag `v*` sont recopiés ;
  sur GitLab, `.gitlab-ci.yml` n’exécute que le composant de sécurité PNB (Betterleaks).

## 03 Versions (Release Please)

- Chaque fusion dans `main` lance **Préparer ou publier une version** (`release-please.yml`).
- Release Please ouvre ou actualise une PR de version ; elle met à jour `CHANGELOG.md`, `version.txt` et la version dans `app/config/services.yaml`.
- La fusion de cette PR crée le tag `vX.Y.Z` et la GitHub Release. Ne jamais créer de tag à la main.
- `feat` → mineure, `fix` → corrective, `!` → majeure.

## 04 Images

| Image | Rôle |
|---|---|
| `scout-market-php` | Application Symfony (PHP-FPM) et commandes de maintenance |
| `scout-market-nginx` | Serveur web et assets compilés |
| `scout-market-postgres` | PostgreSQL 18 et rôles limités |
| `scout-market-liquibase` | Migrations du schéma |
| `scout-market-backup` | Sauvegardes chiffrées |

- Publiées dans GHCR avec SBOM et provenance, signées sans clé (Cosign + OIDC GitHub).
- Toujours consommées **par digest** ; vérification : `make release-verify`.
- Une version existante se reteste et se repromeut en relançant **Publication des images de version** à la main.

## 05 Livraison

| Environnement | Déclenchement | Garde-fous |
|---|---|---|
| Recette | **Automatique** après la publication des images d'une release | Manifeste immuable (digests), migrations, contrôle HTTPS |
| Production | **Manuel** : workflow du dépôt de déploiement, version + saisie `production-VERSION` | Environnement GitHub protégé, sauvegarde avant migration (`release-backup-now`), vérification des signatures |

- Le déploiement est exécuté par le dépôt de déploiement privé, sur un runner auto-hébergé.
- Une release publiée ne prouve pas son déploiement : vérifier le manifeste et l'état réel.
- Jamais de données de développement en recette ou en production ; le jeu de recette dédié est chargé par `scripts/charger-jeu-donnees-recette.sh`, sur accord.

## 06 Secrets

- Valeurs locales dans les fichiers d'environnement ignorés par Git ; modèles : `.env.example`, `app/.env.example`.
- CI : `RELEASE_PLEASE_TOKEN` (PR de version, tag, release) et `HOMELAB_DEPLOY_DISPATCH_TOKEN` (déclenchement de la recette) ;
  le `GITHUB_TOKEN` natif publie les images. Détail et droits minimaux : `GITHUB_SETUP.md`.
- Sauvegardes chiffrées avec une clé publique ; la clé privée reste hors des serveurs et des archives.
- Ne documenter que des noms de variables, jamais de valeurs.

## 07 Parcours sur la recette déployée

- `make test-recette` (`npm run test:recette`, dossier `tests/recette/`) rejoue des parcours connectés sur la recette : lecture des pages principales (tableau de bord, denrées, fournisseurs, recettes, menus, unités, stocks, utilisateurs, commande, distribution, profil), ouverture sans enregistrement des formulaires d'édition à sous-collections (denrée, fournisseur, recette, unité, utilisateur, grille de menus), puis création, modification et annulation d'un mouvement de stock, et déconnexion.
- Lancée à la main par `verificateur` en mode recette avant une promotion ; jamais en production, jamais en CI, et hors `test:e2e` / `test:accessibility` (suites distinctes, `testDir` différent, absentes de `scripts/run-playwright-ci.sh` et de la CI).
- Variables obligatoires, sans valeur par défaut : `RECETTE_URL`, `RECETTE_EMAIL`, `RECETTE_MOT_DE_PASSE`. L'URL doit être en HTTPS et son nom d'hôte contenir le segment `recette` (délimité par `.`, `-` ou le bord du nom ; `recettes-…` est refusé) ; la suite refuse aussi de démarrer si `CI` est défini. Ni trace, ni vidéo, ni capture d'écran (elles enregistreraient le mot de passe ou des données de la recette). En cas d'échec, Playwright écrit tout de même `test-results/recette/**/error-context.md` : la suite supprime l'instantané de page entière (`PLAYWRIGHT_NO_COPY_PROMPT`), mais le fichier garde le message d'erreur et l'extrait ARIA de l'élément testé. Ne pas le partager hors de la machine.
- Compte de contrôle : un utilisateur `ROLE_ADMIN` dédié, créé à la main en recette (jamais via les changesets de développement), sans changement de mot de passe imposé à la connexion. Il ne sert qu'à cette suite.
- Son mot de passe reste hors dépôt, dans le trousseau macOS (service `scout-market-recette-controle`) :

```bash
security add-generic-password -a "$RECETTE_EMAIL" -s scout-market-recette-controle -w
RECETTE_MOT_DE_PASSE="$(security find-generic-password -a "$RECETTE_EMAIL" -s scout-market-recette-controle -w)" make test-recette
```

- Prérequis : le jeu de données de recette est chargé (au moins une denrée active avec un conditionnement, un fournisseur, une recette, une unité et une grille de menus) et l'origine de mouvement « Correction manuelle » (code `CORRECTION`) est présente, ce que fournit la migration initiale.
- La suite **écrit puis nettoie** : un mouvement de stock ne se supprime pas, il s'annule. Elle relève les mouvements actifs avant le test, crée une entrée « Correction manuelle » d'une denrée, la modifie, puis l'annule avec un motif portant le repère « Contrôle recette automatique ». Le nettoyage, exécuté même après un échec, n'annule que le mouvement de contrôle créé par le test. Si l'échec survient avant son identification, un nouveau mouvement n'est annulé que si sa page de confirmation indique « Entrée · Correction manuelle » ; tout autre nouveau mouvement reste en place et fait échouer le test. Il vérifie ensuite qu'il ne reste aucun mouvement de contrôle actif. Le mouvement annulé et son historique d'audit restent en recette : c'est attendu. Ne pas utiliser la recette pendant l'exécution (l'entrée de contrôle modifie brièvement le stock de la denrée choisie).
