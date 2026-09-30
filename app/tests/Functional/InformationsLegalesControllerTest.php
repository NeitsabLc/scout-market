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
        self::assertSelectorTextContains('.legal-page', '30 septembre 2026');
        self::assertSelectorTextContains('.legal-page', 'identification juridique complète du responsable du traitement est en cours de formalisation');
        self::assertSelectorTextContains('.legal-page', 'un mois après leur désactivation');
        self::assertSelectorTextContains('.legal-page', '31 août');
        self::assertSelectorTextContains('.legal-page', 'nom de l’auteur conservé au maximum un an');
        self::assertSelectorTextContains('.legal-page', 'deux ans d’inactivité');
        self::assertSelectorExists('a[href="mailto:contact@neitsab.net"]');
    }

    public function testLesConditionsDUtilisationSontPubliques(): void
    {
        $client = self::createClient();
        $client->request('GET', '/conditions-utilisation');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Conditions d’utilisation');
        self::assertSelectorTextContains('.legal-page', 'lien ou d’un QR code');
        self::assertSelectorTextContains('.legal-page', 'licence Apache 2.0');
        self::assertSelectorExists('a[href="/politique-confidentialite"]');
    }
}
