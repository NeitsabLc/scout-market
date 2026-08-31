<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Denree;
use App\Entity\Fournisseur;
use App\Entity\ReferenceFournisseur;
use App\Entity\Unite;
use App\Service\RegroupementCommandeFournisseur;
use PHPUnit\Framework\TestCase;

final class RegroupementCommandeFournisseurTest extends TestCase
{
    public function testElleClasseUneDenreeChezSonFournisseurPrincipalSansLaDupliquer(): void
    {
        $kilogramme = new Unite('kilogramme', 'kg');
        $denree = (new Denree())->setNom('Lait UHT')->setUniteReference($kilogramme)->setUniteInventaire($kilogramme);
        $metro = new ReferenceFournisseur(new Fournisseur('Métro'), $denree, 'METRO-123');
        $proAPro = (new ReferenceFournisseur(new Fournisseur('Pro à pro'), $denree, 'PRO-456'))->setPrincipal(true);
        $commande = [[
            'denree' => $denree,
            'besoin' => 12.0,
            'stock_previsionnel' => 4.0,
            'quantite_commande' => 8.0,
            'unite' => $kilogramme,
        ]];

        $groupes = (new RegroupementCommandeFournisseur())->regrouper($commande, [$metro, $proAPro]);

        self::assertCount(1, $groupes);
        self::assertSame('Pro à pro', $groupes[0]['nom']);
        self::assertSame('fournisseur', $groupes[0]['type']);
        self::assertCount(1, $groupes[0]['lignes']);
        self::assertSame([$proAPro->getFournisseur()], $groupes[0]['lignes'][0]['fournisseurs']);
        self::assertSame(['PRO-456'], $groupes[0]['lignes'][0]['references_produit']);
    }

    public function testElleIsoleLesDenreesSansFournisseur(): void
    {
        $piece = new Unite('pièce', 'pc');
        $denree = (new Denree())->setNom('Produit local')->setUniteReference($piece)->setUniteInventaire($piece);

        $groupes = (new RegroupementCommandeFournisseur())->regrouper([[
            'denree' => $denree,
            'besoin' => 2.0,
            'stock_previsionnel' => 0.0,
            'quantite_commande' => 2.0,
            'unite' => $piece,
        ]], []);

        self::assertSame('Sans fournisseur', $groupes[0]['nom']);
        self::assertSame('sans_fournisseur', $groupes[0]['type']);
        self::assertSame([], $groupes[0]['lignes'][0]['references_produit']);
    }
}
