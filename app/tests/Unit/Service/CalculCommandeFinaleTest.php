<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Denree;
use App\Entity\GrilleMenu;
use App\Entity\Groupe;
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
        $grilleCaisse = (new GrilleMenu('En caisse', $date, $date->modify('+1 day')))->setTypeDistribution(TypeDistributionMenu::EN_CAISSE);
        $grilleScoutMarket = new GrilleMenu('Scout Market', $date, $date->modify('+1 day'));

        $commandes = [];
        foreach ([
            [$date, 2.0, 1.0, 3.0],
            [$date->modify('+1 day'), 4.0, 2.0, 5.0],
        ] as [$dateMenu, $rizCaisse, $tomatesCaisse, $rizScoutMarket]) {
            $menuCaisse = (new Menu())->setGrilleMenu($grilleCaisse)->setDateMenu($dateMenu);
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
                    ['grille' => $grilleCaisse, 'menu' => $menuCaisse, 'lignes' => $lignesCaisse],
                    ['grille' => $grilleScoutMarket, 'menu' => $menuScoutMarket, 'lignes' => $lignesScoutMarket],
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

    public function testElleConserveLesQuantitesJournalieresParUnite(): void
    {
        $date = new \DateTimeImmutable('2026-08-14');
        $piece = new Unite('pièce', 'pc');
        $pain = (new Denree())->setNom('Pain')->setUniteReference($piece)->setUniteInventaire($piece);
        $grille = new GrilleMenu('Principale', $date, $date->modify('+1 day'));
        $alpha = (new Groupe())->setNom('Alpha');
        $bravo = (new Groupe())->setNom('Bravo');
        $ligne = static fn (float $quantite): array => [
            'denree' => $pain,
            'regime' => null,
            'quantite' => $quantite,
            'unite' => $piece,
        ];
        $commandes = [];
        foreach ([
            [$date, [[$alpha, 4.0], [$bravo, 6.0]]],
            [$date->modify('+1 day'), [[$alpha, 5.0]]],
        ] as [$dateMenu, $quantites]) {
            $menu = (new Menu())->setGrilleMenu($grille)->setDateMenu($dateMenu);
            $unites = [];
            $total = 0.0;
            foreach ($quantites as [$groupe, $quantite]) {
                $unites[] = ['groupe' => $groupe, 'lignes' => [$ligne($quantite)]];
                $total += $quantite;
            }
            $commandes[] = [
                'menu' => $menu,
                'lignes' => [$ligne($total)],
                'grilles' => [[
                    'grille' => $grille,
                    'menu' => $menu,
                    'lignes' => [$ligne($total)],
                    'unites' => $unites,
                ]],
            ];
        }
        $conversion = (new \ReflectionClass(ConversionConditionnement::class))->newInstanceWithoutConstructor();

        $resultat = (new CalculCommandeFinale($conversion))->calculer($commandes, [], [], 0, 0, 1);

        self::assertSame(15.0, $resultat[0]['besoin']);
        self::assertCount(2, $resultat[0]['quantites_journalieres']);
        self::assertSame('2026-08-14', $resultat[0]['quantites_journalieres'][0]['date']->format('Y-m-d'));
        self::assertSame(['Alpha', 'Bravo'], array_map(
            static fn (array $unite): string => $unite['groupe']->getNom(),
            $resultat[0]['quantites_journalieres'][0]['unites'],
        ));
        self::assertSame([4.0, 6.0], array_column($resultat[0]['quantites_journalieres'][0]['unites'], 'quantite'));
        self::assertSame([5.0], array_column($resultat[0]['quantites_journalieres'][1]['unites'], 'quantite'));
    }
}
