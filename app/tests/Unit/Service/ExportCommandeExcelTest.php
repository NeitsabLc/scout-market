<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Denree;
use App\Entity\Fournisseur;
use App\Entity\Groupe;
use App\Entity\Unite;
use App\Service\ExportCommandeExcel;
use PHPUnit\Framework\TestCase;

final class ExportCommandeExcelTest extends TestCase
{
    public function testElleCreeUnClasseurParFournisseurAvecLesInformationsDeCommande(): void
    {
        $kilogramme = new Unite('kilogramme', 'kg');
        $metro = new Fournisseur('Métro & fils');
        $denree = (new Denree())
            ->setNom('Pommes <bio>')
            ->setUniteReference($kilogramme)
            ->setUniteInventaire($kilogramme);
        $groupe = static fn (string $nom, string $reference): array => [
            'nom' => $nom,
            'type' => 'fournisseur',
            'lignes' => [[
                'denree' => $denree,
                'besoin' => 12.5,
                'stock_previsionnel' => -1.25,
                'quantite_commande' => 13.75,
                'unite' => $kilogramme,
                'fournisseurs' => [$metro],
                'references_produit' => [$reference],
            ]],
        ];

        $contenu = (new ExportCommandeExcel())->creerArchive([
            $groupe('Métro & fils', 'REF&123'),
            $groupe('Pro à pro', 'PRO-456'),
        ]);

        $archive = $this->ouvrirZip($contenu);
        try {
            self::assertSame(2, $archive->numFiles);
            self::assertNotFalse($archive->locateName('commande-metro-fils.xlsx'));
            self::assertNotFalse($archive->locateName('commande-pro-a-pro.xlsx'));
            $classeur = $archive->getFromName('commande-metro-fils.xlsx');
            self::assertIsString($classeur);
        } finally {
            $archive->close();
        }

        $xlsx = $this->ouvrirZip($classeur);
        try {
            self::assertNotFalse($xlsx->locateName('[Content_Types].xml'));
            $feuille = $xlsx->getFromName('xl/worksheets/sheet1.xml');
            self::assertIsString($feuille);
            self::assertStringContainsString('Libellé', $feuille);
            self::assertStringContainsString('Quantité nécessaire', $feuille);
            self::assertStringContainsString('Stock prévu', $feuille);
            self::assertStringContainsString('Quantité à commander', $feuille);
            self::assertStringContainsString('Conditionnement', $feuille);
            self::assertStringContainsString('Pommes &lt;bio&gt;', $feuille);
            self::assertStringContainsString('REF&amp;123', $feuille);
            self::assertStringContainsString('<v>12.5</v>', $feuille);
            self::assertStringContainsString('<v>-1.25</v>', $feuille);
            self::assertStringContainsString('<v>13.75</v>', $feuille);
            self::assertStringContainsString('kilogramme (kg)', $feuille);
        } finally {
            $xlsx->close();
        }
    }

    public function testElleRefuseUneCommandeVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La commande à exporter est vide.');

        (new ExportCommandeExcel())->creerArchive([]);
    }

    public function testElleVentileLaCommandeBoulangerParJourEtParUnite(): void
    {
        $piece = new Unite('pièce', 'pc');
        $boulanger = new Fournisseur('Boulanger');
        $alpha = (new Groupe())->setNom('Alpha');
        $bravo = (new Groupe())->setNom('Bravo');
        $pain = (new Denree())->setNom('Pain')->setUniteReference($piece)->setUniteInventaire($piece);
        $brioche = (new Denree())->setNom('Brioche')->setUniteReference($piece)->setUniteInventaire($piece);
        $ligne = static fn (Denree $denree, string $reference, array $jours): array => [
            'denree' => $denree,
            'besoin' => 0.0,
            'stock_previsionnel' => 0.0,
            'quantite_commande' => 0.0,
            'unite' => $piece,
            'quantites_journalieres' => $jours,
            'fournisseurs' => [$boulanger],
            'references_produit' => [$reference],
        ];
        $jour = static fn (string $date, array $unites): array => [
            'date' => new \DateTimeImmutable($date),
            'unites' => $unites,
        ];
        $quantite = static fn (Groupe $groupe, float $valeur): array => ['groupe' => $groupe, 'quantite' => $valeur];

        $contenu = (new ExportCommandeExcel())->creerArchive([[
            'nom' => 'Boulanger',
            'type' => 'fournisseur',
            'lignes' => [
                $ligne($pain, 'PAIN', [
                    $jour('2026-08-14', [$quantite($alpha, 4.0), $quantite($bravo, 6.0)]),
                    $jour('2026-08-15', [$quantite($alpha, 5.0)]),
                ]),
                $ligne($brioche, 'BRIOCHE', [
                    $jour('2026-08-14', [$quantite($bravo, 3.0)]),
                ]),
            ],
        ]]);

        $archive = $this->ouvrirZip($contenu);
        try {
            $classeur = $archive->getFromName('commande-boulanger.xlsx');
            self::assertIsString($classeur);
        } finally {
            $archive->close();
        }
        $xlsx = $this->ouvrirZip($classeur);
        try {
            $feuille = $xlsx->getFromName('xl/worksheets/sheet1.xml');
            self::assertIsString($feuille);
            self::assertStringContainsString('Commande du 14/08/2026', $feuille);
            self::assertStringContainsString('Commande du 15/08/2026', $feuille);
            self::assertStringContainsString('Alpha', $feuille);
            self::assertStringContainsString('Bravo', $feuille);
            self::assertStringContainsString('<mergeCell ref="A1:F1"/>', $feuille);
            self::assertStringContainsString('<c r="D4" s="2"><v>4</v></c>', $feuille);
            self::assertStringContainsString('<c r="E4" s="2"><v>6</v></c>', $feuille);
            self::assertStringContainsString('<c r="F4" s="2"><v>10</v></c>', $feuille);
            self::assertStringContainsString('<c r="D8" s="2"><v>5</v></c>', $feuille);
            self::assertStringContainsString('<c r="E8" s="2"><v>0</v></c>', $feuille);
            self::assertStringContainsString('<c r="F8" s="2"><v>5</v></c>', $feuille);
        } finally {
            $xlsx->close();
        }
    }

    private function ouvrirZip(string $contenu): \ZipArchive
    {
        $chemin = tempnam(sys_get_temp_dir(), 'test-export-commandes-');
        self::assertNotFalse($chemin);
        self::assertNotFalse(file_put_contents($chemin, $contenu));
        $archive = new \ZipArchive();
        self::assertTrue($archive->open($chemin));
        @unlink($chemin);

        return $archive;
    }
}
