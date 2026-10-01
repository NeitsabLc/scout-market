<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Denree;
use App\Entity\Groupe;
use App\Entity\ReferenceFournisseurConditionnement;
use App\Enum\TypeDenree;
use App\Enum\TypeDistributionMenu;

final class CalculCommandeFinale
{
    public function __construct(private readonly ConversionConditionnement $conversion)
    {
    }

    /**
     * @param list<array{
     *     menu: \App\Entity\Menu,
     *     lignes: list<array{denree: Denree, regime: ?\App\Enum\RegimeAlimentaire, quantite: float, unite: \App\Entity\Unite}>,
     *     grilles?: list<array<string, mixed>>
     * }> $commandes
     * @param array<string, array{entrees: float, sorties: float}> $stocksActuels
     * @param list<ReferenceFournisseurConditionnement>            $niveaux
     *
     * @return list<array{
     *     denree: Denree,
     *     besoin: float,
     *     stock_previsionnel: float,
     *     quantite_commande: float,
     *     unite: \App\Entity\Unite,
     *     quantites_journalieres: list<array{date: \DateTimeImmutable, unites: list<array{groupe: Groupe, quantite: float}>}>
     * }>
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
        $commandesPeriode = array_slice($commandes, $premierRepasCommande, $dernierRepasCommande - $premierRepasCommande + 1);
        $besoinsPeriode = $this->agreger(
            $commandesPeriode,
            $niveaux,
            $secEnCaisseDejaLivre,
        );
        $quantitesJournalieres = $this->quantitesJournalieresParUnite(
            $commandesPeriode,
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
                'quantites_journalieres' => $quantitesJournalieres[$denreeId] ?? [],
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
     * @param list<array{menu: \App\Entity\Menu, grilles?: list<array<string, mixed>>}> $commandes
     * @param list<ReferenceFournisseurConditionnement>                                 $niveaux
     *
     * @return array<string, list<array{date: \DateTimeImmutable, unites: list<array{groupe: Groupe, quantite: float}>}>>
     */
    private function quantitesJournalieresParUnite(array $commandes, array $niveaux, bool $secEnCaisseDejaLivre): array
    {
        /** @var array<string, array<string, array{date: \DateTimeImmutable, unites: array<string, array{groupe: Groupe, quantite: float}>}>> $quantites */
        $quantites = [];
        foreach ($commandes as $commande) {
            $date = $commande['menu']->getDateMenu();
            if (null === $date) {
                continue;
            }
            $cleDate = $date->format('Y-m-d');
            foreach ($commande['grilles'] ?? [] as $grille) {
                $secDejaLivre = $secEnCaisseDejaLivre
                    && TypeDistributionMenu::EN_CAISSE === $grille['grille']->getTypeDistribution();
                foreach ($grille['unites'] ?? [] as $unite) {
                    /** @var Groupe $groupe */
                    $groupe = $unite['groupe'];
                    $cleGroupe = (string) $groupe->getId();
                    foreach ($unite['lignes'] as $ligne) {
                        $denree = $ligne['denree'];
                        if ($secDejaLivre && TypeDenree::SEC === $denree->getType()) {
                            continue;
                        }
                        $cleDenree = (string) $denree->getId();
                        $quantites[$cleDenree][$cleDate] ??= ['date' => $date, 'unites' => []];
                        $quantites[$cleDenree][$cleDate]['unites'][$cleGroupe] ??= ['groupe' => $groupe, 'quantite' => 0.0];
                        $quantites[$cleDenree][$cleDate]['unites'][$cleGroupe]['quantite'] += $this->conversion->convertirAvecNiveaux(
                            $denree,
                            $ligne['unite'],
                            $denree->getUniteInventaire(),
                            $ligne['quantite'],
                            $niveaux,
                        );
                    }
                }
            }
        }

        $resultat = [];
        foreach ($quantites as $cleDenree => $jours) {
            ksort($jours);
            foreach ($jours as $jour) {
                $unites = array_values($jour['unites']);
                usort($unites, static fn (array $a, array $b): int => strnatcasecmp($a['groupe']->getNom(), $b['groupe']->getNom()));
                foreach ($unites as &$unite) {
                    $unite['quantite'] = $this->normaliser($unite['quantite']);
                }
                unset($unite);
                $resultat[$cleDenree][] = ['date' => $jour['date'], 'unites' => $unites];
            }
        }

        return $resultat;
    }

    /**
     * @param array{
     *     lignes: list<array{denree: Denree, regime: ?\App\Enum\RegimeAlimentaire, quantite: float, unite: \App\Entity\Unite}>,
     *     grilles?: list<array{grille: \App\Entity\GrilleMenu, menu: \App\Entity\Menu, lignes: list<array{denree: Denree, regime: ?\App\Enum\RegimeAlimentaire, quantite: float, unite: \App\Entity\Unite}>}>
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
            $estDistribueeEnCaisse = TypeDistributionMenu::EN_CAISSE === $grille['grille']->getTypeDistribution();
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
