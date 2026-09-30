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
    description: 'Applique les durées de conservation des comptes, audits, fournisseurs et unités participantes.',
)]
final class PurgerDonneesExpireesCommand extends Command
{
    private const UTILISATEUR_TECHNIQUE = 'saisie-consommation@scout-market.local';
    private const LIBELLE_UTILISATEUR_ANONYMISE = 'Utilisateur anonymisé';

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
        $limiteAudits = $dateReference->modify('-1 year');
        $limiteCoordonneesFournisseurs = $dateReference->modify('-2 years');

        /**
         * @return array{
         *     groupes: int,
         *     comptes_desactives: int,
         *     comptes_supprimes: int,
         *     audits_anonymises: int,
         *     fournisseurs_anonymises: int
         * }
         */
        $appliquerPurge = function (Connection $connexion) use ($finAnneeScolaire, $limiteComptes, $limiteAudits, $limiteCoordonneesFournisseurs, $dateReference): array {
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

            $auditsAnonymisesAvecCompte = $connexion->executeStatement(
                "UPDATE scout_market.audit_mouvement_stock
                 SET utilisateur_id = NULL,
                     utilisateur_libelle = :libelle_anonyme,
                     etat_avant = etat_avant #- '{mouvement,utilisateur_id}' #- '{mouvement,annule_par_id}',
                     etat_apres = etat_apres #- '{mouvement,utilisateur_id}' #- '{mouvement,annule_par_id}'
                 WHERE utilisateur_id IN ($comptesExpires)",
                $parametresComptes + ['libelle_anonyme' => self::LIBELLE_UTILISATEUR_ANONYMISE],
            );

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

            $auditsAnonymisesParAge = $connexion->executeStatement(
                <<<'SQL'
                    UPDATE scout_market.audit_mouvement_stock
                    SET utilisateur_id = NULL,
                        utilisateur_libelle = :libelle_anonyme,
                        etat_avant = etat_avant #- '{mouvement,utilisateur_id}' #- '{mouvement,annule_par_id}',
                        etat_apres = etat_apres #- '{mouvement,utilisateur_id}' #- '{mouvement,annule_par_id}'
                    WHERE created_at <= :limite
                      AND (
                          utilisateur_id IS NOT NULL
                          OR utilisateur_libelle <> :libelle_anonyme
                          OR etat_avant #> '{mouvement,utilisateur_id}' IS NOT NULL
                          OR etat_avant #> '{mouvement,annule_par_id}' IS NOT NULL
                          OR etat_apres #> '{mouvement,utilisateur_id}' IS NOT NULL
                          OR etat_apres #> '{mouvement,annule_par_id}' IS NOT NULL
                      )
                    SQL,
                [
                    'libelle_anonyme' => self::LIBELLE_UTILISATEUR_ANONYMISE,
                    'limite' => $limiteAudits->format('Y-m-d H:i:sP'),
                ],
            );

            $fournisseursAnonymises = $connexion->executeStatement(
                <<<'SQL'
                    UPDATE scout_market.fournisseur
                    SET telephone = NULL,
                        email = NULL,
                        adresse = NULL,
                        updated_at = :maintenant
                    WHERE actif = FALSE
                      AND updated_at <= :limite
                      AND (telephone IS NOT NULL OR email IS NOT NULL OR adresse IS NOT NULL)
                    SQL,
                [
                    'limite' => $limiteCoordonneesFournisseurs->format('Y-m-d H:i:sP'),
                    'maintenant' => $dateReference->format('Y-m-d H:i:sP'),
                ],
            );

            return [
                'groupes' => $groupesSupprimes,
                'comptes_desactives' => $comptesDesactives,
                'comptes_supprimes' => $comptesSupprimes,
                'audits_anonymises' => $auditsAnonymisesAvecCompte + $auditsAnonymisesParAge,
                'fournisseurs_anonymises' => $fournisseursAnonymises,
            ];
        };

        $this->connexion->beginTransaction();
        try {
            $resultats = $appliquerPurge($this->connexion);
            $this->connexion->commit();
        } catch (\Throwable $exception) {
            if ($this->connexion->isTransactionActive()) {
                $this->connexion->rollBack();
            }

            throw $exception;
        }

        $output->writeln(sprintf(
            '<info>Purge terminée : %d unité(s) supprimée(s), %d compte(s) d’unité désactivé(s), %d compte(s) supprimé(s), %d audit(s) anonymisé(s), %d fournisseur(s) sans coordonnées.</info>',
            $resultats['groupes'],
            $resultats['comptes_desactives'],
            $resultats['comptes_supprimes'],
            $resultats['audits_anonymises'],
            $resultats['fournisseurs_anonymises'],
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
