# Contexte du projet Scout Market

## Produit et architecture

Scout Market est l’application de gestion quotidienne du Scout Market de
Jambville : catalogue, recettes, menus, unités, stocks, distributions et
commandes.

- application Symfony 8.1 / PHP 8.4 dans `app/` ;
- PostgreSQL 18, avec Liquibase comme unique source de vérité du schéma ;
- rendu Twig, Symfony UX Turbo et AssetMapper ;
- environnements et services pilotés par Docker Compose et le `Makefile` ;
- tests PHP avec PHPUnit et tests navigateur avec Playwright/Axe ;
- images de livraison publiées dans GHCR et déployées par digest.

Un changeset Liquibase déjà appliqué ne doit jamais être modifié. Toute évolution
du schéma crée un nouveau fichier versionné dans `database/changelog/` et est
référencée par `database/changelog/db.changelog-master.yaml`.

## Forge et workflow de référence

Le dépôt canonique est <https://github.com/NeitsabLc/scout-market>. `origin`
doit pointer vers GitHub. GitLab n’est plus la forge active : ne pas y créer de
merge request, de pipeline, de tag ou de release et ne pas réintroduire de
configuration GitLab supprimée de `main`.

Les changements partent de la dernière version de `origin/main`, restent ciblés
et sont proposés par pull request. Les titres de pull request et les commits
suivent Conventional Commits. `main` est protégée et ne reçoit pas de push
direct.

GitHub Actions exécute la CI et le smoke test de production. Release Please
prépare la pull request de version ; sa fusion crée la GitHub Release. Le workflow
de publication construit et signe les images GHCR, vérifie leurs digests puis
déclenche le déploiement en recette. Dependabot remplace Renovate.

Les détails d’administration sont dans `GITHUB_SETUP.md` et les règles de
contribution dans `CONTRIBUTING.md`.

## Repères de développement

- copier `.env.example` vers `.env` et `app/.env.example` vers `app/.env` pour
  l’installation locale ; ne jamais versionner de secret ou de fichier `.env` ;
- utiliser en priorité les cibles du `Makefile` afin de reproduire les commandes
  exécutées en CI ;
- préserver les données de l’utilisateur et les changements non liés déjà
  présents dans l’arbre de travail ;
- placer la logique métier dans les services ou entités adaptés, garder les
  contrôleurs fins et couvrir les régressions au niveau le plus pertinent ;
- conserver les textes d’interface, la documentation et les messages de commit
  en français, sauf lorsqu’une API ou un standard impose une autre langue.

## Vérifications usuelles

Choisir les contrôles proportionnés au changement :

- `make doctrine-validate`, `make style` et `make analyse-statique` pour le code
  PHP ;
- `make db-validate` pour toute évolution Liquibase ;
- `make test` pour les tests unitaires et fonctionnels ;
- `make test-accessibility` et `make test-e2e` pour les parcours d’interface ;
- `make production-smoke` pour les changements de conteneurs, de configuration
  ou de déploiement.

Ne pas lancer `make reset` sans accord explicite : cette cible détruit les
conteneurs, les volumes et la base locale.
