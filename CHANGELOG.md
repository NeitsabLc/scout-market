# Journal des modifications

## [0.1.6](https://github.com/NeitsabLc/scout-market/compare/v0.1.5...v0.1.6) (2026-09-06)


### Corrections

* **build:** actualiser le paquet age Alpine ([11c7ea5](https://github.com/NeitsabLc/scout-market/commit/11c7ea5cd28a5e517a9c0b77dc746bd14a204ae7))
* **release:** aligner les promotions automatiques ([2937773](https://github.com/NeitsabLc/scout-market/commit/2937773d0157a5dd7f5a03c71b97f2a9494b331b))
* **release:** automatiser la livraison vers le homelab ([e8f18f5](https://github.com/NeitsabLc/scout-market/commit/e8f18f5322a2920c912f7041651d5f04b6f5db78))
* **release:** automatiser la livraison vers le homelab ([3e5e562](https://github.com/NeitsabLc/scout-market/commit/3e5e56225f02414b0141c30281ecdf91607dd70d))

### Fonctionnement

- ajout d’une maintenance quotidienne qui purge les jetons de réinitialisation
  expirés, avec commande ponctuelle et test de non-régression ;
- isolation des données de démonstration hors production et chargement explicite
  de ces données dans la CI.

### Intégration continue et sécurité

- exécution des contrôles de qualité et du smoke de production sur les pull
  requests visant `main`, avec les statuts distincts `Qualite et tests` et
  `Configuration de production` ;
- audit Composer, ImportMap et npm, recherche de secrets et analyse Trivy des
  cinq images finales ;
- vérification de l’absence de publication du port PostgreSQL et utilisation du
  fichier HBA versionné dans le smoke de production ;
- ciblage de `main` par Dependabot et actualisation des dépendances Composer,
  npm et GitHub Actions.

## 0.1.5 — Administration des comptes

- gestion des comptes utilisateurs réservée aux administrateurs ;
- suppression du lien Utilisateurs dans la navigation des gestionnaires ;
- blocage côté serveur des accès directs d’un gestionnaire au module.

## 0.1.4 — Statistiques du tableau de bord

- remplacement du nombre de stocks suivis par le nombre de recettes actives ;
- accès direct au catalogue des recettes depuis la statistique ;
- suppression du calcul des stocks devenu inutile au chargement de l’accueil.

## 0.1.3 — Envoi d’e-mails OVH

- ajout des variables d’expéditeur aux conteneurs PHP ;
- utilisation de `no-reply@neitsab.net` comme expéditeur par défaut ;
- documentation du SMTP SSL/TLS OVH MX Plan.

## 0.1.2 — Installation web01 reproductible

- installation documentée dans `/srv/docker/scout-market` ;
- utilisation du sous-réseau Docker dédié `172.31.0.0/24` pour éviter Campement ;
- ajout d’un `pg_hba.conf` adapté au rôle unique et lisible par PostgreSQL.

## 0.1.1 — Déploiement initial simplifié

- ajout d’une procédure de déploiement sans CI ni registre d’images ;
- sauvegarde chiffrée rendue optionnelle et désactivée par défaut ;
- ajout d’un exemple d’environnement utilisant un rôle PostgreSQL unique.

## 0.1.0 — Scout Market

- extraction du seul périmètre Intendance de Campement ;
- suppression des séjours et des modules administratifs hors intendance ;
- catalogues et stocks rendus permanents et globaux ;
- grilles de menus dotées d’un libellé et d’une période modifiables ;
- navigation regroupée en Catalogue, Préparation, Flux et Gestion ;
- configuration de distribution globale ;
- classification logistique des denrées en sec, fruits et légumes ou frais ;
- modes de distribution Scout Market par repas et en caisse par journée ;
- prise en compte des produits déjà livrés dans le calcul de commande ;
- déclarations explo, pique-nique et repas non pris pour les unités ;
- tableau de bord des unités présentes, effectifs, régimes et allergènes ;
- nouveau schéma PostgreSQL `scout_market` ;
- historique Liquibase remplacé par une migration initiale unique ;
- commande interactive de création du premier administrateur de production ;
- livraison manuelle depuis GitHub et procédure d’exploitation documentées ;
- documentation du futur transfert d’inventaire depuis la production Campement.
