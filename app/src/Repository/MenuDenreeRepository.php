<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Denree;
use App\Entity\MenuDenree;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MenuDenree>
 */
final class MenuDenreeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuDenree::class);
    }

    /** @return list<MenuDenree> */
    public function findUtilisationsPourDenree(Denree $denree): array
    {
        return $this->createQueryBuilder('ligne')
            ->addSelect('menu', 'typeRepas', 'conditionnement', 'quantite', 'publicCible')
            ->join('ligne.menu', 'menu')
            ->leftJoin('menu.typeRepas', 'typeRepas')
            ->join('ligne.conditionnement', 'conditionnement')
            ->leftJoin('ligne.quantites', 'quantite')
            ->leftJoin('quantite.publicCible', 'publicCible')
            ->andWhere('ligne.denree = :denree')
            ->andWhere('menu.actif = true')
            ->setParameter('denree', $denree)
            ->orderBy('menu.dateMenu', 'ASC')
            ->addOrderBy('menu.specialCode', 'ASC')
            ->addOrderBy('typeRepas.ordre', 'ASC')
            ->addOrderBy('ligne.ordre', 'ASC')
            ->addOrderBy('publicCible.ordre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
