<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Denree;
use App\Entity\Menu;
use App\Entity\MenuDenree;
use App\Entity\Recette;
use App\Entity\RecetteDenree;
use App\Entity\TypeRepas;
use App\Entity\Unite;
use App\Enum\RegimeAlimentaire;
use App\Service\PresentationMenu;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class PresentationMenuTest extends TestCase
{
    public function testLaDateDuJourEstSelectionneeParDefautQuandElleEstDansLaGrille(): void
    {
        $presentation = new PresentationMenu();
        $debut = new \DateTimeImmutable('2026-07-10');
        $fin = new \DateTimeImmutable('2026-07-20');

        self::assertEquals(
            new \DateTimeImmutable('2026-07-15'),
            $presentation->date(new Request(), $debut, $fin, new \DateTimeImmutable('2026-07-15 14:30')),
        );
        self::assertSame(
            $debut,
            $presentation->date(new Request(), $debut, $fin, new \DateTimeImmutable('2026-08-01')),
        );
        self::assertEquals(
            new \DateTimeImmutable('2026-07-18'),
            $presentation->date(new Request(['date' => '2026-07-18']), $debut, $fin, new \DateTimeImmutable('2026-07-15')),
        );
    }

    public function testLaCategorieDesRecettesDependDuTypeDeRepas(): void
    {
        $presentation = new PresentationMenu();

        self::assertSame(['PETIT_DEJEUNER'], $presentation->categoriesRecettesPourRepas('PETIT_DEJEUNER'));
        self::assertSame(['GOUTER', 'DESSERT'], $presentation->categoriesRecettesPourRepas('GOUTER'));
        self::assertNull($presentation->categoriesRecettesPourRepas('DEJEUNER'));
        self::assertNull($presentation->categoriesRecettesPourRepas('DINER'));
    }

    public function testLeRepasSuivantRespecteLOrdreDesRepasPuisChangeDeJour(): void
    {
        $presentation = new PresentationMenu();
        $premierJour = new \DateTimeImmutable('2026-07-10');
        $dernierJour = new \DateTimeImmutable('2026-07-11');
        $petitDejeuner = new TypeRepas('PETIT_DEJEUNER', 'Petit-déjeuner', 1);
        $dejeuner = new TypeRepas('DEJEUNER', 'Déjeuner', 2);
        $repas = [$petitDejeuner, $dejeuner];

        self::assertSame(
            ['date' => $premierJour, 'repas' => $dejeuner],
            $presentation->repasSuivant($premierJour, $petitDejeuner, $repas, $dernierJour),
        );
        $repasDuJourSuivant = $presentation->repasSuivant($premierJour, $dejeuner, $repas, $dernierJour);
        self::assertNotNull($repasDuJourSuivant);
        self::assertEquals($dernierJour, $repasDuJourSuivant['date']);
        self::assertSame($petitDejeuner, $repasDuJourSuivant['repas']);
        self::assertNull($presentation->repasSuivant($dernierJour, $dejeuner, $repas, $dernierJour));
    }

    public function testLeRegimeDeLaRecetteEstTransmisAuxDonneesDuMenu(): void
    {
        $unite = new Unite('Gramme', 'g');
        $denree = (new Denree())->setNom('Protéines végétales')->setUniteReference($unite);
        $recette = (new Recette())->setNom('Plat végétarien');
        $recette->addDenree((new RecetteDenree())
            ->setDenree($denree)
            ->setConditionnement($unite)
            ->setRegime(RegimeAlimentaire::VEGETARIEN));

        $donnees = (new PresentationMenu())->recettesJson([$recette]);

        self::assertSame(
            RegimeAlimentaire::VEGETARIEN->value,
            $donnees[(string) $recette->getId()]['lignes'][0]['regime'],
        );
    }

    public function testLeResumeDesMenusRegroupeLesRecettesEtSepareLesDenreesSupplementaires(): void
    {
        $unite = new Unite('Gramme', 'g');
        $repas = new TypeRepas('DEJEUNER', 'Déjeuner', 1);
        $recette = (new Recette())->setNom('Salade composée');
        $tomate = (new Denree())->setNom('Tomates')->setUniteReference($unite);
        $concombre = (new Denree())->setNom('Concombres')->setUniteReference($unite);
        $pain = (new Denree())->setNom('Pain')->setUniteReference($unite);
        $menu = (new Menu())->setTypeRepas($repas)->setNom('Déjeuner frais');
        $menu->addDenree((new MenuDenree())->setDenree($tomate)->setRecette($recette));
        $menu->addDenree((new MenuDenree())->setDenree($concombre)->setRecette($recette));
        $menu->addDenree((new MenuDenree())->setDenree($pain));

        self::assertSame([[
            'libelle' => 'Déjeuner',
            'nom' => 'Déjeuner frais',
            'elements' => ['Salade composée', 'Pain'],
            'recettes' => ['Salade composée'],
            'supplementaires' => ['Pain'],
        ]], (new PresentationMenu())->resumesMenus([$menu]));
    }

    public function testLeResumeDesMenusSuitLOrdreEntreePlatFromageDessert(): void
    {
        $unite = new Unite('Gramme', 'g');
        $repas = new TypeRepas('DEJEUNER', 'Déjeuner', 1);
        $menu = (new Menu())->setTypeRepas($repas);

        foreach ([
            ['DESSERT', 'Tarte', false],
            ['FROMAGE', 'Brie', false],
            ['ENTREE', 'Salade', true],
            ['PLAT', 'Poisson', true],
        ] as [$categorie, $nom, $avecRecette]) {
            $recette = (new Recette())->setNom($nom)->setCategorie($categorie);
            $denree = (new Denree())->setNom('Ingrédient '.$nom)->setUniteReference($unite);
            $ligne = (new MenuDenree())->setDenree($denree)->setCategorie($categorie);
            $menu->addDenree($avecRecette ? $ligne->setRecette($recette) : $ligne);
        }

        self::assertSame(
            ['Salade', 'Poisson', 'Ingrédient Brie', 'Ingrédient Tarte'],
            (new PresentationMenu())->resumesMenus([$menu])[0]['elements'],
        );
    }
}
