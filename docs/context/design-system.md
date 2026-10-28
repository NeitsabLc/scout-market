# Design system — Scout Market

> Charte graphique Scouts et Guides de France (SGDF), commune à Campement, Bénévoles Jambville et Scout Market.
> Source des valeurs : `app/assets/styles/app.css` (`:root`). Toute nouvelle valeur passe d'abord par une variable.
> Les écarts propres à l'application sont listés en §10.

## 01 Identité et logo

| Fichier | Usage |
|---|---|
| `app/assets/images/logo-sgdf-horizontal.png` | En-têtes, page de connexion |
| `app/assets/images/logo-sgdf-vertical.png` | Espaces étroits, mise en page verticale |
| `app/assets/images/favicon.svg` | Icône d'onglet |
| `app/assets/images/repas-viande.svg` | Pictogramme de type de repas |

- Ne pas déformer, recolorer ni recadrer le logo ; garder une zone de respiration autour.
- Toujours avec un texte alternatif (`alt="Scouts et Guides de France"`).

## 02 Couleurs

| Variable | Valeur | Usage |
|---|---|---|
| `--sgdf-blue` | `#003a5d` | Couleur principale : titres, navigation, boutons primaires |
| `--sgdf-blue-light` | `#0077b3` | Liens, éléments actifs |
| `--sgdf-mint` | `#65bc99` | Accent, focus clavier, succès |
| `--surface` | `#ffffff` | Fond des cartes et formulaires |
| `--surface-muted` | `#f4f7f9` | Fond de page, survol des lignes |
| `--border` | `#d9e2e7` | Bordures, séparateurs |
| `--text-muted` | `#526875` | Texte secondaire |
| `--danger` | `#b42318` | Erreurs, actions destructives |
| `--danger-surface` | `#fef3f2` | Fond des messages d'erreur |

- Contraste minimal 4.5:1 pour le texte (3:1 pour les grands titres et les icônes).
- Ne jamais transmettre une information par la couleur seule (ajouter texte ou icône).
- Aucune couleur codée en dur : remplacer celles qui existent quand on touche le bloc.

## 03 Typographie

| Rôle | Police | Variable | Repli |
|---|---|---|---|
| Titres, navigation, boutons | Raleway (400, 700) | `--font-title` | "Segoe UI", Arial, sans-serif |
| Texte courant, formulaires | Sarabun (400, 700, italique) | `--font-body` | "Segoe UI", Arial, sans-serif |

- Polices auto-hébergées dans `app/assets/fonts/` (aucun appel à un service externe).
- Tailles en `rem` ; texte courant ≥ 1rem, mentions et métadonnées ≥ 0.75rem.

## 04 Espacements et rayons

| Élément | Valeur |
|---|---|
| Rayon des champs et boutons | `0.3rem` |
| Rayon des cartes | `0.45rem` |
| Pastilles | `50%` |
| Ombre | `--shadow`, réservée aux éléments flottants (dialogues, menus) |
| Espacements | Échelle `0.25 / 0.5 / 1 / 1.5 / 2 / 3rem`, aucune autre valeur |

## 05 Icônes

- SVG en ligne, `fill`/`stroke` à `currentColor`, taille alignée sur le texte (1em à 1.25rem).
- Icône décorative : `aria-hidden="true"` ; bouton composé d'une seule icône : `aria-label` obligatoire.
- Réutiliser une icône existante avant d'en dessiner une ; style linéaire, trait régulier.

## 06 Composants

| Élément | Règle |
|---|---|
| Boutons | `.button`, `.button--primary` (bleu SGDF), `.button--danger-outline` ; un seul bouton primaire par zone |
| Focus | `outline: 3px solid var(--sgdf-mint)` + `outline-offset: 2px`, jamais supprimé |
| Tableaux | `components/list_table.html.twig` et `sort_indicator.html.twig` pour les listes triables |

## 07 Messages et retours utilisateur

| Type | Affichage | Durée |
|---|---|---|
| Succès, information | Pop-up en haut à droite (`role="status"`) | Disparaît après **3 s** |
| Erreur générale | Pop-up en haut à droite (`role="alert"`) | Disparaît après **3 s** |
| Erreur de champ | Sous le champ, liée par `aria-describedby`, champ en `aria-invalid="true"` | Reste jusqu'à correction |

- Bouton de fermeture, pause du minuteur au survol et au focus, empilement de haut en bas.
- Messages courts, en français, orientés action (« Saisissez une date de fin postérieure au début »).

## 08 Accessibilité

- [ ] Cible WCAG 2.2 AA / RGAA, contrôlée par Axe.
- [ ] Chaque champ a un `<label>` visible ; les erreurs sont annoncées.
- [ ] Navigation complète au clavier, ordre logique, focus visible.
- [ ] Zones cliquables d'au moins 24 × 24 px (44 px recommandé sur mobile).

## 09 Points de rupture

| Nom | Largeur | Comportement |
|---|---|---|
| Mobile | < 48rem | Une colonne |
| Tablette | 48–70rem | Paliers intermédiaires à `60rem` et `70rem` |
| Bureau | > 70rem | Navigation latérale dépliée |

- Navigation latérale repliée sous `760px`.

## 10 Écarts propres à l'application

| Élément | Écart | Motif |
|---|---|---|
| Logo | `logo-jambville-horizontal.png` présent, non utilisé aujourd'hui | Domaine de Jambville ; usage à décider |
| Polices | `caveat-brush/` présente mais inutilisée | Ne pas l'employer sans décision |
| Messages | Les flashs `global-flash` s'affichent encore dans la page | Passage aux pop-ups de §07 à faire (`etat.md`) |
| Couleurs | Des couleurs codées en dur subsistent dans `app.css` | Remplacement progressif par les variables |
