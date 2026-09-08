<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Fournisseur;
use PHPUnit\Framework\TestCase;

final class FournisseurTest extends TestCase
{
    public function testLAdresseEstLimiteeCoteMetier(): void
    {
        $fournisseur = new Fournisseur('Fournisseur');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('1 000 caractères');
        $fournisseur->setAdresse(str_repeat('a', Fournisseur::ADRESSE_LONGUEUR_MAX + 1));
    }
}
