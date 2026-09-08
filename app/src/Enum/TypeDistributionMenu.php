<?php

declare(strict_types=1);

namespace App\Enum;

enum TypeDistributionMenu: string
{
    case SCOUT_MARKET = 'SCOUT_MARKET';
    case EN_CAISSE = 'EN_CAISSE';

    public function libelle(): string
    {
        return match ($this) {
            self::SCOUT_MARKET => 'Scout Market',
            self::EN_CAISSE => 'En caisse',
        };
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
