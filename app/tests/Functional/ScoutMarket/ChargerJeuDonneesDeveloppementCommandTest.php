<?php

declare(strict_types=1);

namespace App\Tests\Functional\ScoutMarket;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ChargerJeuDonneesDeveloppementCommandTest extends KernelTestCase
{
    private const MENU_PETIT_DEJEUNER = '60000000-0000-7000-8000-000000000001';

    public function testLesMenusDuJeuSontDatesDuJourDeParisQuelQueSoitLeFuseauDeLaSession(): void
    {
        self::bootKernel();
        $connexion = static::getContainer()->get(Connection::class);
        $aujourdhuiParis = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')))->format('Y-m-d');
        $fuseauSession = $this->fuseauDontLaDateDiffereDeParis($connexion, $aujourdhuiParis);

        try {
            $connexion->executeStatement(sprintf("SET TIME ZONE '%s'", $fuseauSession));

            $application = new Application(self::$kernel);
            $testeur = new CommandTester($application->find('app:dev:charger-jeu-donnees'));
            $code = $testeur->execute([]);

            self::assertSame(Command::SUCCESS, $code);
            self::assertSame(
                $aujourdhuiParis,
                $connexion->fetchOne(
                    'SELECT date_menu::text FROM scout_market.menu WHERE id = :id',
                    ['id' => self::MENU_PETIT_DEJEUNER],
                ),
            );
            // La correction doit rester limitée à la transaction de chargement.
            self::assertSame($fuseauSession, $connexion->fetchOne('SHOW TIME ZONE'));
        } finally {
            $connexion->executeStatement('RESET TIME ZONE');
        }
    }

    /**
     * L'un des deux décalages extrêmes donne toujours une date calendaire différente de celle de Paris.
     */
    private function fuseauDontLaDateDiffereDeParis(Connection $connexion, string $aujourdhuiParis): string
    {
        foreach (['Etc/GMT-14', 'Etc/GMT+12'] as $fuseau) {
            $date = $connexion->fetchOne(
                'SELECT (CURRENT_TIMESTAMP AT TIME ZONE :fuseau)::date::text',
                ['fuseau' => $fuseau],
            );
            if ($date !== $aujourdhuiParis) {
                return $fuseau;
            }
        }

        self::fail('Aucun fuseau extrême ne diffère de la date de Paris.');
    }
}
