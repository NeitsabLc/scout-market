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

## Préparer une version

Release Please maintient une pull request de version sur `main`. Sa fusion met à
jour `CHANGELOG.md`, `version.txt` et `app.version`. Elle ne crée volontairement ni
release GitHub ni tag.

Après validation fonctionnelle de la recette, créer un tag annoté et signé sur le
commit concerné :

```shell
git switch main
git pull --ff-only origin main
version=$(cat version.txt)
git tag -s "v$version" -m "Scout Market $version"
git push origin "v$version"
```

Le workflow de publication refuse un tag non signé, un tag extérieur à `main` ou
une version différente de `version.txt`. Il applique ensuite le tag de version aux
digests candidats, sans reconstruire les images.

## Promotion en production

La production se déclenche manuellement depuis le workflow `Promouvoir Scout Market
en production` de `homelab-deploy`. L’opérateur saisit la version sans `v` et la
confirmation `production-VERSION`.

Le workflow vérifie le tag Git signé, résout les cinq références par digest et exige
qu’elles correspondent exactement au candidat testé en recette. `web01` crée une
sauvegarde PostgreSQL chiffrée avant Liquibase, déploie les images et conserve le
service de sauvegarde planifiée actif.

Les secrets applicatifs, la clé privée `age` et les sauvegardes ne doivent jamais
être versionnés.
