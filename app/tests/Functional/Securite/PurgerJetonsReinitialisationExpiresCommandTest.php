<?php

declare(strict_types=1);

namespace App\Tests\Functional\Securite;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PurgerJetonsReinitialisationExpiresCommandTest extends KernelTestCase
{
    public function testLaCommandePurgeUniquementLesJetonsExpires(): void
    {
        self::bootKernel();
        $connexion = static::getContainer()->get(Connection::class);
        $suffixe = bin2hex(random_bytes(5));
        $emailExpire = 'jeton-expire-'.$suffixe.'@example.test';
        $emailValide = 'jeton-valide-'.$suffixe.'@example.test';

        try {
            $this->creerUtilisateurAvecJeton($connexion, $emailExpire, '-1 hour');
            $this->creerUtilisateurAvecJeton($connexion, $emailValide, '+1 hour');

            $application = new Application(self::$kernel);
            $testeur = new CommandTester($application->find('app:securite:purger-jetons-expires'));
            $code = $testeur->execute([]);

            self::assertSame(Command::SUCCESS, $code);
            self::assertNull($this->jeton($connexion, $emailExpire));
            self::assertNotNull($this->jeton($connexion, $emailValide));
            self::assertStringContainsString('jeton(s) expiré(s) purgé(s)', $testeur->getDisplay());
        } finally {
            $connexion->executeStatement(
                'DELETE FROM scout_market.utilisateur WHERE email IN (:email_expire, :email_valide)',
                ['email_expire' => $emailExpire, 'email_valide' => $emailValide],
            );
        }
    }

    private function creerUtilisateurAvecJeton(Connection $connexion, string $email, string $expiration): void
    {
        $connexion->executeStatement(
            <<<'SQL'
                INSERT INTO scout_market.utilisateur (
                    email, mot_de_passe, prenom, nom, roles, actif,
                    jeton_reinitialisation, expiration_jeton_reinitialisation
                ) VALUES (:email, :mot_de_passe, :prenom, :nom, :roles, TRUE, :jeton, :expiration)
                SQL,
            [
                'email' => $email,
                'mot_de_passe' => 'mot-de-passe-inutilise',
                'prenom' => 'Test',
                'nom' => 'Maintenance',
                'roles' => '["ROLE_GESTIONNAIRE"]',
                'jeton' => hash('sha256', $email),
                'expiration' => new \DateTimeImmutable($expiration),
            ],
            ['expiration' => 'datetime_immutable'],
        );
    }

    private function jeton(Connection $connexion, string $email): ?string
    {
        $jeton = $connexion->fetchOne(
            'SELECT jeton_reinitialisation FROM scout_market.utilisateur WHERE email = :email',
            ['email' => $email],
        );

        return false === $jeton || null === $jeton ? null : (string) $jeton;
    }
}
