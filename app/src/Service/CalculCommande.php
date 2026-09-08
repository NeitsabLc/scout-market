<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Groupe;
use App\Entity\GroupeRepas;
use App\Entity\Menu;
use App\Entity\MenuDenree;
use App\Enum\ModeRepasGroupe;

final class CalculCommande
{
    /**
     * @param list<Menu>        $menus
     * @param list<Groupe>      $groupes
     * @param list<GroupeRepas> $configurations
     *
     * @return list<array{
     *     menu: Menu,
     *     lignes: list<array{denree: \App\Entity\Denree, regime: ?\App\Enum\RegimeAlimentaire, quantite: float, unite: \App\Entity\Unite}>,
     *     grilles: list<array{
     *         grille: \App\Entity\GrilleMenu,
     *         menu: Menu,
     *         lignes: list<array{denree: \App\Entity\Denree, regime: ?\App\Enum\RegimeAlimentaire, quantite: float, unite: \App\Entity\Unite}>,
     *         unites: list<array{groupe: Groupe, source: Menu, lignes: list<array{denree: \App\Entity\Denree, regime: ?\App\Enum\RegimeAlimentaire, quantite: float, unite: \App\Entity\Unite}>}>
     *     }>
     * }>
     */
    public function calculer(array $menus, array $groupes, array $configurations = []): array
    {
        $menusSpeciaux = [];
        $repasLogiques = [];
        foreach ($menus as $menu) {
            $grilleId = (string) ($menu->getGrilleMenu()?->getId() ?? 'SANS_GRILLE');
            if ($menu->isSpecial()) {
                $menusSpeciaux[$grilleId][$menu->getSpecialCode() ?? ''] = $menu;
            } elseif (null !== $menu->getDateMenu()) {
                $cle = $menu->getDateMenu()->format('Y-m-d').'|'.(string) ($menu->getTypeRepas()?->getId() ?? 'SANS_TYPE');
                $repasLogiques[$cle] ??= ['menu' => $menu, 'parGrille' => []];
                $repasLogiques[$cle]['parGrille'][$grilleId] = $menu;
            }
        }
        $repasLogiques = array_values($repasLogiques);
        usort($repasLogiques, static fn (array $a, array $b): int => $a['menu']->getDateMenu() <=> $b['menu']->getDateMenu()
            ?: ($a['menu']->getTypeRepas()?->getOrdre() ?? 0) <=> ($b['menu']->getTypeRepas()?->getOrdre() ?? 0));

        $modes = [];
        foreach ($configurations as $configuration) {
            $modes[(string) $configuration->getGroupe()->getId()][(string) $configuration->getMenu()->getId()] = $configuration->getMode();
        }

        $commandes = [];
        foreach ($repasLogiques as $repasLogique) {
            /** @var Menu $menu */
            $menu = $repasLogique['menu'];
            $dateMenu = $menu->getDateMenu();
            if (null === $dateMenu) {
                continue;
            }
            $groupesPresents = array_values(array_filter(
                $groupes,
                static fn (Groupe $groupe): bool => $groupe->estPresentLe($dateMenu),
            ));
            $lignes = [];
            $grilles = [];
            foreach ($repasLogique['parGrille'] as $grilleId => $menuGrille) {
                if (null !== $menuGrille->getGrilleMenu()) {
                    $grilles[$grilleId] = [
                        'grille' => $menuGrille->getGrilleMenu(),
                        'menu' => $menuGrille,
                        'lignes' => [],
                        'unites' => [],
                    ];
                }
            }
            foreach ($groupesPresents as $groupe) {
                $grilleId = (string) ($groupe->getGrilleMenu()?->getId() ?? 'SANS_GRILLE');
                $menuStandard = $repasLogique['parGrille'][$grilleId] ?? null;
                if (!$menuStandard instanceof Menu) {
                    continue;
                }
                $mode = $modes[(string) $groupe->getId()][(string) $menuStandard->getId()] ?? null;
                if (ModeRepasGroupe::NON_PRIS === $mode) {
                    continue;
                }
                $source = null === $mode ? $menuStandard : ($menusSpeciaux[$grilleId][$mode->value] ?? null);
                if (!$source instanceof Menu) {
                    continue;
                }
                $lignesUnite = [];
                foreach ($source->getDenrees() as $ligneMenu) {
                    $regime = $ligneMenu->getRegime();
                    $cle = implode('|', [
                        (string) $ligneMenu->getDenree()->getId(),
                        null === $regime ? 'STANDARD' : $regime->value,
                        (string) $ligneMenu->getConditionnement()->getId(),
                    ]);
                    $lignes[$cle] ??= [
                        'denree' => $ligneMenu->getDenree(),
                        'regime' => $regime,
                        'quantite' => 0.0,
                        'unite' => $ligneMenu->getConditionnement(),
                    ];
                    $quantite = $this->quantitePourGroupe(
                        $ligneMenu,
                        $groupe,
                        $this->quantitesParPublic($ligneMenu),
                    );
                    $lignes[$cle]['quantite'] += $quantite;
                    $lignesUnite[$cle] ??= [
                        'denree' => $ligneMenu->getDenree(),
                        'regime' => $regime,
                        'quantite' => 0.0,
                        'unite' => $ligneMenu->getConditionnement(),
                    ];
                    $lignesUnite[$cle]['quantite'] += $quantite;
                    if (isset($grilles[$grilleId])) {
                        $grilles[$grilleId]['lignes'][$cle] ??= [
                            'denree' => $ligneMenu->getDenree(),
                            'regime' => $regime,
                            'quantite' => 0.0,
                            'unite' => $ligneMenu->getConditionnement(),
                        ];
                        $grilles[$grilleId]['lignes'][$cle]['quantite'] += $quantite;
                    }
                }
                if (isset($grilles[$grilleId])) {
                    $grilles[$grilleId]['unites'][] = [
                        'groupe' => $groupe,
                        'source' => $source,
                        'lignes' => $this->trierLignes($lignesUnite),
                    ];
                }
            }
            $lignes = $this->trierLignes($lignes);
            foreach ($grilles as &$grille) {
                $grille['lignes'] = $this->trierLignes($grille['lignes']);
                usort($grille['unites'], static fn (array $a, array $b): int => strnatcasecmp($a['groupe']->getNom(), $b['groupe']->getNom()));
            }
            unset($grille);
            $grilles = array_values($grilles);
            usort($grilles, static fn (array $a, array $b): int => strnatcasecmp($a['grille']->getLabel(), $b['grille']->getLabel()));
            $commandes[] = ['menu' => $menu, 'lignes' => $lignes, 'grilles' => $grilles];
        }

        return $commandes;
    }

