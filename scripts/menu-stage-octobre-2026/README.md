# Menu des stages d’octobre 2026

Ce jeu importe les recettes et la grille du 17 au 24 octobre 2026. Il met à jour
les recettes portant déjà le même nom et initialise les quantités des publics
jeunes à zéro. Les quantités renseignées concernent uniquement les adultes.

Le lancement sans option effectue tous les contrôles puis annule la transaction :

```sh
scripts/menu-stage-octobre-2026/importer.sh
```

Après sauvegarde de la base et validation du contrôle, l’application explicite est :

```sh
scripts/menu-stage-octobre-2026/importer.sh --apply
```

Hypothèses validées : équipe de huit personnes, lieu à 150 g par adulte, bouillon
sec à 1 g par adulte, prunes à 48 g par adulte, samedi soir au velouté de potiron
et jeudi soir au velouté de 7 légumes. L’eau des naans n’est pas créée comme
denrée commandable.
