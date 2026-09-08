<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Utilisateur;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV7;

final class UtilisateurTest extends TestCase
{
    public function testUnIdentifiantUuidV7EstAttribueALaCreation(): void
    {
        $utilisateur = new Utilisateur();

        self::assertNotNull($utilisateur->getId());
        self::assertInstanceOf(UuidV7::class, $utilisateur->getId());
    }

    public function testUnUtilisateurNePossedeQuUnRole(): void
    {
        $utilisateur = (new Utilisateur())->setRole(Utilisateur::ROLE_ADMIN);
        $utilisateur->setRole(Utilisateur::ROLE_GESTIONNAIRE);

        self::assertSame([Utilisateur::ROLE_GESTIONNAIRE], $utilisateur->getRoles());
    }

    public function testLeJetonDeReinitialisationEstHacheExpireEtUsageUnique(): void
    {
        $utilisateur = new Utilisateur();
        $utilisateur->definirJetonReinitialisation('secret', new \DateTimeImmutable('+1 hour'));

        self::assertNotSame('secret', $utilisateur->getJetonReinitialisation());
        self::assertTrue($utilisateur->jetonReinitialisationEstValide('secret'));
        self::assertFalse($utilisateur->jetonReinitialisationEstValide('incorrect'));
        self::assertFalse($utilisateur->jetonReinitialisationEstValide('secret', new \DateTimeImmutable('+2 hours')));

        $utilisateur->effacerJetonReinitialisation();
        self::assertFalse($utilisateur->jetonReinitialisationEstValide('secret'));
    }

    public function testLaDesactivationDeclencheLeDelaiEtLaReactivationLAnnule(): void
    {
        $utilisateur = new Utilisateur();

        self::assertNull($utilisateur->getDesactiveAt());

        $utilisateur->setActif(false);
        $dateDesactivation = $utilisateur->getDesactiveAt();
        self::assertInstanceOf(\DateTimeImmutable::class, $dateDesactivation);

        $utilisateur->setActif(false);
        self::assertSame($dateDesactivation, $utilisateur->getDesactiveAt());

        $utilisateur->setActif(true);
        self::assertNull($utilisateur->getDesactiveAt());
    }
}
