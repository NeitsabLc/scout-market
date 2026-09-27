<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Denree;
use App\Entity\GrilleMenu;
use App\Entity\Menu;
use App\Entity\MenuDenree;
use App\Entity\MenuDenreeQuantite;
use App\Entity\PublicCible;
use App\Entity\Recette;
use App\Entity\TypeRepas;
use App\Entity\Unite;
use App\Service\AffichageQuantite;
use App\Service\DescriptionRecetteSanitizer;
use App\Service\FichesRecettesPdf;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV7;

final class FichesRecettesPdfTest extends TestCase
{
    public function testLaPageMenuProposeLeTelechargementDesFiches(): void
    {
        $modele = file_get_contents(dirname(__DIR__, 3).'/templates/menu/index.html.twig');

        self::assertIsString($modele);
        self::assertStringContainsString("path('app_grille_menu_fiches_recettes'", $modele);
        self::assertStringContainsString('Fiches recettes PDF', $modele);
    }

    public function testLeDocumentContientLaGrilleLesQuantitesEtLaDescription(): void
    {
        $grille = new GrilleMenu('Stage test', new \DateTimeImmutable('2026-10-17'), new \DateTimeImmutable('2026-10-18'));
        $repas = new TypeRepas('DEJEUNER', 'Déjeuner', 20);
        $unite = new Unite('gramme', 'g');
        $denree = (new Denree())->setNom('Carottes')->setUniteReference($unite)->setUniteInventaire($unite);
        $public = (new PublicCible())->setCode('ADULTE')->setLibelle('Adultes')->setOrdre(100);
        $recette = (new Recette())
            ->setNom('Carottes rôties')
            ->setCategorie('PLAT')
            ->setDescription('<ol><li>Découper les carottes.</li><li>Faire cuire.</li></ol>');
        $menu = (new Menu())
            ->setGrilleMenu($grille)
            ->setDateMenu(new \DateTimeImmutable('2026-10-17'))
            ->setTypeRepas($repas)
            ->addDenree((new MenuDenree())
                ->setDenree($denree)
                ->setConditionnement($unite)
                ->setCategorie('PLAT')
                ->setRecette($recette)
                ->setRecetteInstanceId(new UuidV7())
                ->addQuantite((new MenuDenreeQuantite())
                    ->setPublicCible($public)
                    ->setQuantiteIndividuelle('120.000')));

        $service = new FichesRecettesPdf(
            dirname(__DIR__, 3),
            new AffichageQuantite(),
            new DescriptionRecetteSanitizer(),
        );
        $methode = new \ReflectionMethod($service, 'html');
        $html = $methode->invoke($service, $grille, [$menu]);
        self::assertIsString($html);
        self::assertStringContainsString('Stage test', $html);
        self::assertStringContainsString('Carottes rôties', $html);
        self::assertStringContainsString('120 g/pers.', $html);
        self::assertStringContainsString('<ol><li>Découper les carottes.</li><li>Faire cuire.</li></ol>', $html);

        $pdf = $service->generer($grille, [$menu]);
        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertGreaterThan(10_000, strlen($pdf));
    }
}
