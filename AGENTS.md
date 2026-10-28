# AGENTS.md — Scout Market

> Consignes pour les agents IA (Claude Code, Codex…) qui travaillent sur ce dépôt.
> Les suivre garantit un code homogène, sûr, respectueux des données et conforme au design system.

## 01 Objectif

Gérer l'intendance permanente de Jambville : catalogue de denrées, fournisseurs, recettes et menus par grille,
unités, stocks, distributions et commandes. Aucune notion de séjour.
Public : équipe d'intendance et responsables d'unité. Règles détaillées : [produit.md](docs/context/produit.md).

## 02 Avant de commencer

- [ ] Lire `git status --short` ; préserver les modifications locales existantes.
- [ ] Lire `docs/context/etat.md` (reprise, fichier local non versionné).
- [ ] Lire les documents utiles à la tâche :

| Besoin | Document |
|---|---|
| Objectif, règles métier, vocabulaire | [produit.md](docs/context/produit.md) |
| Stack, commandes, structure, données, tests | [architecture.md](docs/context/architecture.md) |
| CI, versions, images, recette et production | [infra.md](docs/context/infra.md) |
| Interface : couleurs, typo, icônes, messages | [design-system.md](docs/context/design-system.md) |
| Exigences et contrôles de sécurité | [securite.md](docs/context/securite.md) |
| Données personnelles et conservation | [confidentialite.md](docs/context/confidentialite.md) |
| Choix durables et leçons | [decisions.md](docs/context/decisions.md) |

- [ ] Utiliser le design system pour tout écran ou composant.
- [ ] Créer une branche dédiée depuis `main` à jour : `feature/…`, `fix/…`, `refactor/…`, `test/…`, `docs/…`, `chore/…`.

## 03 Règles générales

- [ ] Fichiers `.env` réels : lecture possible en local uniquement, jamais sur un serveur ; ne jamais recopier une valeur.
- [ ] Réutiliser les composants, services et gabarits existants avant d'en créer.
- [ ] Garder le code modulaire et scalable : une responsabilité par classe, service ou gabarit.
- [ ] Ne pas créer de fichier non nécessaire (pas de doc ou script jetable dans le dépôt).
- [ ] Langues : code, tables, documentation, interface et commits en français (exception notée dans `decisions.md`).
- [ ] Dépôt public : aucun détail d'infrastructure (IP, machines, domaines privés, chemins locaux)
      ni aucune faille de sécurité connue (uniquement dans `etat.md` §04, non versionné).
- [ ] Répondre en français ; ne jamais fusionner sans accord explicite.
- [ ] Commit uniquement quand le travail est terminé et que toutes les questions posées ont reçu une réponse.
- [ ] Push autorisé sans nouvel accord une fois la fonctionnalité et le développement validés, après les contrôles de §08.

## 04 Guidelines de développement

- [ ] Contrôleurs fins, règles métier dans `app/src/Service/` et les entités.
- [ ] Nommage simple et explicite, conforme au glossaire de `produit.md` (`CalculCommande`, `PreparationDistribution`).
- [ ] Composants petits : extraire un service, un gabarit `components/` ou un contrôleur Stimulus dès qu'un bloc grossit.
- [ ] Accessibilité WCAG 2.2 AA / RGAA : libellés, contrastes, navigation clavier, focus visible, rôles ARIA.
- [ ] Confidentialité dès la conception : chaque donnée personnelle a une utilité, une durée et une purge (`confidentialite.md`).
- [ ] Cloisonnement : toute lecture ou écriture vérifie côté serveur que l'objet appartient au périmètre de l'utilisateur
      (un `ROLE_GROUPE` ne voit que son unité).
- [ ] Identifiants exposés en UUID v7 (`EntityIdTrait`), jamais en entier auto-incrémenté.
- [ ] Dates et heures stockées en UTC (`TIMESTAMPTZ`), affichées dans le fuseau `Europe/Paris`.
- [ ] Migrations par étapes réversibles : ajouter, migrer, puis supprimer dans une version ultérieure.
- [ ] Aucune nouvelle dépendance sans vérifier maintenance, licence et CVE ; la justifier dans la PR.
- [ ] Aucun `TODO` dans le code : le travail restant va dans `etat.md`.

