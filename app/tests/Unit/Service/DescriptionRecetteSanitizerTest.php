<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\DescriptionRecetteSanitizer;
use PHPUnit\Framework\TestCase;

final class DescriptionRecetteSanitizerTest extends TestCase
{
    private DescriptionRecetteSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new DescriptionRecetteSanitizer();
    }

    public function testIlConserveLaMiseEnFormeUtile(): void
    {
        self::assertSame(
            '<p>Faire <strong>cuire</strong> puis <em>servir</em>.</p><ol><li>Préparer</li><li>Cuire<br>doucement</li></ol>',
            $this->sanitizer->nettoyer('<p>Faire <strong>cuire</strong> puis <em>servir</em>.</p><ol><li>Préparer</li><li>Cuire<br>doucement</li></ol>'),
        );
    }

    public function testIlSupprimeLesBalisesEtAttributsDangereux(): void
    {
        self::assertSame(
            '<p>Préparer <strong>avec soin</strong>.</p>',
            $this->sanitizer->nettoyer('<p class="mise-en-page" onclick="attaque()">Préparer <script>alert(1)</script><strong style="color:red">avec soin</strong><img src="x" onerror="attaque()">.</p>'),
        );
    }

    public function testIlNormaliseLeHtmlProduitParUnEditeurDeTexteRiche(): void
    {
        self::assertSame(
            '<p>Première étape</p><p><strong>Deuxième</strong> étape</p>',
            $this->sanitizer->nettoyer('<div>Première étape</div><div><b>Deuxième</b> étape</div>'),
        );
    }

    public function testUneDescriptionVideDevientNulle(): void
    {
        self::assertNull($this->sanitizer->nettoyer('  '));
        self::assertNull($this->sanitizer->nettoyer('<p> </p>'));
    }
}
