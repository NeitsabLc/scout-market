<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Denree;
use App\Entity\Fournisseur;
use App\Entity\ReferenceFournisseur;
use App\Entity\Unite;
use PHPUnit\Framework\TestCase;

final class ReferenceFournisseurTest extends TestCase
{
    public function testUneReferencePeutEtreDefinieCommeFournisseurPrincipal(): void
    {
        $unite = new Unite('pièce', 'pc');
        $denree = (new Denree())->setNom('Navet')->setUniteReference($unite)->setUniteInventaire($unite);
        $reference = new ReferenceFournisseur(new Fournisseur('St Prim'), $denree, null);

        self::assertFalse($reference->isPrincipal());
        self::assertSame($reference, $reference->setPrincipal(true));
        self::assertTrue($reference->isPrincipal());
    }
}
