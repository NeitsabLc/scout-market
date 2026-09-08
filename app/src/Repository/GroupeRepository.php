<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Groupe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Groupe> */
final class GroupeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Groupe::class);
    }

    /** @return list<Groupe> */
    public function findActifs(): array
    {
        return $this->createQueryBuilder('e')->andWhere('e.actif = true')->getQuery()->getResult();
    }

    /** @return list<Groupe> */
    /** @return list<Groupe> */
    public function findActifsPresents(\DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('groupe')
            ->andWhere('groupe.actif = true')
            ->andWhere('groupe.dateDebutPresence <= :date')
            ->andWhere('groupe.dateFinPresence >= :date')
            ->setParameter('date', $date)
            ->orderBy('groupe.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Groupe> */
    public function findPourGestion(bool $inclureInactifs = false): array
    {
        $requete = $this->createQueryBuilder('groupe')
            ->orderBy('groupe.actif', 'DESC')
            ->addOrderBy('groupe.nom', 'ASC');

        if (!$inclureInactifs) {
            $requete->andWhere('groupe.actif = true');
        }

        return $requete->getQuery()->getResult();
    }

    public function existeAvecNom(string $nom, ?Groupe $groupeExclu = null): bool
    {
        $requete = $this->createQueryBuilder('groupe')
            ->select('groupe.id')
            ->andWhere('LOWER(groupe.nom) = LOWER(:nom)')
            ->setParameter('nom', $nom);

        if (null !== $groupeExclu) {
            $requete->andWhere('groupe != :groupeExclu')->setParameter('groupeExclu', $groupeExclu);
        }

        return null !== $requete->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