    /** @return array<string, float> */
    private function quantitesParPublic(MenuDenree $ligne): array
    {
        $quantites = [];
        foreach ($ligne->getQuantites() as $quantite) {
            $quantites[$quantite->getPublicCible()->getCode()] = (float) $quantite->getQuantiteIndividuelle();
        }

        return $quantites;
    }

    /** @param array<string, float> $quantites */
    private function quantitePourGroupe(MenuDenree $ligne, Groupe $groupe, array $quantites): float
    {
        $codePublic = $this->codePublic($groupe);
        if (null === $ligne->getRegime()) {
            return ($quantites[$codePublic] ?? 0.0) * $groupe->getEffectifJeune()
                + ($quantites['ADULTE'] ?? 0.0) * $groupe->getEffectifAdulte();
        }

        return ($quantites[$codePublic] ?? 0.0) * $groupe->nombrePourRegime($ligne->getRegime());
    }

    private function codePublic(Groupe $groupe): string
    {
        return strtoupper(str_replace('-', '_', $groupe->getType()));
    }

    /**
     * @param array<string, array{denree: \App\Entity\Denree, regime: ?\App\Enum\RegimeAlimentaire, quantite: float, unite: \App\Entity\Unite}> $lignes
     *
     * @return list<array{denree: \App\Entity\Denree, regime: ?\App\Enum\RegimeAlimentaire, quantite: float, unite: \App\Entity\Unite}>
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
