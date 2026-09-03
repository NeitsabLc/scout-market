# Livrer Scout Market avec GitHub et sa CI

Le dépôt de référence est <https://github.com/NeitsabLc/scout-market>. La branche
`dev` sert à la recette et reçoit les évolutions en premier ; `main` représente la
production. Le workflow `.github/workflows/ci.yaml` contrôle les deux branches. Le
smoke test complet de production ne s’exécute que sur `main`. Les images
de production restent construites manuellement sur le serveur tant que la publication
GHCR n’est pas explicitement activée.

## 1. Préparer le dépôt GitHub

Le dépôt doit être vide lors du premier envoi : ne pas ajouter de README, licence ou
`.gitignore` depuis l’interface. Pour une connexion SSH, ajouter la clé publique du
poste au compte GitHub puis vérifier :

```shell
ssh -T git@github.com
```

Dans **Settings > Rules > Rulesets**, protéger les branches :

- bloquer les suppressions et les force-push ;
- exiger une pull request vers `dev`, puis une pull request de `dev` vers `main` ;
- exiger le contrôle `Qualite et tests` avant fusion lorsque l’offre GitHub permet les
  rulesets sur ce dépôt privé ;
- exiger également `Configuration de production` sur `main` lorsque l’offre GitHub le
  permet.

Un second ruleset peut protéger les tags `v*` contre la modification et la suppression.

## 2. Créer l’historique Scout Market neuf

Le dossier de travail provient de Campement, mais Scout Market doit commencer avec un
historique indépendant. Conserver l’ancien `.git` hors du projet avant d’initialiser :

```shell
cd /chemin/vers/scout-market
mv .git ../campement-historique-git
git init -b main
git add .
git status --short
git diff --cached --check
git commit -m "Initialisation de Scout Market"
git remote add origin git@github.com:NeitsabLc/scout-market.git
git push -u origin main
```

Avant le commit, vérifier impérativement que `.env`, les sauvegardes, exports et clés
privées n’apparaissent pas dans `git status`. Le script Campement local
`scripts/supprimer-mouvements-stock-sejour.sh` est volontairement ignoré.

## 3. Publier une version

Mettre à jour `CHANGELOG.md` et `app.version`, vérifier que la CI de `main` est verte,
puis créer un tag annoté depuis `main` :

```shell
git switch main
git status --short
git tag -a v0.1.0 -m "Scout Market 0.1.0"
git push origin main
git push origin v0.1.0
```

Sur GitHub, ouvrir **Releases > Draft a new release**, choisir le tag `v0.1.0`, reprendre
les notes du changelog et publier. La release identifie exactement le code à déployer.
Tant que la publication GHCR n’est pas activée, elle ne contient pas d’image Docker
préconstruite.

## 4. Convention pour la suite

- `dev` reste la branche de recette et reçoit les branches courtes ;
- `main` reste toujours déployable et ne reçoit que les changements validés sur
  `dev` ;
- chaque livraison porte un tag `vMAJEUR.MINEUR.CORRECTIF` immuable ;
- le serveur déploie un tag explicite, jamais une branche flottante ;
- tout secret reste dans le `.env` du serveur ou dans un gestionnaire de secrets.

La procédure serveur est dans
[`exploitation-production.md`](exploitation-production.md).
