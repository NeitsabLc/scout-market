<?php

declare(strict_types=1);

namespace App\Tests\Functional\Securite;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CreerAdministrateurCommandTest extends KernelTestCase
{
    public function testLaCommandeCreeUnAdministrateurAvecUnMotDePasseValide(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $application = new Application(self::$kernel);
        $commande = $application->find('app:utilisateur:creer-administrateur');
        $testeur = new CommandTester($commande);
        $email = 'premier-admin-'.bin2hex(random_bytes(5)).'@example.test';
        $testeur->setInputs(['MotDePasse?2026']);

        $code = $testeur->execute([
            'email' => strtoupper($email),
            'prenom' => 'Premier',
            'nom' => 'Administrateur',
        ]);

        self::assertSame(Command::SUCCESS, $code);
        $utilisateur = $container->get(UtilisateurRepository::class)->loadUserByIdentifier($email);
        self::assertInstanceOf(Utilisateur::class, $utilisateur);
        self::assertSame([Utilisateur::ROLE_ADMIN], $utilisateur->getRoles());
        self::assertTrue($container->get(UserPasswordHasherInterface::class)->isPasswordValid($utilisateur, 'MotDePasse?2026'));

        $container->get(EntityManagerInterface::class)->remove($utilisateur);
        $container->get(EntityManagerInterface::class)->flush();
    }

    public function testLaCommandeRefuseUneAdresseDejaUtilisee(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);
        $commande = $application->find('app:utilisateur:creer-administrateur');
        $testeur = new CommandTester($commande);

        $code = $testeur->execute([
            'email' => 'admin@scout-market.local',
            'prenom' => 'Autre',
            'nom' => 'Administrateur',
        ]);

        self::assertSame(Command::FAILURE, $code);
        self::assertStringContainsString('possède déjà cette adresse', $testeur->getDisplay());
    }
}
