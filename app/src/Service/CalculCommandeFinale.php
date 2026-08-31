<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Denree;
use App\Entity\ReferenceFournisseurConditionnement;
use App\Enum\TypeDenree;
use App\Enum\TypeDistributionMenu;

final class CalculCommandeFinale
{
    public function __construct(private readonly ConversionConditionnement $conversion)
    {
    }

    /**
     * @param list<array{menu: \App\Entity\Menu, lignes: list<array{denree: Denree, regime: ?\App\Enum\RegimeAlimentaire, quantite: float, unite: \App\Entity\Unite}>}> $commandes
     * @param array<string, array{entrees: float, sorties: float}>                                                                                                      $stocksActuels
     * @param list<ReferenceFournisseurConditionnement>                                                                                                                 $niveaux
     *
     * @return list<array{denree: Denree, besoin: float, stock_previsionnel: float, quantite_commande: float, unite: \App\Entity\Unite}>
     */
    public function calculer(
        array $commandes,
        array $stocksActuels,
        array $niveaux,
        int $premierRepasDeduction,
        int $premierRepasCommande,
        int $dernierRepasCommande,
        bool $secEnCaisseDejaLivre = false,
        bool $fraisJourneeDejaLivre = false,
    ): array {
        if ($premierRepasDeduction < 0
            || $premierRepasDeduction > $premierRepasCommande
            || $premierRepasCommande > $dernierRepasCommande
            || $dernierRepasCommande >= count($commandes)) {
            throw new \InvalidArgumentException('La période de calcul des commandes est invalide.');
        }

        $commandesAvantPeriode = array_slice(
            $commandes,
            $premierRepasDeduction,
            $premierRepasCommande - $premierRepasDeduction,
        );
        if ($fraisJourneeDejaLivre) {
            $dateDejaLivree = $commandes[$premierRepasDeduction]['menu']->getDateMenu();
            $commandesAvantPeriode = array_values(array_filter(
                $commandesAvantPeriode,
                static fn (array $commande): bool => $commande['menu']->getDateMenu()?->format('Y-m-d') !== $dateDejaLivree?->format('Y-m-d'),
            ));
        }

        $besoinsAvantPeriode = $this->agreger(
            $commandesAvantPeriode,
            $niveaux,
            $secEnCaisseDejaLivre,
        );
        $besoinsPeriode = $this->agreger(
            array_slice($commandes, $premierRepasCommande, $dernierRepasCommande - $premierRepasCommande + 1),
            $niveaux,
            $secEnCaisseDejaLivre,
        );

        $resultat = [];
        foreach ($besoinsPeriode as $denreeId => $besoin) {
            $denree = $besoin['denree'];
            $stock = $stocksActuels[$denreeId] ?? ['entrees' => 0.0, 'sorties' => 0.0];
            $stockPrevisionnel = $stock['entrees'] - $stock['sorties'] - ($besoinsAvantPeriode[$denreeId]['quantite'] ?? 0.0);
            $quantiteCommande = max(0.0, $besoin['quantite'] - $stockPrevisionnel);
            $resultat[] = [
                'denree' => $denree,
                'besoin' => $this->normaliser($besoin['quantite']),
                'stock_previsionnel' => $this->normaliser($stockPrevisionnel),
                'quantite_commande' => $this->normaliser($quantiteCommande),
                'unite' => $denree->getUniteInventaire(),
            ];
        }
        usort($resultat, static fn (array $a, array $b): int => strnatcasecmp($a['denree']->getNom(), $b['denree']->getNom()));

        return $resultat;
    }

    /**
     * @param list<array{menu: \App\Entity\Menu, lignes: list<array{denree: Denree, regime: ?\App\Enum\RegimeAlimentaire, quantite: float, unite: \App\Entity\Unite}>}> $commandes
     * @param list<ReferenceFournisseurConditionnement>                                                                                                                 $niveaux
     *
     * @return array<string, array{denree: Denree, quantite: float}>
     */
    private function agreger(array $commandes, array $niveaux, bool $secEnCaisseDejaLivre): array
    {
        $besoins = [];
        foreach ($commandes as $commande) {
            foreach ($this->lignesPourCalcul($commande, $secEnCaisseDejaLivre) as $ligne) {
                $denree = $ligne['denree'];
                $denreeId = (string) $denree->getId();
                $besoins[$denreeId] ??= ['denree' => $denree, 'quantite' => 0.0];
                $besoins[$denreeId]['quantite'] += $this->conversion->convertirAvecNiveaux(
                    $denree,
                    $ligne['unite'],
                    $denree->getUniteInventaire(),
                    $ligne['quantite'],
                    $niveaux,
                );
            }
        }

        return $besoins;
    }

    /**
     * @param array{
     *     lignes: list<array{denree: Denree, regime: ?\App\Enum\RegimeAlimentaire, quantite: float, unite: \App\Entity\Unite}>,
     *     grilles?: list<array{menu: \App\Entity\Menu, lignes: list<array{denree: Denree, regime: ?\App\Enum\RegimeAlimentaire, quantite: float, unite: \App\Entity\Unite}>}>
     * } $commande
     *
     * @return list<array{denree: Denree, regime: ?\App\Enum\RegimeAlimentaire, quantite: float, unite: \App\Entity\Unite}>
     */
    private function lignesPourCalcul(array $commande, bool $secEnCaisseDejaLivre): array
    {
        if (!$secEnCaisseDejaLivre || !isset($commande['grilles']) || [] === $commande['grilles']) {
            return $commande['lignes'];
        }

        $lignes = [];
        foreach ($commande['grilles'] as $grille) {
            $estDistribueeEnCaisse = TypeDistributionMenu::EN_CAISSE === $grille['menu']->getTypeDistribution();
            foreach ($grille['lignes'] as $ligne) {
                if ($estDistribueeEnCaisse && TypeDenree::SEC === $ligne['denree']->getType()) {
                    continue;
                }
                $lignes[] = $ligne;
            }
        }

        return $lignes;
    }

    private function normaliser(float $quantite): float
    {
        return abs($quantite) < 0.000_000_1 ? 0.0 : $quantite;
    }
}
