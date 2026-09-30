<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Utilisateur;
use App\Service\AuditMouvementStock;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class AuditMouvementStockTest extends TestCase
{
    public function testLeLibelleDeLAuteurNeContientPasSonAdresseEmail(): void
    {
        $connexion = $this->createMock(Connection::class);
        $connexion->expects(self::once())
            ->method('insert')
            ->with(
                'scout_market.audit_mouvement_stock',
                self::callback(static fn (array $donnees): bool => 'Marie Dupont' === $donnees['utilisateur_libelle']
                    && !str_contains((string) $donnees['utilisateur_libelle'], '@example.test')),
                self::anything(),
            );

        $utilisateur = (new Utilisateur())
            ->setPrenom('Marie')
            ->setNom('Dupont')
            ->setEmail('marie.dupont@example.test');

        (new AuditMouvementStock($connexion))->enregistrer(
            '97000000-0000-7000-8000-000000000001',
            $utilisateur,
            AuditMouvementStock::MODIFICATION,
            'Correction de quantité',
            ['mouvement' => []],
            ['mouvement' => []],
        );
    }
}
