# Produit — Scout Market

> Ce que fait Scout Market, pour qui, selon quelles règles, et avec quel vocabulaire.
> Référence fonctionnelle : à maintenir avec le code.

## 01 Vue d'ensemble

| Élément | Valeur |
|---|---|
| Objectif | Gérer l'intendance permanente de Jambville : catalogue, recettes, menus, stocks, distributions et commandes |
| Utilisateurs | Équipe d'intendance, responsables d'unité |
| Supports | Ordinateur et tablette ; écran de distribution public |
| Hors périmètre | Aucune notion de séjour ; aucune donnée sur les jeunes (les unités sont décrites par des effectifs) |

La navigation conserve le design de Campement et regroupe les fonctions en quatre ensembles :

1. **Catalogue** : fournisseurs et denrées ;
2. **Préparation** : recettes et menus ;
3. **Flux** : mouvements de stock, distribution et commande ;
4. **Gestion** : unités participantes et utilisateurs.

Le tableau de bord présente les unités présentes à la date du jour, leurs effectifs, régimes et allergènes,
ainsi que le menu du jour.

## 02 Utilisateurs et habilitations

| Rôle | Peut | Périmètre de données |
|---|---|---|
| `ROLE_ADMIN` | Tout ce que peut `ROLE_GESTIONNAIRE`, plus la gestion des comptes | Toute l'application |
| `ROLE_GESTIONNAIRE` | Administrer l'intendance et les unités participantes | Toute l'intendance, sans les comptes |
| `ROLE_GROUPE` | Consulter la grille de son unité | Son unité uniquement |
| `ROLE_TECHNIQUE` | Attribuer les distributions publiques, sans pouvoir se connecter | Distribution liée au jeton |

## 03 Règles métier

### Modèle

- Fournisseurs, denrées, recettes, unités de mesure, types de repas, publics cibles, types et origines de mouvements
  sont globaux.
- Une denrée a un type logistique : `SEC`, `FRUITS_LEGUMES`, `FRAIS` ou `HYGIENE`. L'hygiène suit la logistique
  du sec : livrée en une fois sur la période en caisse.
- Une `GrilleMenu` porte un libellé unique, une date de début, une date de fin et un statut actif.
- Un `Menu` appartient à une grille, représente un repas daté ou un menu spécial et porte le mode `SCOUT_MARKET`
  ou `EN_CAISSE`. Les quantités ciblent un `PublicCible` et viennent d'une denrée ou d'une recette.
- Un `Groupe` représente une unité participante : période de présence, effectifs, régimes, allergènes, grille,
  et déclarations de repas explo, pique-nique ou non pris. Sa période pilote les besoins.

### Distribution

- La page **Scout Market** agrège, repas par repas, les denrées des menus de ce mode.
- La page **En caisse** contient une section repliable de sec et d'hygiène pour toute la période, puis une carte
  repliable par journée, avec une sous-section par grille puis par unité (fruits, légumes, frais), sans découpage par repas.
- Le mode de distribution ne change jamais les règles explo, pique-nique ou repas non pris : il ne choisit que
  la présentation logistique.

### Commande

Deux ajustements indépendants :

- **Sec en caisse déjà livré** : exclut le sec et l'hygiène des grilles en caisse des besoins et de la déduction de stock ;
- **Frais de la journée déjà livré** : exclut tous les repas de la journée du premier repas à déduire
  dans le calcul du stock prévisionnel.

## 04 Glossaire

> Un seul nom par notion, à l'écran, dans le code et en base. Scout Market code en français (`decisions.md`).

| À l'écran | Dans le code | Table | Définition |
|---|---|---|---|
| Unité participante | `Groupe` | `groupe` | Unité accueillie, décrite par sa période et ses effectifs |
| Grille | `GrilleMenu` | `grille_menu` | Ensemble de menus valable sur une période |
| Menu | `Menu` | `menu` | Repas daté ou menu spécial d'une grille |
| Denrée | `Denree` | `denree` | Produit acheté et stocké |
| Public | `PublicCible` | `public_cible` | Catégorie de convives pour les quantités (jeunes, adultes…) |
| Mouvement de stock | `MouvementStock` | `mouvement_stock` | Entrée, sortie ou correction de stock |
| Scout Market / En caisse | `SCOUT_MARKET` / `EN_CAISSE` | — | Mode logistique d'un menu |

| À utiliser | À ne jamais utiliser |
|---|---|
| Unité participante, `Groupe` | Séjour, camp, troupe |
| Denrée | Produit, article, ingrédient |
| Grille | Planning, calendrier de menus |

## 05 Parcours principaux

1. Gestionnaire → crée une grille et ses menus → les unités rattachées voient leurs repas.
2. Gestionnaire → enregistre une unité et sa période → les besoins et la commande sont recalculés.
3. Gestionnaire → prépare la commande avec les ajustements → exporte la liste de courses.
4. Équipe de distribution → ouvre l'écran public par son lien → enregistre les sorties de stock.
