<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GrilleMenu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<GrilleMenu> */
final class GrilleMenuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GrilleMenu::class);
    }

    /** @return list<GrilleMenu> */
    public function findActives(): array
    {
        return $this->createQueryBuilder('grille')
            ->andWhere('grille.actif = true')
            ->orderBy('grille.dateDebut', 'DESC')->addOrderBy('grille.label', 'ASC')
            ->getQuery()->getResult();
    }

    public function existeAvecLabel(string $label, ?GrilleMenu $exclue = null): bool
    {
        $requete = $this->createQueryBuilder('grille')->select('grille.id')
            ->andWhere('LOWER(grille.label) = LOWER(:label)')
            ->setParameter('label', $label);
        if (null !== $exclue) {
            $requete->andWhere('grille != :exclue')->setParameter('exclue', $exclue);
        }

        return null !== $requete->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }
}
