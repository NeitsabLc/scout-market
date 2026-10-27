# Configuration GitHub

GitHub est la forge de référence de Scout Market :

- dépôt : <https://github.com/NeitsabLc/scout-market> ;
- branche stable : `main` ;
- registre d’images : GitHub Container Registry (`ghcr.io`) ;
- intégration et livraison continues : GitHub Actions ;
- mises à jour de dépendances : Dependabot ;
- préparation des versions : Release Please.

Le remote de travail doit être `origin` et pointer vers GitHub.

Le dépôt GitLab des Scouts et Guides de France
(`sgdf/projets-benevoles/scout-market`) est un miroir alimenté par le workflow
`miroir-gitlab.yaml` : `main` et les tags `v*` y sont recopiés à chaque push.
Aucune branche, aucun tag ni aucune release n’y est créé à la main.

## Protection de `main`

Configurer une règle de protection ou un ruleset qui :

- impose une pull request mais conserve zéro approbation obligatoire tant que le dépôt repose sur un mainteneur unique ; le mainteneur relit le diff final après le dernier changement et avant la fusion ;
- exige la résolution des conversations ;
- exige les contrôles GitHub Actions « Qualité et tests » et
  « Configuration de production » ;
- interdit les push directs, les force-push et la suppression de `main` ;
- autorise le squash et utilise le titre de la pull request comme message du
  commit fusionné.

Les titres de pull request doivent suivre Conventional Commits. Cette convention
alimente directement Release Please et détermine le prochain numéro de version.

## Secrets et permissions Actions

Les secrets sont configurés dans **Settings > Secrets and variables > Actions**
et ne sont jamais versionnés.

| Secret | Usage | Accès minimal attendu |
|---|---|---|
| `RELEASE_PLEASE_TOKEN` | créer ou actualiser la pull request de version, puis créer le tag et la GitHub Release | écriture sur le contenu et les pull requests du dépôt |
| `HOMELAB_DEPLOY_DISPATCH_TOKEN` | envoyer l’événement de déploiement en recette à `NeitsabLc/homelab-deploy` | accès au dépôt cible et permission d’y créer un repository dispatch |
| `GITLAB_MIRROR_TOKEN` | pousser `main` et les tags vers le miroir GitLab SGDF | jeton d’accès au projet GitLab, rôle Maintainer, portée `write_repository`, à renouveler avant expiration |

Le `GITHUB_TOKEN` fourni par Actions suffit pour lire le dépôt, publier ou lire
les paquets GHCR et demander un jeton OIDC pour la signature Sigstore. Les
permissions supplémentaires restent limitées aux jobs qui en ont besoin.

## Workflows de référence

- `.github/workflows/ci.yaml` valide chaque pull request vers `main` ;
- `.github/workflows/production-smoke.yaml` contrôle une installation de
  production jetable ;
- `.github/workflows/release-please.yml` prépare ou publie la prochaine version
  après un push sur `main` ;
- `.github/workflows/publish-images.yaml` construit, signe, teste et promeut les
  cinq images GHCR, puis déclenche le déploiement en recette ;
- `.github/workflows/miroir-gitlab.yaml` recopie `main` et les tags `v*` vers
  le miroir GitLab SGDF ;
- `.github/dependabot.yml` ouvre les pull requests de mises à jour Composer, npm,
  GitHub Actions et Docker.

Les actions tierces sont épinglées par SHA. Toute mise à jour doit conserver ce
niveau d’immuabilité.

## Publication d’une version

1. Fusionner les pull requests applicatives dans `main` avec un titre
   Conventional Commit valide.
2. Vérifier la pull request maintenue par Release Please.
3. Fusionner cette pull request : Release Please crée le tag `vX.Y.Z` et la
   GitHub Release.
4. Le workflow de publication construit les images candidates, génère leur SBOM
   et leur provenance, les signe avec Sigstore, exécute le smoke test sur leurs
   digests puis les promeut sous la version publiée.
5. Le workflow envoie ensuite l’événement de déploiement en recette au dépôt
   `NeitsabLc/homelab-deploy`. Le passage en production reste manuel.

Une version déjà publiée peut être retestée et repromue sans reconstruction en
lançant manuellement le workflow « Publication des images de version » avec son
numéro sans le préfixe `v`.

## GHCR et déploiement

Les images publiées sont :

- `ghcr.io/neitsablc/scout-market-php` ;
- `ghcr.io/neitsablc/scout-market-nginx` ;
- `ghcr.io/neitsablc/scout-market-postgres` ;
- `ghcr.io/neitsablc/scout-market-liquibase` ;
- `ghcr.io/neitsablc/scout-market-backup`.

Les déploiements consomment toujours des références par digest, jamais un tag
mutable. Le fichier `.env.release.example` documente les variables attendues et
`make release-verify` contrôle les digests ainsi que les signatures avant tout
téléchargement.
