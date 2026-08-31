<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Menu;
use App\Enum\TypeDenree;
use App\Enum\TypeDistributionMenu;

final class PreparationVuesDistribution
{
    /**
     * @param list<array{menu: Menu, lignes: list<array<string, mixed>>, grilles: list<array<string, mixed>>}> $commandes
     *
     * @return list<array{menu: Menu, lignes: list<array<string, mixed>>}>
     */
    public function scoutMarket(array $commandes): array
    {
        $resultat = [];
        foreach ($commandes as $commande) {
            /** @var array<string, array<string, mixed>> $lignes */
            $lignes = [];
            foreach ($commande['grilles'] as $grille) {
                if (TypeDistributionMenu::SCOUT_MARKET !== $grille['menu']->getTypeDistribution()) {
                    continue;
                }
                foreach ($grille['lignes'] as $ligne) {
                    $this->ajouterLigne($lignes, $ligne);
                }
            }
            if ([] !== $lignes) {
                $resultat[] = ['menu' => $commande['menu'], 'lignes' => $this->trierLignes($lignes)];
            }
        }

        return $resultat;
    }

    /**
     * @param list<array{menu: Menu, lignes: list<array<string, mixed>>, grilles: list<array<string, mixed>>}> $commandes
     *
     * @return list<array{date: \DateTimeImmutable, menus: list<array{grille: \App\Entity\GrilleMenu, unites: list<array{groupe: \App\Entity\Groupe, lignes: list<array<string, mixed>>}>}>}>
     */
    public function enCaisse(array $commandes): array
    {
        /** @var array<string, array{date: \DateTimeImmutable, menus: array<string, array{grille: \App\Entity\GrilleMenu, unites: array<string, array{groupe: \App\Entity\Groupe, lignes: array<string, array<string, mixed>>}>}>}> $jours */
        $jours = [];
        foreach ($commandes as $commande) {
            $date = $commande['menu']->getDateMenu();
            if (null === $date) {
                continue;
            }
            $cleDate = $date->format('Y-m-d');
            foreach ($commande['grilles'] as $grille) {
                if (TypeDistributionMenu::EN_CAISSE !== $grille['menu']->getTypeDistribution()) {
                    continue;
                }
                $cleGrille = (string) $grille['grille']->getId();
                foreach ($grille['unites'] as $unite) {
                    $lignes = array_values(array_filter(
                        $unite['lignes'],
                        static fn (array $ligne): bool => in_array($ligne['denree']->getType(), [TypeDenree::FRUITS_LEGUMES, TypeDenree::FRAIS], true),
                    ));
                    if ([] === $lignes) {
                        continue;
                    }
                    $jours[$cleDate] ??= ['date' => $date, 'menus' => []];
                    $jours[$cleDate]['menus'][$cleGrille] ??= ['grille' => $grille['grille'], 'unites' => []];
                    $cleUnite = (string) $unite['groupe']->getId();
                    $jours[$cleDate]['menus'][$cleGrille]['unites'][$cleUnite] ??= ['groupe' => $unite['groupe'], 'lignes' => []];
                    foreach ($lignes as $ligne) {
                        $this->ajouterLigne($jours[$cleDate]['menus'][$cleGrille]['unites'][$cleUnite]['lignes'], $ligne);
                    }
                }
            }
        }

        $resultat = [];
        foreach ($jours as $jour) {
            $menus = [];
            foreach ($jour['menus'] as $menu) {
                $unites = [];
                foreach ($menu['unites'] as $unite) {
                    $unite['lignes'] = $this->trierLignes($unite['lignes']);
                    $unites[] = $unite;
                }
                usort($unites, static fn (array $a, array $b): int => strnatcasecmp($a['groupe']->getNom(), $b['groupe']->getNom()));
                $menus[] = ['grille' => $menu['grille'], 'unites' => $unites];
            }
            usort($menus, static fn (array $a, array $b): int => strnatcasecmp($a['grille']->getLabel(), $b['grille']->getLabel()));
            $resultat[] = ['date' => $jour['date'], 'menus' => $menus];
        }

        return $resultat;
    }

    /**
     * @param list<array{menu: Menu, lignes: list<array<string, mixed>>, grilles: list<array<string, mixed>>}> $commandes
     *
     * @return list<array<string, mixed>>
     */
    public function produitsSecsEnCaisse(array $commandes): array
    {
        /** @var array<string, array<string, mixed>> $lignes */
        $lignes = [];
        foreach ($commandes as $commande) {
            foreach ($commande['grilles'] as $grille) {
                if (TypeDistributionMenu::EN_CAISSE !== $grille['menu']->getTypeDistribution()) {
                    continue;
                }
                foreach ($grille['lignes'] as $ligne) {
                    if (TypeDenree::SEC === $ligne['denree']->getType()) {
                        $this->ajouterLigne($lignes, $ligne);
                    }
                }
            }
        }

        return $this->trierLignes($lignes);
    }

    /**
     * @param array<string, array<string, mixed>> $lignes
     * @param array<string, mixed>                $ligne
     */
    private function ajouterLigne(array &$lignes, array $ligne): void
    {
        $cle = implode('|', [
            (string) $ligne['denree']->getId(),
            null === $ligne['regime'] ? 'STANDARD' : $ligne['regime']->value,
            (string) $ligne['unite']->getId(),
        ]);
        $lignes[$cle] ??= [...$ligne, 'quantite' => 0.0];
        $lignes[$cle]['quantite'] += $ligne['quantite'];
    }

    /**
     * @param array<string, array<string, mixed>> $lignes
     *
     * @return list<array<string, mixed>>
     */
    private function trierLignes(array $lignes): array
    {
        $lignes = array_values($lignes);
        usort($lignes, static fn (array $a, array $b): int => strnatcasecmp($a['denree']->getNom(), $b['denree']->getNom())
            ?: strnatcasecmp($a['regime']?->libelle() ?? '', $b['regime']?->libelle() ?? '')
            ?: strnatcasecmp($a['unite']->getNom(), $b['unite']->getNom()));

        return $lignes;
    }
}
