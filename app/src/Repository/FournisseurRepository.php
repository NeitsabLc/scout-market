<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Fournisseur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Fournisseur> */
final class FournisseurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Fournisseur::class);
    }

    /** @return list<Fournisseur> */
    public function findActifs(): array
    {
        return $this->findPourGestion();
    }

    /** @return list<Fournisseur> */
    public function findPourGestion(bool $inclureInactifs = false): array
    {
        $requete = $this->createQueryBuilder('fournisseur')
            ->orderBy('fournisseur.actif', 'DESC')
            ->addOrderBy('fournisseur.nom', 'ASC');

        if (!$inclureInactifs) {
            $requete->andWhere('fournisseur.actif = true');
        }

        return $requete->getQuery()->getResult();
    }

    public function existeAvecNom(string $nom, ?Fournisseur $fournisseurExclu = null): bool
    {
        $requete = $this->createQueryBuilder('fournisseur')
            ->select('fournisseur.id')
            ->andWhere('LOWER(fournisseur.nom) = LOWER(:nom)')
            ->setParameter('nom', $nom);

        if (null !== $fournisseurExclu) {
            $requete->andWhere('fournisseur != :fournisseurExclu')
                ->setParameter('fournisseurExclu', $fournisseurExclu);
        }

        return null !== $requete->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }
}
