# Confidentialité — Scout Market

> Données personnelles traitées, utilité, durée de conservation et mesures de protection.
> Source unique de l'analyse d'impact (PIA) au sens du RGPD ; `PIA.md`, exigé par l'accueil SGDF, y renvoie.

## 01 Règles communes

- **Confidentialité dès la conception** : aucune donnée personnelle n'est ajoutée sans utilité écrite ici,
  durée de conservation et mécanisme de suppression, dès le premier développement.
- **Minimisation** : collecter le strict nécessaire ; préférer un nombre ou un agrégat à une donnée nominative.
- **Suppression dès la fin de l'usage** : chaque donnée a une purge automatique, testée, déclenchée
  au plus tôt (fin de séjour, désactivation, expiration) ; l'anonymisation remplace la suppression
  seulement quand une trace est nécessaire.
- **Cloisonnement** : une donnée n'est visible que dans le périmètre de son propriétaire ;
  tout accès est vérifié côté serveur et couvert par un test.
- Aucune donnée personnelle dans les journaux, les dépôts, les tickets ou les jeux de test.

## 02 Finalité

Gérer l'intendance du Scout Market de Jambville : catalogue, menus, stocks, distributions et commandes
pour les unités accueillies. L'application ne traite aucune donnée sur les jeunes : les unités sont décrites
par des effectifs.

## 03 Données personnelles

| Données | Personnes | Utilité | Conservation | Purge |
|---|---|---|---|---|
| Prénom, nom, adresse e-mail | Utilisateurs (intendance, responsables d'unité) | Connexion, invitations, réinitialisation du mot de passe | Compte désactivé à la fin de l'année scolaire de son unité, supprimé 1 mois après | `app:donnees:purger` |
| Empreinte du mot de passe | Utilisateurs | Authentification | Supprimée avec le compte | `app:donnees:purger` |
| Jeton de réinitialisation | Utilisateurs | Changement de mot de passe | Jusqu'à son expiration | `app:securite:purger-jetons-expires` |
| Auteur d'un mouvement de stock | Utilisateurs | Traçabilité des stocks | Anonymisé à la suppression du compte, au plus tard après 1 an | `app:donnees:purger` |
| Téléphone, e-mail, adresse | Contacts des fournisseurs | Passer les commandes | 2 ans après la désactivation du fournisseur | `app:donnees:purger` |

## 04 Données non personnelles

- Unités participantes : nom, période de présence, effectifs jeunes et adultes, **nombres** de régimes
  (végétarien, sans lactose, sans gluten), sans lien avec une personne ; supprimées après le 31 août
  qui suit leur présence.
- Catalogue, recettes, menus, stocks et commandes : conservés tant qu'ils sont utiles ; les mouvements de stock
  perdent tout lien vers une unité ou un compte supprimé.

## 05 Mesures de protection

- Purges lancées chaque jour par le service `maintenance` (`docker/php/maintenance.sh`).
- Accès par rôles (`ROLE_ADMIN`, `ROLE_GESTIONNAIRE`, `ROLE_GROUPE`) ; un responsable d'unité ne voit que son unité.
- Mots de passe hachés ; données sensibles masquées dans les journaux (`Logging/SensitiveDataProcessor.php`).
- Rôles PostgreSQL séparés ; l'application ne peut pas modifier le schéma.
- Sauvegardes chiffrées avec `age` ; la clé privée reste hors du serveur.
- Aucune donnée réelle dans le dépôt : jeux de données fictifs uniquement.

## 06 Droits des personnes

| Droit | Comment l'exercer |
|---|---|
| Accès, rectification | Profil utilisateur ; sinon demande à l'équipe d'intendance |
| Effacement | Suppression du compte par un administrateur |
| Contact | Responsable de traitement à désigner (§07) |

## 07 Points à surveiller

- Désigner le responsable de traitement et la personne à contacter pour l'exercice des droits.
- Fixer la durée de conservation des sauvegardes chez l'hébergeur.
