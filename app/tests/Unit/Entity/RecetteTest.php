<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Recette;
use PHPUnit\Framework\TestCase;

final class RecetteTest extends TestCase
{
    public function testLaDescriptionEstOptionnelleEtModifiable(): void
    {
        $recette = new Recette();

        self::assertNull($recette->getDescription());
        self::assertSame($recette, $recette->setDescription('<p>Préparation</p>'));
        self::assertSame('<p>Préparation</p>', $recette->getDescription());
        self::assertSame($recette, $recette->setDescription(null));
        self::assertNull($recette->getDescription());
    }
}
