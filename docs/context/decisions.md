# Décisions — Scout Market

> Choix durables et leçons apprises. Chaque entrée précise son motif ;
> une nouvelle décision remplace explicitement l'ancienne au lieu de s'y ajouter.
> Dates : premier commit qui introduit ou formalise la décision.

## 01 Décisions

| Date | Décision | Motif |
|---|---|---|
| 2026-09-08 | Liquibase possède le schéma ; Doctrine ne sert qu'au mapping et aux requêtes | Une seule chaîne de migration, relisible en SQL |
| 2026-09-27 | Un changeset appliqué est immuable ; sauvegarde avant toute migration durable | Rejouabilité et retour arrière |
| 2026-09-08 | Bases de test dédiées et recréables ; une base métier n'est jamais une cible de test destructive | Protéger les données réelles |
| 2026-09-27 | Contrôleurs fins ; règles métier et contrôles d'accès côté serveur | Testabilité, sécurité |
| 2026-09-27 | GitHub est la forge de référence ; `CONTRIBUTING.md` définit le cycle vers `main` | Un seul flux de revue |
| 2026-10-27 | Le GitLab SGDF (PNB) est un miroir alimenté par GitHub Actions, sans modification directe ; Dependabot tient lieu de Renovate (critère 2.22) | Renovate ferait diverger le miroir |
| 2026-09-27 | Release Please et images signées par digest | Livraison traçable |
| 2026-10-22 | Les détails privés de production appartiennent au projet Infra Homelab | Dépôt public |
| 2026-10-28 | Code, tables et colonnes en français, par exception à la règle commune (code en anglais) | Vocabulaire métier SGDF entièrement français |

## 02 Leçons apprises

> Chaque bug qui a coûté du temps devient une règle.

| Ce qui a cassé | Correction | Règle retenue |
|---|---|---|
| Des repas spéciaux d'une unité apparaissaient pour d'autres unités | Filtrage par groupe | Toute requête de distribution filtre par unité ; test de cloisonnement associé |
| Les commandes oubliaient les petits-déjeuners et goûters (#66) | Prise en compte de tous les types de repas | Le calcul de commande est testé pour chaque type de repas |
| Le rapport SARIF de la CI pouvait exposer des secrets (#60) | Masquage dans le rapport | Tout rapport publié par la CI est filtré des secrets |
| Une commande de création d'administrateur restait disponible | Commande retirée | Aucun raccourci de création de compte privilégié hors parcours prévu |
| Image PHP vulnérable (CVE PHP 8.4, #82, #88) | Image épinglée par digest, refus des versions vulnérables en CI | La CI refuse une image PHP antérieure à la version corrigée |
| Betterleaks échouait faute de droits sur le runner (#38) | Exécution avec l'utilisateur du runner | Les outils de CI tournent sans privilège, avec l'utilisateur du runner |
