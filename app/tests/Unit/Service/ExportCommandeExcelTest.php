<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Denree;
use App\Entity\Fournisseur;
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
