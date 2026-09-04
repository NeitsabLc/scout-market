<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:securite:purger-jetons-expires',
    description: 'Supprime les jetons de réinitialisation de mot de passe arrivés à expiration.',
)]
final class PurgerJetonsReinitialisationExpiresCommand extends Command
{
    public function __construct(private readonly Connection $connexion)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $jetonsPurges = $this->connexion->executeStatement(
            <<<'SQL'
                UPDATE scout_market.utilisateur
                SET jeton_reinitialisation = NULL,
                    expiration_jeton_reinitialisation = NULL,
                    updated_at = CURRENT_TIMESTAMP
                WHERE expiration_jeton_reinitialisation < CURRENT_TIMESTAMP
                  AND (jeton_reinitialisation IS NOT NULL OR expiration_jeton_reinitialisation IS NOT NULL)
                SQL,
        );

        $output->writeln(sprintf('<info>Maintenance terminée : %d jeton(s) expiré(s) purgé(s).</info>', $jetonsPurges));

        return Command::SUCCESS;
    }
}
