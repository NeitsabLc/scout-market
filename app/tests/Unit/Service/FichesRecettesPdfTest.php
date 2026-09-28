<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Denree;
use App\Entity\GrilleMenu;
use App\Entity\Groupe;
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
        self::assertStringContainsString('class="recipe-heading"', $html);
        self::assertStringContainsString('class="category-slot"', $html);
        self::assertStringContainsString('<div class="ingredients">', $html);
        self::assertStringContainsString('class="ingredient-name"', $html);
        self::assertStringContainsString('.ingredient-name { margin-bottom:4px;', $html);
        self::assertStringContainsString('.quantities { min-height:16px;', $html);
        self::assertStringContainsString('class="menu-cell"', $html);
        self::assertStringContainsString('class="recipe-card-body"', $html);
        self::assertStringContainsString('.recipe-card { margin:0; padding-top:7mm; page-break-inside:avoid;', $html);
        self::assertStringContainsString('.recipe-card-body { padding:4mm;', $html);
        self::assertStringNotContainsString('class="recipes-table"', $html);
        self::assertStringNotContainsString('repas-viande.svg', $html);
        self::assertStringContainsString('border-left:5px solid #003a5d', $html);
        self::assertStringContainsString('@page { size:A4 portrait; margin:19mm 11mm 11mm; }', $html);
        self::assertStringContainsString('.overview { padding-top:5mm; }', $html);
        self::assertStringContainsString('.meal-page { padding-top:10mm; }', $html);
        self::assertStringContainsString('logo-jambville-horizontal.png', $html);
        self::assertStringContainsString('class="logo-cell"', $html);
        self::assertStringContainsString('.logo-cell { text-align:right; }', $html);
        self::assertStringContainsString('.logo { display:inline-block; width:190px; margin:0; }', $html);
        self::assertStringContainsString('footer { position:absolute; right:7mm; bottom:10mm; left:7mm;', $html);
        self::assertStringContainsString('footer span:first-child { left:0; }', $html);
        self::assertStringContainsString('footer span:last-child { right:0; text-align:right; }', $html);
        self::assertStringNotContainsString('border:1px dashed #6cbda4', $html);
        self::assertStringNotContainsString('class="accent-line" style=', $html);

        $denree->setNom('Bœuf haché');
        $htmlCarne = $methode->invoke($service, $grille, [$menu]);
        self::assertIsString($htmlCarne);
        self::assertStringContainsString('repas-viande.svg', $htmlCarne);

        $pdf = $service->generer($grille, [$menu]);
        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertGreaterThan(10_000, strlen($pdf));
    }

    public function testLesQuantitesSontFiltreesSelonLesUnitesAffectees(): void
    {
        $grille = new GrilleMenu('Stage test', new \DateTimeImmutable('2026-10-17'), new \DateTimeImmutable('2026-10-18'));
        $repas = new TypeRepas('DEJEUNER', 'Déjeuner', 20);
        $unite = new Unite('gramme', 'g');
        $denree = (new Denree())->setNom('Pâtes')->setUniteReference($unite)->setUniteInventaire($unite);
        $ligne = (new MenuDenree())
            ->setDenree($denree)
            ->setConditionnement($unite);
        foreach ([
            ['code' => 'LOUVETEAUX_JEANNETTES', 'libelle' => 'Louveteaux-Jeannettes', 'ordre' => 20, 'quantite' => '80.000'],
            ['code' => 'SCOUTS_GUIDES', 'libelle' => 'Scouts-Guides', 'ordre' => 30, 'quantite' => '100.000'],
            ['code' => 'ADULTE', 'libelle' => 'Adultes', 'ordre' => 100, 'quantite' => '120.000'],
        ] as $donnees) {
            $public = (new PublicCible())
                ->setCode($donnees['code'])
                ->setLibelle($donnees['libelle'])
                ->setOrdre($donnees['ordre']);
            $ligne->addQuantite((new MenuDenreeQuantite())
                ->setPublicCible($public)
                ->setQuantiteIndividuelle($donnees['quantite']));
        }
        $menu = (new Menu())
            ->setGrilleMenu($grille)
            ->setDateMenu(new \DateTimeImmutable('2026-10-17'))
            ->setTypeRepas($repas)
            ->addDenree($ligne);
        $groupes = [
            (new Groupe())->setType('louveteaux-jeannettes'),
            (new Groupe())->setType('adulte'),
        ];

        $service = new FichesRecettesPdf(
            dirname(__DIR__, 3),
            new AffichageQuantite(),
            new DescriptionRecetteSanitizer(),
        );
        $codesPublics = new \ReflectionMethod($service, 'codesPublics');
        $html = new \ReflectionMethod($service, 'html');

        $htmlFiltre = $html->invoke($service, $grille, [$menu], $codesPublics->invoke($service, $groupes));
        self::assertIsString($htmlFiltre);
        self::assertStringContainsString('Louveteaux-Jeannettes', $htmlFiltre);
        self::assertStringContainsString('Adultes', $htmlFiltre);
        self::assertStringNotContainsString('Scouts-Guides', $htmlFiltre);

        $htmlSansGroupe = $html->invoke($service, $grille, [$menu], $codesPublics->invoke($service, []));
        self::assertIsString($htmlSansGroupe);
        self::assertStringContainsString('Louveteaux-Jeannettes', $htmlSansGroupe);
        self::assertStringContainsString('Scouts-Guides', $htmlSansGroupe);
        self::assertStringContainsString('Adultes', $htmlSansGroupe);
    }
}
