<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:donnees:purger',
    description: 'Applique les durées de conservation des comptes et des unités participantes.',
)]
final class PurgerDonneesExpireesCommand extends Command
{
    private const UTILISATEUR_TECHNIQUE = 'saisie-consommation@scout-market.local';

    public function __construct(private readonly Connection $connexion)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('date', null, InputOption::VALUE_REQUIRED, 'Date de référence au format AAAA-MM-JJ (tests et reprise contrôlée).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $dateReference = $this->dateReference($input->getOption('date'));
        } catch (\InvalidArgumentException $exception) {
            $output->writeln('<error>'.$exception->getMessage().'</error>');

            return Command::INVALID;
        }

        $finAnneeScolaire = $this->derniereFinAnneeScolaire($dateReference);
        $limiteComptes = $dateReference->modify('-1 month');

        $resultats = $this->connexion->transactional(function (Connection $connexion) use ($finAnneeScolaire, $limiteComptes, $dateReference): array {
            $parametresGroupes = [
                'fin_annee_scolaire' => $finAnneeScolaire->format('Y-m-d'),
                'maintenant' => $dateReference->format('Y-m-d H:i:sP'),
            ];
            $groupesExpires = <<<'SQL'
                SELECT id
                FROM scout_market.groupe
                WHERE date_fin_presence <= :fin_annee_scolaire
                SQL;

            $comptesDesactives = $connexion->executeStatement(
                "UPDATE scout_market.utilisateur
                 SET actif = FALSE,
                     desactive_at = COALESCE(desactive_at, :maintenant),
                     groupe_id = NULL,
                     jeton_reinitialisation = NULL,
                     expiration_jeton_reinitialisation = NULL,
                     updated_at = :maintenant
                 WHERE groupe_id IN ($groupesExpires)",
                $parametresGroupes,
            );
            $connexion->executeStatement(
                "UPDATE scout_market.mouvement_stock SET groupe_id = NULL WHERE groupe_id IN ($groupesExpires)",
                $parametresGroupes,
            );
            $groupesSupprimes = $connexion->executeStatement(
                "DELETE FROM scout_market.groupe WHERE id IN ($groupesExpires)",
                $parametresGroupes,
            );

            $utilisateurTechnique = $connexion->fetchOne(
                'SELECT id FROM scout_market.utilisateur WHERE email = :email',
                ['email' => self::UTILISATEUR_TECHNIQUE],
            );
            if (false === $utilisateurTechnique) {
                throw new \RuntimeException('Le compte technique requis pour préserver les mouvements est introuvable.');
            }

            $parametresComptes = [
                'limite' => $limiteComptes->format('Y-m-d H:i:sP'),
                'utilisateur_technique' => $utilisateurTechnique,
            ];
            $comptesExpires = <<<'SQL'
                SELECT id
                FROM scout_market.utilisateur
                WHERE actif = FALSE
                  AND desactive_at <= :limite
                  AND id <> :utilisateur_technique
                SQL;

            $connexion->executeStatement(
                "UPDATE scout_market.mouvement_stock
                 SET utilisateur_id = :utilisateur_technique
                 WHERE utilisateur_id IN ($comptesExpires)",
                $parametresComptes,
            );
            $connexion->executeStatement(
                "UPDATE scout_market.mouvement_stock
                 SET annule_par_id = NULL
                 WHERE annule_par_id IN ($comptesExpires)",
                $parametresComptes,
            );
            $comptesSupprimes = $connexion->executeStatement(
                "DELETE FROM scout_market.utilisateur WHERE id IN ($comptesExpires)",
                $parametresComptes,
            );

            return [
                'groupes' => $groupesSupprimes,
                'comptes_desactives' => $comptesDesactives,
                'comptes_supprimes' => $comptesSupprimes,
            ];
        });

        $output->writeln(sprintf(
            '<info>Purge terminée : %d unité(s) supprimée(s), %d compte(s) d’unité désactivé(s), %d compte(s) supprimé(s).</info>',
            $resultats['groupes'],
            $resultats['comptes_desactives'],
            $resultats['comptes_supprimes'],
        ));

        return Command::SUCCESS;
    }

    private function dateReference(mixed $valeur): \DateTimeImmutable
    {
        if (null === $valeur) {
            return new \DateTimeImmutable();
        }
        if (!is_string($valeur)) {
            throw new \InvalidArgumentException('La date de référence est invalide.');
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);
        if (false === $date || $date->format('Y-m-d') !== $valeur) {
            throw new \InvalidArgumentException('La date de référence doit respecter le format AAAA-MM-JJ.');
        }

        return $date;
    }

    private function derniereFinAnneeScolaire(\DateTimeImmutable $dateReference): \DateTimeImmutable
    {
        $annee = (int) $dateReference->format('Y');
        if ($dateReference->format('m-d') <= '08-31') {
            --$annee;
        }

        return new \DateTimeImmutable(sprintf('%d-08-31', $annee));
    }
}
