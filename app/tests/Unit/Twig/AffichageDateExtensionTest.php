<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Twig\AffichageDateExtension;
use PHPUnit\Framework\TestCase;

final class AffichageDateExtensionTest extends TestCase
{
    public function testLaDateAfficheLeJourEtLeMoisEnFrancais(): void
    {
        self::assertSame(
            'Vendredi 28 août',
            (new AffichageDateExtension())->jourEtMois(new \DateTimeImmutable('2026-08-28')),
        );
    }
}
