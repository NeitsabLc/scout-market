<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Denree;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Denree> */
final class DenreeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Denree::class);
    }

    /** @return list<Denree> */
    public function findActifs(): array
    {
        return $this->createQueryBuilder('e')
            ->addSelect('unite')
            ->join('e.uniteReference', 'unite')
            ->andWhere('e.actif = true')
            ->orderBy('e.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Denree> */
    public function findPourGestion(bool $actif): array
    {
        return $this->createQueryBuilder('d')
            ->addSelect('u', 'ui')
            ->join('d.uniteReference', 'u')
            ->join('d.uniteInventaire', 'ui')
            ->andWhere('d.actif = :actif')
            ->setParameter('actif', $actif)
            ->orderBy('d.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function existeAvecNom(string $nom, ?Denree $exclue = null): bool
    {
        $qb = $this->createQueryBuilder('d')->select('COUNT(d.id)')
            ->andWhere('LOWER(d.nom) = LOWER(:nom)')
            ->setParameter('nom', $nom);
        if (null !== $exclue) {
            $qb->andWhere('d != :exclue')->setParameter('exclue', $exclue);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
