<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Denree;
use App\Entity\Fournisseur;
use App\Entity\Groupe;
use App\Entity\Unite;
use Symfony\Component\String\Slugger\AsciiSlugger;

final class ExportCommandeExcel
{
    private const ENTETES = [
        'Libellé',
        'Référence',
        'Quantité nécessaire',
        'Stock prévu',
        'Quantité à commander',
        'Conditionnement',
    ];

    /**
     * @param list<array{
     *     nom: string,
     *     type: string,
     *     lignes: list<array{
     *         denree: Denree,
     *         besoin: float,
     *         stock_previsionnel: float,
     *         quantite_commande: float,
     *         unite: Unite,
     *         quantites_journalieres?: list<array{date: \DateTimeImmutable, unites: list<array{groupe: Groupe, quantite: float}>}>,
     *         fournisseurs: list<Fournisseur>,
     *         references_produit: list<string>
     *     }>
     * }> $groupes
     */
    public function creerArchive(array $groupes): string
    {
        if ([] === $groupes) {
            throw new \InvalidArgumentException('La commande à exporter est vide.');
        }

        $chemin = $this->fichierTemporaire();
        $archive = new \ZipArchive();
        if (true !== $archive->open($chemin, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
            @unlink($chemin);
            throw new \RuntimeException('Impossible de créer l’archive des commandes.');
        }

        $fermee = false;
        try {
            $nomsUtilises = [];
            foreach ($groupes as $groupe) {
                $nom = $this->nomFichier($groupe['nom'], $nomsUtilises);
                $boulanger = 'fournisseur' === $groupe['type'] && 'boulanger' === $this->slug($groupe['nom']);
                if (!$archive->addFromString($nom, $this->creerClasseur($groupe['nom'], $groupe['lignes'], $boulanger))) {
                    throw new \RuntimeException('Impossible d’ajouter un classeur à l’archive.');
                }
            }

            if (!$archive->close()) {
                throw new \RuntimeException('Impossible de finaliser l’archive des commandes.');
            }
            $fermee = true;
            $contenu = file_get_contents($chemin);
            if (false === $contenu) {
                throw new \RuntimeException('Impossible de lire l’archive des commandes.');
            }

            return $contenu;
        } finally {
            if (!$fermee) {
                $archive->close();
            }
            @unlink($chemin);
        }
    }

    /**
     * @param list<array{
     *     denree: Denree,
     *     besoin: float,
     *     stock_previsionnel: float,
     *     quantite_commande: float,
     *     unite: Unite,
     *     quantites_journalieres?: list<array{date: \DateTimeImmutable, unites: list<array{groupe: Groupe, quantite: float}>}>,
     *     fournisseurs: list<Fournisseur>,
     *     references_produit: list<string>
     * }> $lignes
     */
    private function creerClasseur(string $fournisseur, array $lignes, bool $boulanger): string
    {
        $chemin = $this->fichierTemporaire();
        $classeur = new \ZipArchive();
        if (true !== $classeur->open($chemin, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
            @unlink($chemin);
            throw new \RuntimeException('Impossible de créer un classeur de commande.');
        }

        $ferme = false;
        try {
            $fichiers = [
                '[Content_Types].xml' => $this->typesContenu(),
                '_rels/.rels' => $this->relationsRacine(),
                'docProps/app.xml' => $this->proprietesApplication(),
                'docProps/core.xml' => $this->proprietesDocument($fournisseur),
                'xl/workbook.xml' => $this->classeurXml($fournisseur),
                'xl/_rels/workbook.xml.rels' => $this->relationsClasseur(),
                'xl/styles.xml' => $this->styles(),
                'xl/worksheets/sheet1.xml' => $boulanger ? $this->feuilleBoulanger($lignes) : $this->feuille($lignes),
            ];
            foreach ($fichiers as $nom => $contenu) {
                if (!$classeur->addFromString($nom, $contenu)) {
                    throw new \RuntimeException('Impossible de construire un classeur de commande.');
                }
            }

            if (!$classeur->close()) {
                throw new \RuntimeException('Impossible de finaliser un classeur de commande.');
            }
            $ferme = true;
            $contenu = file_get_contents($chemin);
            if (false === $contenu) {
                throw new \RuntimeException('Impossible de lire un classeur de commande.');
            }

            return $contenu;
        } finally {
            if (!$ferme) {
                $classeur->close();
            }
            @unlink($chemin);
        }
    }

    /**
     * @param list<array{
     *     denree: Denree,
     *     besoin: float,
     *     stock_previsionnel: float,
     *     quantite_commande: float,
     *     unite: Unite,
     *     fournisseurs: list<Fournisseur>,
     *     references_produit: list<string>
     * }> $lignes
     */
    private function feuille(array $lignes): string
    {
        $lignesXml = [$this->ligneXml(1, self::ENTETES, true)];
        foreach ($lignes as $index => $ligne) {
            $lignesXml[] = $this->ligneXml($index + 2, [
                $ligne['denree']->getNom(),
                implode(', ', $ligne['references_produit']),
                $ligne['besoin'],
                $ligne['stock_previsionnel'],
                $ligne['quantite_commande'],
                sprintf('%s (%s)', $ligne['unite']->getNom(), $ligne['unite']->getSymbole()),
            ]);
        }
        $derniereLigne = count($lignes) + 1;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols><col min="1" max="1" width="32" customWidth="1"/><col min="2" max="2" width="22" customWidth="1"/><col min="3" max="5" width="21" customWidth="1"/><col min="6" max="6" width="25" customWidth="1"/></cols>'
            .'<sheetData>'.implode('', $lignesXml).'</sheetData>'
            .sprintf('<autoFilter ref="A1:F%d"/>', $derniereLigne)
            .'</worksheet>';
    }

    /**
     * @param list<array{
     *     denree: Denree,
     *     unite: Unite,
     *     quantites_journalieres?: list<array{date: \DateTimeImmutable, unites: list<array{groupe: Groupe, quantite: float}>}>,
     *     references_produit: list<string>
     * }> $lignes
     */
    private function feuilleBoulanger(array $lignes): string
    {
        /** @var array<string, Groupe> $unites */
        $unites = [];
        /** @var array<string, array{date: \DateTimeImmutable, lignes: list<array{ligne: array<string, mixed>, quantites: array<string, float>}>}> $jours */
        $jours = [];
        foreach ($lignes as $ligne) {
            foreach ($ligne['quantites_journalieres'] ?? [] as $jour) {
                $cleDate = $jour['date']->format('Y-m-d');
                $quantites = [];
                foreach ($jour['unites'] as $unite) {
                    $cleUnite = (string) $unite['groupe']->getId();
                    $unites[$cleUnite] = $unite['groupe'];
                    $quantites[$cleUnite] = ($quantites[$cleUnite] ?? 0.0) + $unite['quantite'];
                }
                $jours[$cleDate] ??= ['date' => $jour['date'], 'lignes' => []];
                $jours[$cleDate]['lignes'][] = ['ligne' => $ligne, 'quantites' => $quantites];
            }
        }

        uasort($unites, static fn (Groupe $a, Groupe $b): int => strnatcasecmp($a->getNom(), $b->getNom()));
        ksort($jours);
        $entetes = ['Denrée', 'Référence', 'Conditionnement'];
        foreach ($unites as $unite) {
            $entetes[] = $unite->getNom();
        }
        $entetes[] = 'Total';

        $numero = 1;
        $lignesXml = [];
        $fusions = [];
        $derniereColonne = $this->colonne(count($entetes));
        foreach ($jours as $jour) {
            if ([] !== $lignesXml) {
                $lignesXml[] = sprintf('<row r="%d"/>', $numero++);
            }
            $lignesXml[] = $this->ligneXml($numero, ['Commande du '.$jour['date']->format('d/m/Y')], true);
            $fusions[] = sprintf('<mergeCell ref="A%d:%s%d"/>', $numero, $derniereColonne, $numero);
            ++$numero;
            $lignesXml[] = $this->ligneXml($numero++, $entetes, true);

            usort($jour['lignes'], static fn (array $a, array $b): int => strnatcasecmp($a['ligne']['denree']->getNom(), $b['ligne']['denree']->getNom()));
            foreach ($jour['lignes'] as $detail) {
                $ligne = $detail['ligne'];
                $valeurs = [
                    $ligne['denree']->getNom(),
                    implode(', ', $ligne['references_produit']),
                    sprintf('%s (%s)', $ligne['unite']->getNom(), $ligne['unite']->getSymbole()),
                ];
                $total = 0.0;
                foreach ($unites as $cleUnite => $unite) {
                    $quantite = $detail['quantites'][$cleUnite] ?? 0.0;
                    $valeurs[] = $quantite;
                    $total += $quantite;
                }
                $valeurs[] = $total;
                $lignesXml[] = $this->ligneXml($numero++, $valeurs);
            }
        }

        if ([] === $jours) {
            $lignesXml[] = $this->ligneXml(1, ['Aucun détail journalier disponible.']);
        }
        $colonnesUnites = count($unites);
        $colonnes = '<col min="1" max="1" width="32" customWidth="1"/><col min="2" max="2" width="22" customWidth="1"/><col min="3" max="3" width="25" customWidth="1"/>';
        if ($colonnesUnites > 0) {
            $colonnes .= sprintf('<col min="4" max="%d" width="18" customWidth="1"/>', 3 + $colonnesUnites);
        }
        $colonnes .= sprintf('<col min="%d" max="%d" width="18" customWidth="1"/>', 4 + $colonnesUnites, 4 + $colonnesUnites);
        $fusionXml = [] === $fusions ? '' : sprintf('<mergeCells count="%d">%s</mergeCells>', count($fusions), implode('', $fusions));

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<cols>'.$colonnes.'</cols>'
            .'<sheetData>'.implode('', $lignesXml).'</sheetData>'
            .$fusionXml
            .'</worksheet>';
    }

    /** @param list<string|float> $valeurs */
    private function ligneXml(int $numero, array $valeurs, bool $entete = false): string
    {
        $cellules = '';
        foreach ($valeurs as $index => $valeur) {
            $reference = $this->colonne($index + 1).$numero;
            if (is_float($valeur)) {
                $cellules .= sprintf('<c r="%s" s="2"><v>%s</v></c>', $reference, $this->nombre($valeur));
                continue;
            }
            $style = $entete ? ' s="1"' : '';
            $cellules .= sprintf(
                '<c r="%s" t="inlineStr"%s><is><t xml:space="preserve">%s</t></is></c>',
                $reference,
                $style,
                $this->xml($valeur),
            );
        }

        return sprintf('<row r="%d">%s</row>', $numero, $cellules);
    }

    private function nombre(float $valeur): string
    {
        if (!is_finite($valeur)) {
            throw new \InvalidArgumentException('Une quantité non finie ne peut pas être exportée.');
        }

        $nombre = rtrim(rtrim(number_format($valeur, 10, '.', ''), '0'), '.');

        return '-0' === $nombre || '' === $nombre ? '0' : $nombre;
    }

    /** @param array<string, true> $nomsUtilises */
    private function nomFichier(string $fournisseur, array &$nomsUtilises): string
    {
        $base = $this->slug($fournisseur);
        $base = '' === $base ? 'sans-fournisseur' : $base;
        $nom = 'commande-'.$base;
        $suffixe = 2;
        while (isset($nomsUtilises[$nom])) {
            $nom = 'commande-'.$base.'-'.$suffixe;
            ++$suffixe;
        }
        $nomsUtilises[$nom] = true;

        return $nom.'.xlsx';
    }

    private function slug(string $valeur): string
    {
        return (new AsciiSlugger('fr'))->slug($valeur)->lower()->toString();
    }

    private function colonne(int $numero): string
    {
        $colonne = '';
        while ($numero > 0) {
            --$numero;
            $colonne = chr(65 + ($numero % 26)).$colonne;
            $numero = intdiv($numero, 26);
        }

        return $colonne;
    }

    private function nomFeuille(string $nom): string
    {
        $nom = preg_replace('/[\\\\\/\?\*\[\]:]/u', ' ', $nom) ?? '';
        $nom = trim($nom, " '\t\n\r\0\x0B");

        return $this->xml('' === $nom ? 'Commande' : mb_substr($nom, 0, 31));
    }

    private function xml(string $valeur): string
    {
        $valeur = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $valeur) ?? '';

        return htmlspecialchars(mb_substr($valeur, 0, 32767), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function fichierTemporaire(): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'scout-market-commandes-');
        if (false === $chemin) {
            throw new \RuntimeException('Impossible de créer un fichier temporaire pour l’export.');
        }

        return $chemin;
    }

    private function typesContenu(): string
    {
        return <<<'XML'
            <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>
            XML;
    }

    private function relationsRacine(): string
    {
        return <<<'XML'
            <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>
            XML;
    }

    private function proprietesApplication(): string
    {
        return <<<'XML'
            <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Scout Market</Application><AppVersion>1.0</AppVersion></Properties>
            XML;
    }

    private function proprietesDocument(string $fournisseur): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:title>Commande '.$this->xml($fournisseur).'</dc:title><dc:creator>Scout Market</dc:creator>'
            .'</cp:coreProperties>';
    }

    private function classeurXml(string $fournisseur): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$this->nomFeuille($fournisseur).'" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function relationsClasseur(): string
    {
        return <<<'XML'
            <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>
            XML;
    }

    private function styles(): string
    {
        return <<<'XML'
            <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Aptos"/><family val="2"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Aptos"/><family val="2"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF003A5D"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD8E0E5"/></left><right style="thin"><color rgb="FFD8E0E5"/></right><top style="thin"><color rgb="FFD8E0E5"/></top><bottom style="thin"><color rgb="FFD8E0E5"/></bottom><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment vertical="top"/></xf><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment vertical="center"/></xf><xf numFmtId="2" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment vertical="top"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>
            XML;
    }
}
