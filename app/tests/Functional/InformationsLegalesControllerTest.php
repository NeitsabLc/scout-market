<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class InformationsLegalesControllerTest extends WebTestCase
{
    public function testLaPolitiqueDeConfidentialiteEstPubliqueEtDocumenteLaConservation(): void
    {
        $client = self::createClient();
        $client->request('GET', '/politique-confidentialite');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Politique de confidentialité');
        self::assertSelectorTextContains('.legal-page', 'un mois après leur désactivation');
        self::assertSelectorTextContains('.legal-page', '31 août');
        self::assertSelectorTextContains('.legal-page', 'Mouvements de stock et audits');
        self::assertSelectorExists('a[href="mailto:contact@neitsab.net"]');
    }

    public function testLesConditionsDUtilisationSontPubliques(): void
    {
        $client = self::createClient();
        $client->request('GET', '/conditions-utilisation');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Conditions d’utilisation');
        self::assertSelectorExists('a[href="/politique-confidentialite"]');
    }
}
