<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Denree;
use App\Entity\GrilleMenu;
use App\Entity\Groupe;
use App\Entity\GroupeRepas;
use App\Entity\Menu;
use App\Entity\MenuDenree;
use App\Entity\MenuDenreeQuantite;
use App\Entity\PublicCible;
use App\Entity\TypeRepas;
use App\Entity\Unite;
use App\Enum\ModeRepasGroupe;
use App\Enum\RegimeAlimentaire;
use App\Service\CalculCommande;
use PHPUnit\Framework\TestCase;

final class CalculCommandeTest extends TestCase
{
    public function testChaqueUniteUtiliseSaGrillePourUnMemeRepas(): void
    {
        $date = new \DateTimeImmutable('2026-08-14');
        $farfadets = (new PublicCible())->setCode('FARFADETS')->setLibelle('Farfadets');
        $adultes = (new PublicCible())->setCode('ADULTE')->setLibelle('Adulte');
        $publics = ['FARFADETS' => $farfadets, 'ADULTE' => $adultes];
        $repas = new TypeRepas('DEJEUNER', 'Déjeuner');
        $grilleA = new GrilleMenu('Classique', $date, $date);
        $grilleB = new GrilleMenu('Alternatif', $date, $date);
        $unite = new Unite('kilogramme', 'kg');
        $riz = (new Denree())->setNom('Riz')->setUniteReference($unite);
        $pates = (new Denree())->setNom('Pâtes')->setUniteReference($unite);
        $menuA = (new Menu())->setGrilleMenu($grilleA)->setDateMenu($date)->setTypeRepas($repas)
            ->addDenree($this->ligne($riz, $unite, null, $publics, 1, 1));
        $menuB = (new Menu())->setGrilleMenu($grilleB)->setDateMenu($date)->setTypeRepas($repas)
            ->addDenree($this->ligne($pates, $unite, null, $publics, 2, 2));
        $groupeA = $this->groupe('A', $date, 3, 0, 0)->setGrilleMenu($grilleA);
        $groupeB = $this->groupe('B', $date, 4, 0, 0)->setGrilleMenu($grilleB);

        $commandes = (new CalculCommande())->calculer([$menuA, $menuB], [$groupeA, $groupeB]);

        self::assertCount(1, $commandes);
        self::assertSame(['Pâtes', 'Riz'], array_map(static fn (array $ligne): string => $ligne['denree']->getNom(), $commandes[0]['lignes']));
        self::assertSame([8.0, 3.0], array_column($commandes[0]['lignes'], 'quantite'));
        self::assertSame(['Alternatif', 'Classique'], array_map(static fn (array $grille): string => $grille['grille']->getLabel(), $commandes[0]['grilles']));
        self::assertSame(['Pâtes'], array_map(static fn (array $ligne): string => $ligne['denree']->getNom(), $commandes[0]['grilles'][0]['lignes']));
        self::assertSame([8.0], array_column($commandes[0]['grilles'][0]['lignes'], 'quantite'));
        self::assertSame(['Riz'], array_map(static fn (array $ligne): string => $ligne['denree']->getNom(), $commandes[0]['grilles'][1]['lignes']));
        self::assertSame([3.0], array_column($commandes[0]['grilles'][1]['lignes'], 'quantite'));
        self::assertSame($menuB, $commandes[0]['grilles'][0]['menu']);
        self::assertSame(['B'], array_map(static fn (array $unite): string => $unite['groupe']->getNom(), $commandes[0]['grilles'][0]['unites']));
    }

