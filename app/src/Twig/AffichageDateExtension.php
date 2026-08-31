<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class AffichageDateExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('date_jour_mois', $this->jourEtMois(...)),
        ];
    }

    public function jourEtMois(\DateTimeInterface $date): string
    {
        $formateur = new \IntlDateFormatter(
            'fr_FR',
            \IntlDateFormatter::NONE,
            \IntlDateFormatter::NONE,
            null,
            \IntlDateFormatter::GREGORIAN,
            'EEEE d MMMM',
        );
        $dateFormatee = $formateur->format($date);
        if (false === $dateFormatee) {
            throw new \LogicException('La date n’a pas pu être formatée.');
        }

        return ucfirst($dateFormatee);
    }
}
