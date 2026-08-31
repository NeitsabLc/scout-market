<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Recette;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Recette> */
final class RecetteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $r)
    {
        parent::__construct($r, Recette::class);
    }

    /** @return list<Recette> */
    public function findActives(): array
    {
        return $this->findPourGestion(true);
    }

    /** @return list<Recette> */
    public function findPourGestion(bool $actif, string $tri = 'nom', string $ordre = 'asc'): array
    {
        $colonneTri = 'categorie' === $tri ? 'r.categorie' : 'r.nom';
        $directionTri = 'desc' === mb_strtolower($ordre) ? 'DESC' : 'ASC';

        $requete = $this->createQueryBuilder('r')
            ->addSelect('l', 'd', 'u', 'q', 'p')
            ->leftJoin('r.denrees', 'l')
            ->leftJoin('l.denree', 'd')
            ->leftJoin('l.conditionnement', 'u')
            ->leftJoin('l.quantites', 'q')
            ->leftJoin('q.publicCible', 'p')
            ->andWhere('r.actif = :actif')
            ->setParameter('actif', $actif)
            ->orderBy($colonneTri, $directionTri);
        if ('categorie' === $tri) {
            $requete->addOrderBy('r.nom', 'ASC');
        }

        return $requete->getQuery()->getResult();
    }

    public function existeAvecNom(string $nom, ?Recette $recetteExclue = null): bool
    {
        $requete = $this->createQueryBuilder('recette')
            ->select('recette.id')
            ->andWhere('LOWER(recette.nom) = LOWER(:nom)')
            ->setParameter('nom', $nom);

        if (null !== $recetteExclue) {
            $requete->andWhere('recette != :recetteExclue')
                ->setParameter('recetteExclue', $recetteExclue);
        }

        return null !== $requete->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }
}
