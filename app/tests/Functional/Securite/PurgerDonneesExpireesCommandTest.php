<?php

declare(strict_types=1);

namespace App\Tests\Functional\Securite;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class PurgerDonneesExpireesCommandTest extends KernelTestCase
{
    public function testLaCommandeAppliqueLesDureesSansSupprimerMouvementsNiAudits(): void
    {
        self::bootKernel();
        $connexion = static::getContainer()->get(Connection::class);
        $suffixe = bin2hex(random_bytes(5));
        $emailExpire = 'compte-expire-'.$suffixe.'@example.test';
        $emailRecent = 'compte-recent-'.$suffixe.'@example.test';
        $groupeId = null;
        $mouvementId = null;
        $auditId = null;

        try {
            $groupeId = (string) $connexion->fetchOne(
                <<<'SQL'
                    INSERT INTO scout_market.groupe (
                        nom, type, date_debut_presence, date_fin_presence,
                        effectif_jeune, effectif_adulte
                    ) VALUES (:nom, 'scouts-guides', '2026-07-01', '2026-08-31', 18, 4)
                    RETURNING id
                    SQL,
                ['nom' => 'Unité RGPD '.$suffixe],
            );
            $utilisateurExpire = $this->creerUtilisateur($connexion, $emailExpire, '2026-07-01 00:00:00+00', $groupeId);
            $this->creerUtilisateur($connexion, $emailRecent, '2026-08-20 00:00:00+00', null);

            $mouvementId = (string) $connexion->fetchOne(
                <<<'SQL'
                    INSERT INTO scout_market.mouvement_stock (
                        utilisateur_id, groupe_id, type_mouvement_id,
                        origine_mouvement_id, date_mouvement
                    )
                    SELECT :utilisateur, :groupe, type.id, origine.id, '2026-08-01 12:00:00+00'
                    FROM scout_market.type_mouvement type
                    CROSS JOIN scout_market.origine_mouvement origine
                    WHERE type.code = 'ENTREE' AND origine.code = 'INVENTAIRE'
                    RETURNING id
                    SQL,
                ['utilisateur' => $utilisateurExpire, 'groupe' => $groupeId],
            );
            $auditId = (string) $connexion->fetchOne(
                <<<'SQL'
                    INSERT INTO scout_market.audit_mouvement_stock (
                        mouvement_stock_id, utilisateur_id, utilisateur_libelle,
                        action, motif, etat_avant, etat_apres
                    ) VALUES (:mouvement, :utilisateur, :libelle, 'MODIFICATION', 'Test RGPD', '{}'::jsonb, '{}'::jsonb)
                    RETURNING id
                    SQL,
                [
                    'mouvement' => $mouvementId,
                    'utilisateur' => $utilisateurExpire,
                    'libelle' => 'Personne Test <'.$emailExpire.'>',
                ],
            );

            $application = new Application(self::$kernel);
            $testeur = new CommandTester($application->find('app:donnees:purger'));
            $code = $testeur->execute(['--date' => '2026-09-08']);

            self::assertSame(Command::SUCCESS, $code);
            self::assertFalse($connexion->fetchOne('SELECT id FROM scout_market.groupe WHERE id = :id', ['id' => $groupeId]));
            self::assertFalse($connexion->fetchOne('SELECT id FROM scout_market.utilisateur WHERE email = :email', ['email' => $emailExpire]));
            self::assertNotFalse($connexion->fetchOne('SELECT id FROM scout_market.utilisateur WHERE email = :email', ['email' => $emailRecent]));

            $mouvement = $connexion->fetchAssociative(
                <<<'SQL'
                    SELECT mouvement.groupe_id, utilisateur.email
                    FROM scout_market.mouvement_stock mouvement
                    JOIN scout_market.utilisateur utilisateur ON utilisateur.id = mouvement.utilisateur_id
                    WHERE mouvement.id = :id
                    SQL,
                ['id' => $mouvementId],
            );
            self::assertIsArray($mouvement);
            self::assertNull($mouvement['groupe_id']);
            self::assertSame('saisie-consommation@scout-market.local', $mouvement['email']);

            $audit = $connexion->fetchAssociative(
                'SELECT utilisateur_id, utilisateur_libelle FROM scout_market.audit_mouvement_stock WHERE id = :id',
                ['id' => $auditId],
            );
            self::assertIsArray($audit);
            self::assertNull($audit['utilisateur_id']);
            self::assertStringContainsString($emailExpire, (string) $audit['utilisateur_libelle']);
            self::assertStringContainsString('1 unité(s) supprimée(s)', $testeur->getDisplay());
            self::assertStringContainsString('1 compte(s) supprimé(s)', $testeur->getDisplay());
        } finally {
            if (null !== $auditId) {
                $connexion->executeStatement('DELETE FROM scout_market.audit_mouvement_stock WHERE id = :id', ['id' => $auditId]);
            }
            if (null !== $mouvementId) {
                $connexion->executeStatement('DELETE FROM scout_market.mouvement_stock WHERE id = :id', ['id' => $mouvementId]);
            }
            $connexion->executeStatement(
                'DELETE FROM scout_market.utilisateur WHERE email IN (:expire, :recent)',
                ['expire' => $emailExpire, 'recent' => $emailRecent],
            );
            if (null !== $groupeId) {
                $connexion->executeStatement('DELETE FROM scout_market.groupe WHERE id = :id', ['id' => $groupeId]);
            }
        }
    }

    public function testLaCommandeRefuseUneDateInvalide(): void
    {
        self::bootKernel();
        $application = new Application(self::$kernel);
        $testeur = new CommandTester($application->find('app:donnees:purger'));

        self::assertSame(Command::INVALID, $testeur->execute(['--date' => '08/09/2026']));
        self::assertStringContainsString('AAAA-MM-JJ', $testeur->getDisplay());
    }

    private function creerUtilisateur(Connection $connexion, string $email, string $desactiveAt, ?string $groupeId): string
    {
        return (string) $connexion->fetchOne(
            <<<'SQL'
                INSERT INTO scout_market.utilisateur (
                    groupe_id, email, mot_de_passe, prenom, nom, roles, actif, desactive_at
                ) VALUES (:groupe, :email, 'mot-de-passe-inutilise', 'Personne', 'Test', '["ROLE_GROUPE"]'::jsonb, FALSE, :desactive_at)
                RETURNING id
                SQL,
            ['groupe' => $groupeId, 'email' => $email, 'desactive_at' => $desactiveAt],
        );
    }
}
