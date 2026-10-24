<?php

declare(strict_types=1);

namespace App\Enum;

enum TypeDenree: string
{
    case SEC = 'SEC';
    case FRUITS_LEGUMES = 'FRUITS_LEGUMES';
    case FRAIS = 'FRAIS';
    case HYGIENE = 'HYGIENE';

    public function libelle(): string
    {
        return match ($this) {
            self::SEC => 'Produit sec',
            self::FRUITS_LEGUMES => 'Fruit et légume',
            self::FRAIS => 'Produit frais',
            self::HYGIENE => 'Hygiène',
        };
    }

    /**
     * Produits conservables livrés en une fois sur la période en caisse.
     */
    public function estLivreSurLaPeriode(): bool
    {
        return self::SEC === $this || self::HYGIENE === $this;
    }

    /** @return array<string, string> */
    public static function choix(): array
    {
        $choix = [];
        foreach (self::cases() as $type) {
            $choix[$type->value] = $type->libelle();
        }

        return $choix;
    }
}
