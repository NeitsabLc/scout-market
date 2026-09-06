# Livrer Scout Market avec GitHub et le homelab

Le dépôt applicatif de référence est
<https://github.com/NeitsabLc/scout-market>. Les branches de travail sont intégrées
par pull request dans `main`, qui doit rester déployable.

## Intégration continue

Chaque pull request vers `main` exécute les contrôles de qualité, les tests
fonctionnels et navigateur, les audits de dépendances, les analyses Trivy et un
smoke test complet de la configuration de production.

Les titres suivent Conventional Commits :

- `fix:` déclenche une version corrective ;
- `feat:` déclenche une version mineure ;
- `type!:` ou `BREAKING CHANGE` déclenche une version majeure.

Dependabot utilise `fix(deps):` ou `fix(deps-dev):`, afin que ses mises à jour
produisent également une version corrective.

## Recette automatique

Après chaque fusion dans `main`, GitHub construit les cinq images `php`, `nginx`,
`postgres`, `liquibase` et `backup`. Elles sont publiées dans GHCR sous le tag
immuable `sha-<commit>`, accompagnées d’un SBOM, d’une provenance et d’une signature
Sigstore sans clé.

Les images candidates sont testées par digest. Après validation, le dépôt privé
`NeitsabLc/homelab-deploy` reçoit l’événement `scout-market-candidate-ready` et
déploie exactement ces digests sur `web02`. Les sauvegardes planifiées restent
désactivées en recette.

## Publier une version

Release Please maintient une pull request de version sur `main`. Sa fusion met à
jour `CHANGELOG.md`, `version.txt` et `app.version`, puis crée automatiquement la
GitHub Release et son tag `vX.Y.Z`. La publication de cette release applique la
version aux digests candidats déjà testés, sans reconstruire les images.

Si cet événement n'a pas déclenché la promotion, lancer manuellement le workflow
`Publication des images` depuis `main` et renseigner `release_version` sans le
préfixe `v`. Le workflow vérifie la release publiée, son tag, `version.txt` et le
SHA candidat avant de reprendre uniquement la promotion. Laisser ce champ vide
reconstruit et teste les candidats du commit courant.

## Promotion en production

La production se déclenche manuellement depuis le workflow `Promouvoir Scout Market
en production` de `homelab-deploy`. L’opérateur saisit la version sans `v` et la
confirmation `production-VERSION`.

Le workflow exige une GitHub Release publiée par le propriétaire et son tag créé
par Release Please. Il accepte aussi les anciens tags annotés et signés. Il résout
les cinq références par digest et exige qu’elles correspondent exactement au
candidat testé en recette. `web01` crée une sauvegarde PostgreSQL chiffrée avant
Liquibase, déploie les images et conserve le service de sauvegarde planifiée actif.

Les secrets applicatifs, la clé privée `age` et les sauvegardes ne doivent jamais
être versionnés.
