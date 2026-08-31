<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Denree;
use App\Entity\GrilleMenu;
use App\Entity\Menu;
use App\Entity\Unite;
use App\Enum\TypeDenree;
use App\Enum\TypeDistributionMenu;
use App\Service\CalculCommandeFinale;
use App\Service\ConversionConditionnement;
use PHPUnit\Framework\TestCase;

final class CalculCommandeFinaleTest extends TestCase
{
    public function testElleCumuleLaPeriodeEtDeduitLesBesoinsPrecedentsDuStock(): void
    {
        $date = new \DateTimeImmutable('2026-08-14');
        $kilogramme = new Unite('kilogramme', 'kg');
        $tomates = (new Denree())->setNom('Tomates')->setUniteReference($kilogramme)->setUniteInventaire($kilogramme);
        $menus = [
            (new Menu())->setDateMenu($date),
            (new Menu())->setDateMenu($date->modify('+1 day')),
            (new Menu())->setDateMenu($date->modify('+2 days')),
        ];
        $commandes = [];
        foreach ([2.0, 4.0, 6.0] as $index => $quantite) {
            $commandes[] = ['menu' => $menus[$index], 'lignes' => [[
                'denree' => $tomates,
                'regime' => null,
                'quantite' => $quantite,
                'unite' => $kilogramme,
            ]]];
        }
        $conversion = (new \ReflectionClass(ConversionConditionnement::class))->newInstanceWithoutConstructor();

        $resultat = (new CalculCommandeFinale($conversion))->calculer(
            $commandes,
            [(string) $tomates->getId() => ['entrees' => 10.0, 'sorties' => 1.0]],
            [],
            0,
            1,
            2,
        );

        self::assertCount(1, $resultat);
        self::assertSame(10.0, $resultat[0]['besoin']);
        self::assertSame(7.0, $resultat[0]['stock_previsionnel']);
        self::assertSame(3.0, $resultat[0]['quantite_commande']);
        self::assertSame($kilogramme, $resultat[0]['unite']);
    }

    public function testElleIgnoreLeSecDesGrillesEnCaisseDejaLivreesDansLeBesoinEtLaDeduction(): void
    {
        $date = new \DateTimeImmutable('2026-08-14');
        $kilogramme = new Unite('kilogramme', 'kg');
        $riz = (new Denree())->setNom('Riz')->setType(TypeDenree::SEC)->setUniteReference($kilogramme)->setUniteInventaire($kilogramme);
        $tomates = (new Denree())->setNom('Tomates')->setType(TypeDenree::FRAIS)->setUniteReference($kilogramme)->setUniteInventaire($kilogramme);
        $grilleCaisse = new GrilleMenu('En caisse', $date, $date->modify('+1 day'));
        $grilleScoutMarket = new GrilleMenu('Scout Market', $date, $date->modify('+1 day'));

        $commandes = [];
        foreach ([
            [$date, 2.0, 1.0, 3.0],
            [$date->modify('+1 day'), 4.0, 2.0, 5.0],
        ] as [$dateMenu, $rizCaisse, $tomatesCaisse, $rizScoutMarket]) {
            $menuCaisse = (new Menu())->setGrilleMenu($grilleCaisse)->setDateMenu($dateMenu)->setTypeDistribution(TypeDistributionMenu::EN_CAISSE);
            $menuScoutMarket = (new Menu())->setGrilleMenu($grilleScoutMarket)->setDateMenu($dateMenu);
            $ligne = static fn (Denree $denree, float $quantite): array => [
                'denree' => $denree,
                'regime' => null,
                'quantite' => $quantite,
                'unite' => $kilogramme,
            ];
            $lignesCaisse = [$ligne($riz, $rizCaisse), $ligne($tomates, $tomatesCaisse)];
            $lignesScoutMarket = [$ligne($riz, $rizScoutMarket)];
            $commandes[] = [
                'menu' => $menuCaisse,
                'lignes' => [...$lignesCaisse, ...$lignesScoutMarket],
                'grilles' => [
                    ['menu' => $menuCaisse, 'lignes' => $lignesCaisse],
                    ['menu' => $menuScoutMarket, 'lignes' => $lignesScoutMarket],
                ],
            ];
        }
        $conversion = (new \ReflectionClass(ConversionConditionnement::class))->newInstanceWithoutConstructor();

        $resultat = (new CalculCommandeFinale($conversion))->calculer(
            $commandes,
            [],
            [],
            0,
            1,
            1,
            true,
        );
        $parDenree = [];
        foreach ($resultat as $ligne) {
            $parDenree[$ligne['denree']->getNom()] = $ligne;
        }

        self::assertSame(5.0, $parDenree['Riz']['besoin']);
        self::assertSame(-3.0, $parDenree['Riz']['stock_previsionnel']);
        self::assertSame(8.0, $parDenree['Riz']['quantite_commande']);
        self::assertSame(2.0, $parDenree['Tomates']['besoin']);
        self::assertSame(-1.0, $parDenree['Tomates']['stock_previsionnel']);
        self::assertSame(3.0, $parDenree['Tomates']['quantite_commande']);
    }

    public function testElleIgnoreTouteLaJourneeDejaLivreeDansLaDeductionDuStock(): void
    {
        $date = new \DateTimeImmutable('2026-08-14');
        $kilogramme = new Unite('kilogramme', 'kg');
        $tomates = (new Denree())->setNom('Tomates')->setType(TypeDenree::FRAIS)->setUniteReference($kilogramme)->setUniteInventaire($kilogramme);
        $commandes = [];
        foreach ([[$date, 2.0], [$date, 3.0], [$date->modify('+1 day'), 4.0]] as [$dateMenu, $quantite]) {
            $commandes[] = [
                'menu' => (new Menu())->setDateMenu($dateMenu),
                'lignes' => [[
                    'denree' => $tomates,
                    'regime' => null,
                    'quantite' => $quantite,
                    'unite' => $kilogramme,
                ]],
            ];
        }
        $conversion = (new \ReflectionClass(ConversionConditionnement::class))->newInstanceWithoutConstructor();

        $resultat = (new CalculCommandeFinale($conversion))->calculer(
            $commandes,
            [(string) $tomates->getId() => ['entrees' => 10.0, 'sorties' => 0.0]],
            [],
            0,
            2,
            2,
            false,
            true,
        );

        self::assertSame(4.0, $resultat[0]['besoin']);
        self::assertSame(10.0, $resultat[0]['stock_previsionnel']);
        self::assertSame(0.0, $resultat[0]['quantite_commande']);
    }
}
