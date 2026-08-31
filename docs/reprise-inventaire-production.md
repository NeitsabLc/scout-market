# Reprise de l’inventaire Campement vers Scout Market

Ce document fixe le contrat du futur outil de reprise. Il ne constitue pas encore le
script d’import.

## Principes

- la production Campement est lue sans aucune écriture ;
- l’export est horodaté, versionné et accompagné de totaux de contrôle ;
- l’import Scout Market est rejouable et s’exécute d’abord en mode simulation ;
- les UUID source sont conservés lorsqu’ils ne provoquent aucun conflit ;
- chaque rejet est explicite et n’empêche pas l’établissement d’un rapport complet ;
- Liquibase ne transporte aucune donnée de production.

## Données concernées

Le périmètre minimal comprend :

1. unités de mesure ;
2. fournisseurs ;
3. denrées ;
4. références fournisseur et leurs conditionnements ;
5. mouvements de stock non annulés ;
6. lignes, quantités saisies, lots et détails de conditionnement associés.

Les séjours, participants, présences, situations particulières et documents sont hors
périmètre. Les relations de mouvement vers un séjour sont volontairement abandonnées.

## Contrôles avant validation

- unicité des noms globaux de denrées et fournisseurs après fusion des séjours ;
- correspondance des codes et symboles des référentiels ;
- intégrité de toutes les références et chaînes de conditionnement ;
- égalité des totaux d’entrées, de sorties et des soldes par denrée entre la source et
  la cible ;
- conservation des dates, auteurs, numéros de lot et annulations utiles ;
- production d’un manifeste contenant les comptes lus, importés, fusionnés et rejetés.

La stratégie de fusion des doublons inter-séjours devra être validée sur un export réel
avant l’écriture définitive du script.