    public function testLaCommandeUtiliseLeMenuChoisiParUniteEtIgnoreLesRepasNonPris(): void
    {
        $date = new \DateTimeImmutable('2026-08-14');
        $farfadets = (new PublicCible())->setCode('FARFADETS')->setLibelle('Farfadets');
        $adultes = (new PublicCible())->setCode('ADULTE')->setLibelle('Adulte');
        $configurationsPublics = ['FARFADETS' => $farfadets, 'ADULTE' => $adultes];
        $grille = new GrilleMenu('Principale', $date, $date->modify('+2 days'));
        $repas = new TypeRepas('DEJEUNER', 'Déjeuner');

        $gramme = new Unite('gramme', 'g');
        $piece = new Unite('pièce', 'pc');
        $farine = (new Denree())->setNom('Farine')->setUniteReference($gramme);
        $tofu = (new Denree())->setNom('Tofu')->setUniteReference($gramme);
        $haricots = (new Denree())->setNom('Haricots')->setUniteReference($gramme);
        $pain = (new Denree())->setNom('Pain')->setUniteReference($piece);

        $menu = (new Menu())->setGrilleMenu($grille)->setTypeRepas($repas)->setDateMenu($date);
        $menu->addDenree($this->ligne($farine, $gramme, null, $configurationsPublics, 100, 150));
        $menu->addDenree($this->ligne($tofu, $gramme, RegimeAlimentaire::VEGETARIEN, $configurationsPublics, 80, 120));
        $explo = (new Menu())->setGrilleMenu($grille)->setSpecialCode('EXPLO');
        $explo->addDenree($this->ligne($haricots, $gramme, null, $configurationsPublics, 50, 60));
        $piqueNique = (new Menu())->setGrilleMenu($grille)->setSpecialCode('PIQUE_NIQUE_1');
        $piqueNique->addDenree($this->ligne($pain, $piece, null, $configurationsPublics, 2, 2));

        $normal = $this->groupe('Normal', $date, 10, 2, 3)->setGrilleMenu($grille);
        $enExplo = $this->groupe('Explo', $date, 5, 0, 0)->setGrilleMenu($grille);
        $enPiqueNique = $this->groupe('Pique-nique', $date, 4, 0, 0)->setGrilleMenu($grille);
        $sansRepas = $this->groupe('Non pris', $date, 20, 0, 0)->setGrilleMenu($grille);
        $absent = $this->groupe('Absent', $date->modify('+1 day'), 30, 0, 0)->setGrilleMenu($grille);
        $configurationsRepas = [
            new GroupeRepas($enExplo, $menu, ModeRepasGroupe::EXPLO),
            new GroupeRepas($enPiqueNique, $menu, ModeRepasGroupe::PIQUE_NIQUE_1),
            new GroupeRepas($sansRepas, $menu, ModeRepasGroupe::NON_PRIS),
        ];

        $commandes = (new CalculCommande())->calculer(
            [$menu, $explo, $piqueNique],
            [$normal, $enExplo, $enPiqueNique, $sansRepas, $absent],
            $configurationsRepas,
        );

        self::assertCount(1, $commandes);
        self::assertSame(['Farine', 'Haricots', 'Pain', 'Tofu'], array_map(
            static fn (array $ligne): string => $ligne['denree']->getNom(),
            $commandes[0]['lignes'],
        ));
        $quantites = array_column($commandes[0]['lignes'], 'quantite');
        self::assertSame([1300.0, 250.0, 8.0, 240.0], $quantites);
        $unites = $commandes[0]['grilles'][0]['unites'];
        self::assertSame(['Explo', 'Normal', 'Pique-nique'], array_map(static fn (array $unite): string => $unite['groupe']->getNom(), $unites));
        self::assertSame($explo, $unites[0]['source']);
        self::assertSame($menu, $unites[1]['source']);
        self::assertSame($piqueNique, $unites[2]['source']);
    }

    /** @param array<string, PublicCible> $configurations */
    private function ligne(Denree $denree, Unite $unite, ?RegimeAlimentaire $regime, array $configurations, int $jeune, int $adulte): MenuDenree
    {
        return (new MenuDenree())
            ->setDenree($denree)
            ->setConditionnement($unite)
            ->setRegime($regime)
            ->addQuantite((new MenuDenreeQuantite())->setPublicCible($configurations['FARFADETS'])->setQuantiteIndividuelle((string) $jeune))
            ->addQuantite((new MenuDenreeQuantite())->setPublicCible($configurations['ADULTE'])->setQuantiteIndividuelle((string) $adulte));
    }

    private function groupe(string $nom, \DateTimeImmutable $date, int $jeunes, int $adultes, int $vegetariens): Groupe
    {
        return (new Groupe())
            ->setNom($nom)
            ->setType('farfadets')
            ->setEffectifJeune($jeunes)
            ->setEffectifAdulte($adultes)
            ->setNombreVegetariens($vegetariens)
            ->setDateDebutPresence($date)
            ->setDateFinPresence($date);
    }
}
