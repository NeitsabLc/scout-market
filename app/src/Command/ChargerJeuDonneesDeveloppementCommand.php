<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:dev:charger-jeu-donnees',
    description: 'Recharge le jeu de données Scout Market autour de la date du jour.',
)]
final class ChargerJeuDonneesDeveloppementCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly KernelInterface $kernel,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!in_array($this->kernel->getEnvironment(), ['dev', 'test'], true)) {
            $io->error('Cette commande est réservée aux environnements de développement et de test.');

            return Command::FAILURE;
        }

        $fichier = dirname(__DIR__, 2).'/resources/dev/jeu_donnees_scout_market.sql';
        $sql = file_get_contents($fichier);
        if (false === $sql) {
            $io->error(sprintf('Le fichier de données « %s » est illisible.', $fichier));

            return Command::FAILURE;
        }

        $this->connection->transactional(static function (Connection $connection) use ($sql): void {
            $connection->executeStatement($sql);
        });

        $resume = $this->connection->fetchAssociative(
            <<<'SQL'
                SELECT
                    (SELECT count(*) FROM scout_market.fournisseur WHERE id::text LIKE '20000000-0000-7000-8000-%') AS fournisseurs,
                    (SELECT count(*) FROM scout_market.denree WHERE id::text LIKE '30000000-0000-7000-8000-%') AS denrees,
                    (SELECT count(*) FROM scout_market.recette WHERE id::text LIKE '50000000-0000-7000-8000-%') AS recettes,
                    (SELECT count(*) FROM scout_market.grille_menu WHERE id::text LIKE '10000000-0000-7000-8000-%') AS grilles,
                    (SELECT count(*) FROM scout_market.menu WHERE id::text LIKE '60000000-0000-7000-8000-%') AS menus,
                    (SELECT count(*) FROM scout_market.groupe WHERE id::text LIKE '70000000-0000-7000-8000-%') AS unites,
                    (SELECT count(*) FROM scout_market.mouvement_stock WHERE id::text LIKE '80000000-0000-7000-8000-%') AS mouvements
                SQL,
        );

        $io->success(sprintf(
            'Jeu de données actualisé pour le %s.',
            (new \DateTimeImmutable('today'))->format('d/m/Y'),
        ));
        if (false !== $resume) {
            $io->table(
                ['Fournisseurs', 'Denrées', 'Recettes', 'Grilles', 'Menus', 'Unités', 'Mouvements'],
                [[
                    $resume['fournisseurs'],
                    $resume['denrees'],
                    $resume['recettes'],
                    $resume['grilles'],
                    $resume['menus'],
                    $resume['unites'],
                    $resume['mouvements'],
                ]],
            );
        }

        return Command::SUCCESS;
    }
}
