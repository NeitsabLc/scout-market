# Données traitées — Scout Market

Ce document recense les données traitées par Scout Market, leur utilité et leur
durée de conservation. Il sert de base à l’analyse d’impact (PIA) au sens du RGPD.

## 01 Finalité

Gérer l’intendance du Scout Market de Jambville : catalogue, menus, stocks,
distributions et commandes pour les unités accueillies. L’application ne traite
aucune donnée sur les jeunes : les unités sont décrites par des effectifs.

## 02 Données personnelles

| Données | Personnes | Utilité | Conservation |
|---|---|---|---|
| Prénom, nom, adresse e-mail | Utilisateurs (intendance, responsables d’unité) | Connexion, invitations, réinitialisation du mot de passe | Compte désactivé à la fin de l’année scolaire de son unité, supprimé 1 mois après désactivation |
| Empreinte du mot de passe | Utilisateurs | Authentification | Supprimée avec le compte |
| Jeton de réinitialisation | Utilisateurs | Changement de mot de passe | Purgé dès son expiration |
| Auteur d’un mouvement de stock (audit) | Utilisateurs | Traçabilité des stocks | Anonymisé à la suppression du compte, au plus tard après 1 an |
| Téléphone, e-mail, adresse | Contacts des fournisseurs | Passer les commandes | Effacés 2 ans après la désactivation du fournisseur |

## 03 Données non personnelles

- Unités participantes : nom, période de présence, effectifs jeunes et adultes,
  **nombres** de régimes (végétarien, sans lactose, sans gluten), sans lien avec une
  personne. Supprimées après la fin de l’année scolaire (31 août)
  suivant leur présence.
- Catalogue, recettes, menus, stocks et commandes : conservés tant qu’ils sont utiles ;
  les mouvements de stock perdent tout lien vers une unité ou un compte supprimé.

## 04 Mesures de protection

- Purge automatique par la tâche `maintenance` (`app:donnees:purger` et
  `app:securite:purger-jetons-expires`), à planifier chaque jour.
- Accès par rôles (`ROLE_ADMIN`, `ROLE_GESTIONNAIRE`, `ROLE_GROUPE`) ; un responsable
  d’unité ne voit que son unité.
- Mots de passe hachés ; aucune donnée personnelle dans les journaux applicatifs.
- Rôles PostgreSQL séparés ; l’application ne peut pas modifier le schéma.
- Sauvegardes chiffrées avec `age` ; la clé privée reste hors du serveur.
- Aucune donnée réelle dans le dépôt : seuls des jeux de données fictifs servent au
  développement et aux tests.

## 05 Points à valider avec le commanditaire

- Responsable de traitement et personne à contacter pour l’exercice des droits.
- Durée de conservation des sauvegardes chez l’hébergeur.
