<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Denree;
use App\Entity\GrilleMenu;
use App\Entity\Groupe;
use App\Entity\Menu;
use App\Entity\TypeRepas;
use App\Entity\Unite;
use App\Enum\TypeDenree;
use App\Enum\TypeDistributionMenu;
use App\Service\PreparationVuesDistribution;
use PHPUnit\Framework\TestCase;

final class PreparationVuesDistributionTest extends TestCase
{
    public function testScoutMarketRegroupeLesQuantitesDesMenusConcernesUniquement(): void
    {
        [$commande, $riz, $tomates] = $this->commande();

        $resultat = (new PreparationVuesDistribution())->scoutMarket([$commande]);

        self::assertCount(1, $resultat);
        self::assertSame([$riz], array_column($resultat[0]['lignes'], 'denree'));
        self::assertSame([12.0], array_column($resultat[0]['lignes'], 'quantite'));
        self::assertNotContains($tomates, array_column($resultat[0]['lignes'], 'denree'));
    }

    public function testEnCaisseConserveLesDenreesDeLaSourceSpecialeParGrilleEtUnite(): void
    {
        [$commande, $riz, $tomates, $yaourts] = $this->commande();

        $resultat = (new PreparationVuesDistribution())->enCaisse([$commande]);

        self::assertCount(1, $resultat);
        self::assertEquals(new \DateTimeImmutable('2026-08-31'), $resultat[0]['date']);
        self::assertCount(1, $resultat[0]['menus']);
        self::assertCount(1, $resultat[0]['menus'][0]['unites']);
        $detailUnite = $resultat[0]['menus'][0]['unites'][0];
        self::assertSame([$tomates, $yaourts], array_column($detailUnite['lignes'], 'denree'));
        self::assertNotContains($riz, array_column($detailUnite['lignes'], 'denree'));
    }

    public function testLesMenusEnCaisseSontRegroupesParJour(): void
    {
        [$commande] = $this->commande();

        $resultat = (new PreparationVuesDistribution())->enCaisse([$commande, $commande]);

        self::assertCount(1, $resultat);
        self::assertCount(1, $resultat[0]['menus']);
        self::assertSame([8.0, 4.0], array_column($resultat[0]['menus'][0]['unites'][0]['lignes'], 'quantite'));
    }

    public function testLesProduitsSecsSontRegroupesDansLaLivraisonInitiale(): void
    {
        [$commande, $riz, $tomates] = $this->commande();

        $resultat = (new PreparationVuesDistribution())->produitsSecsEnCaisse([$commande]);

        self::assertSame([$riz], array_column($resultat, 'denree'));
        self::assertSame([6.0], array_column($resultat, 'quantite'));
        self::assertNotContains($tomates, array_column($resultat, 'denree'));
    }

    /** @return array{array<string, mixed>, Denree, Denree, Denree, Menu} */
    private function commande(): array
    {
        $date = new \DateTimeImmutable('2026-08-31');
        $repas = new TypeRepas('DEJEUNER', 'Déjeuner');
        $unite = new Unite('gramme', 'g');
        $riz = (new Denree())->setNom('Riz')->setType(TypeDenree::SEC)->setUniteReference($unite);
        $tomates = (new Denree())->setNom('Tomates')->setType(TypeDenree::FRUITS_LEGUMES)->setUniteReference($unite);
        $yaourts = (new Denree())->setNom('Yaourts')->setType(TypeDenree::FRAIS)->setUniteReference($unite);
        $grilleScout = new GrilleMenu('Scout', $date, $date);
        $grilleCaisse = new GrilleMenu('Caisse', $date, $date);
        $menuScout = (new Menu())->setGrilleMenu($grilleScout)->setDateMenu($date)->setTypeRepas($repas);
        $menuCaisse = (new Menu())->setGrilleMenu($grilleCaisse)->setDateMenu($date)->setTypeRepas($repas)->setTypeDistribution(TypeDistributionMenu::EN_CAISSE);
        $explo = (new Menu())->setGrilleMenu($grilleCaisse)->setSpecialCode('EXPLO');
        $groupe = (new Groupe())->setNom('Farfadets')->setType('farfadets')->setDateDebutPresence($date)->setDateFinPresence($date);
        $ligne = static fn (Denree $denree, float $quantite): array => ['denree' => $denree, 'regime' => null, 'quantite' => $quantite, 'unite' => $unite];

        return [[
            'menu' => $menuScout,
            'lignes' => [],
            'grilles' => [
                ['grille' => $grilleScout, 'menu' => $menuScout, 'lignes' => [$ligne($riz, 12)], 'unites' => []],
                ['grille' => $grilleCaisse, 'menu' => $menuCaisse, 'lignes' => [$ligne($riz, 6), $ligne($tomates, 4), $ligne($yaourts, 2)], 'unites' => [[
                    'groupe' => $groupe,
                    'source' => $explo,
                    'lignes' => [$ligne($riz, 6), $ligne($tomates, 4), $ligne($yaourts, 2)],
                ]]],
            ],
        ], $riz, $tomates, $yaourts, $explo];
    }
}
