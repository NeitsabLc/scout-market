<?php

declare(strict_types=1);

namespace App\Service;

final class DescriptionRecetteSanitizer
{
    /** @var list<string> */
    private const BALISES_AUTORISEES = ['p', 'br', 'ul', 'ol', 'li', 'strong', 'em'];

    /** @var array<string, string> */
    private const BALISES_EQUIVALENTES = ['div' => 'p', 'b' => 'strong', 'i' => 'em'];

    /** @var list<string> */
    private const BALISES_SUPPRIMEES_AVEC_CONTENU = [
        'script',
        'style',
        'iframe',
        'object',
        'embed',
        'svg',
        'math',
        'form',
        'input',
        'button',
    ];

    public function nettoyer(string $html): ?string
    {
        if ('' === trim($html)) {
            return null;
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        $etatErreurs = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'.$html.'</body></html>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($etatErreurs);

        $corps = $document->getElementsByTagName('body')->item(0);
        if (!$corps instanceof \DOMElement) {
            return null;
        }

        $documentNettoye = new \DOMDocument('1.0', 'UTF-8');
        $conteneur = $documentNettoye->createElement('div');
        $documentNettoye->appendChild($conteneur);

        foreach (iterator_to_array($corps->childNodes) as $noeud) {
            $this->copierNoeudNettoye($noeud, $conteneur, $documentNettoye);
        }

        $resultat = '';
        foreach ($conteneur->childNodes as $noeud) {
            $resultat .= $documentNettoye->saveHTML($noeud);
        }

        $resultat = trim($resultat);

        return '' === trim(strip_tags($resultat)) && !str_contains($resultat, '<br') ? null : $resultat;
    }

    private function copierNoeudNettoye(\DOMNode $source, \DOMNode $destination, \DOMDocument $document): void
    {
        if ($source instanceof \DOMText) {
            $destination->appendChild($document->createTextNode($source->data));

            return;
        }

        if (!$source instanceof \DOMElement) {
            return;
        }

        $nom = strtolower($source->tagName);
        if (in_array($nom, self::BALISES_SUPPRIMEES_AVEC_CONTENU, true)) {
            return;
        }

        $nom = self::BALISES_EQUIVALENTES[$nom] ?? $nom;
        if (in_array($nom, self::BALISES_AUTORISEES, true)) {
            $element = $document->createElement($nom);
            $destination->appendChild($element);
            $destination = $element;
        }

        foreach (iterator_to_array($source->childNodes) as $enfant) {
            $this->copierNoeudNettoye($enfant, $destination, $document);
        }
    }
}