## 05 Sécurité

- [ ] Ne jamais exposer de clé, mot de passe ou jeton (code, logs, tests, docs, Git).
- [ ] Toute configuration sensible passe par une variable d'environnement, documentée dans `.env.example`.
- [ ] Valider toutes les entrées utilisateur côté serveur (Validator, jeton CSRF, contrôle d'accès) ;
      le HTML des recettes passe par `DescriptionRecetteSanitizer`.
- [ ] Gérer les erreurs : message clair à l'utilisateur, aucune trace technique exposée, journal sans donnée personnelle.
- [ ] Données réelles interdites dans le dépôt ; données de développement uniquement dans les scripts et changesets prévus.
- [ ] Les contrôles de `securite.md` §04 sont **bloquants** : exécutés avant chaque push quand c'est possible,
      et obligatoirement avant toute mise en production.

## 06 Commandes utiles

```bash
make help                 # inventaire complet
make install              # première installation (après copie des .env.example)
make up / make ps         # démarrer / état
make dev-data             # recharger le jeu local autour de la date du jour
make db-status            # migrations en attente
make test                 # tests PHP (recrée la base de tests)
make test-accessibility   # Axe ; make test-e2e pour les parcours
make style analyse-statique doctrine-validate
```

## 07 Pièges connus

- Ne jamais modifier un changeset Liquibase appliqué ; ne jamais lancer `doctrine:schema:update --force`.
- `make test` recrée la base de tests ; vérifier la base visée avant les tests navigateur.
- `make reset`, suppressions de volumes, purges et restaurations : accord explicite obligatoire.
- Pas de notion de séjour : ne pas réintroduire le contexte multi-séjour de Campement.
- La présence d'une unité et sa grille pilotent les besoins.
- Les modes Scout Market / En caisse changent la logistique, pas les règles de repas (explo, pique-nique, non pris).
- La forge est GitHub ; le GitLab SGDF est un miroir : ne jamais y pousser à la main. Dependabot, pas Renovate.
- Leçons détaillées : `decisions.md` §02.

## 08 Après le développement (avant le push)

- [ ] Tests : `make test`, plus `make test-accessibility` / `make test-e2e` si l'interface change ;
      un test de cloisonnement si l'accès aux données change.
- [ ] Correction de bug : un test reproduit d'abord le bug, puis la correction le fait passer.
- [ ] Qualité : `make style analyse-statique doctrine-validate` et `git diff --check`.
- [ ] Sécurité : `make composer ARGS="audit"`, `npm audit --audit-level=high`, `gitleaks git --log-opts="origin/main..HEAD"`,
      et les autres contrôles *push* de `securite.md` §04 ; la CI ajoute Trivy et Betterleaks.
- [ ] Commits Conventional Commits, description en français : `fix(stock): empêcher un mouvement négatif`.
- [ ] Une PR = un sujet, de taille raisonnable ; pas de refactorisation mêlée à une fonctionnalité.
- [ ] Documentation mise à jour dans la même PR que le code concerné.
- [ ] Avant toute PR : `git fetch origin && git rebase origin/main`, puis relancer les tests.
      Une branche déjà poussée ne se repousse qu'avec accord (`--force-with-lease`).
- [ ] Mettre à jour `etat.md` ; noter une leçon dans `decisions.md` si un bug a coûté du temps.
- [ ] Indiquer les contrôles réalisés et leurs limites.

## 09 Besoin d'aide ?

- Chercher dans `docs/context/`, puis dans le code existant un cas similaire.
- Demander avant une action destructive, une restauration, un changement de production ou une refonte.
- `CLAUDE.md` contient seulement `@AGENTS.md` ; ce fichier reste la source unique des consignes.
