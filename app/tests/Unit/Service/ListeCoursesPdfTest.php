<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Denree;
use App\Entity\Groupe;
use App\Entity\Menu;
use App\Entity\MenuDenree;
use App\Entity\Unite;
use App\Enum\RegimeAlimentaire;
use App\Service\AffichageQuantite;
use App\Service\ListeCoursesPdf;
use PHPUnit\Framework\TestCase;

final class ListeCoursesPdfTest extends TestCase
{
    public function testLaFicheConserveSeulementLesRegimesNecessairesAuGroupe(): void
    {
        $unite = new Unite('Gramme', 'g');
        $denree = (new Denree())->setNom('Protéines')->setUniteReference($unite);
        $menu = new Menu();
        foreach ([null, RegimeAlimentaire::VEGETARIEN, RegimeAlimentaire::SANS_GLUTEN] as $regime) {
            $menu->addDenree((new MenuDenree())
                ->setDenree($denree)
                ->setConditionnement($unite)
                ->setRegime($regime));
        }
        $groupe = (new Groupe())
            ->setNom('Unité test')
            ->setType('farfadets')
            ->setNombreVegetariens(2)
            ->setNombreSansGluten(0);

        $service = new ListeCoursesPdf('/tmp', new AffichageQuantite());
        $methode = new \ReflectionMethod($service, 'fiche');
        $fiche = $methode->invoke($service, $menu, $menu->getLibelle(), [$menu], $groupe, 'FARFADETS', 12, '#000');
        self::assertIsArray($fiche);
        $noms = array_column($fiche['lignes'], 'nom');

        self::assertContains('Protéines', $noms);
        self::assertContains('Protéines — Végétarien (2 pers.)', $noms);
        self::assertNotContains('Protéines — Sans gluten (0 pers.)', $noms);
    }

    public function testLaFicheSpecialeConserveLaDateDuRepasPlanifie(): void
    {
        $menuPlanifie = (new Menu())
            ->setDateMenu(new \DateTimeImmutable('2026-07-10'));
        $service = new ListeCoursesPdf('/tmp', new AffichageQuantite());
        $methode = new \ReflectionMethod($service, 'titre');

        self::assertSame('Vendredi 10/07 - pique-nique 1', $methode->invoke($service, $menuPlanifie, 'Pique-nique 1'));
    }
}
