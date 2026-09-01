# Journal des modifications

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
