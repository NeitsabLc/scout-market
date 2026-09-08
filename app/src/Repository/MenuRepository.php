<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GrilleMenu;
use App\Entity\Menu;
use App\Entity\TypeRepas;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Menu> */
final class MenuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Menu::class);
    }

    /** @return list<Menu> */
    public function findActifs(): array
    {
        return $this->requeteComplete()
            ->innerJoin('menu.grilleMenu', 'grille')->addSelect('grille')
            ->andWhere('menu.actif = true')->andWhere('grille.actif = true')
            ->andWhere('menu.specialCode IS NOT NULL OR typeRepas.actif = true')
            ->orderBy('menu.specialCode', 'ASC')->addOrderBy('menu.dateMenu', 'ASC')
            ->addOrderBy('typeRepas.ordre', 'ASC')
            ->getQuery()->getResult();
    }

    public function findPourRepasGrille(GrilleMenu $grille, \DateTimeImmutable $date, TypeRepas $repas): ?Menu
    {
        return $this->requeteComplete()
            ->andWhere('menu.grilleMenu = :grille')->andWhere('menu.dateMenu = :date')
            ->andWhere('menu.typeRepas = :repas')->andWhere('menu.actif = true')
            ->setParameter('grille', $grille)->setParameter('date', $date)->setParameter('repas', $repas)
            ->getQuery()->getOneOrNullResult();
    }

    /** @return list<Menu> */
    public function findPourDateGrille(GrilleMenu $grille, \DateTimeImmutable $date): array
    {
        return $this->requeteComplete()
            ->andWhere('menu.grilleMenu = :grille')->andWhere('menu.dateMenu = :date')
            ->andWhere('menu.actif = true')->andWhere('typeRepas.actif = true')
            ->setParameter('grille', $grille)->setParameter('date', $date)
            ->orderBy('typeRepas.ordre', 'ASC')->addOrderBy('menuDenree.ordre', 'ASC')
            ->getQuery()->getResult();
    }

    /** @return list<Menu> */
    public function findPourDate(\DateTimeImmutable $date): array
    {
        return $this->requeteComplete()
            ->andWhere('menu.dateMenu = :date')->andWhere('menu.actif = true')
            ->andWhere('typeRepas.actif = true')->setParameter('date', $date)
            ->orderBy('typeRepas.ordre', 'ASC')->addOrderBy('menuDenree.ordre', 'ASC')
            ->getQuery()->getResult();
    }

    public function findSpecialGrille(GrilleMenu $grille, string $code): ?Menu
    {
        return $this->requeteComplete()
            ->andWhere('menu.grilleMenu = :grille')->andWhere('menu.specialCode = :code')->andWhere('menu.actif = true')
            ->setParameter('grille', $grille)->setParameter('code', $code)
            ->getQuery()->getOneOrNullResult();
    }

    /** @return list<Menu> */
    public function findActifsPourGrille(GrilleMenu $grille): array
    {
        return $this->requeteComplete()
            ->andWhere('menu.grilleMenu = :grille')->andWhere('menu.actif = true')
            ->andWhere('menu.specialCode IS NOT NULL OR typeRepas.actif = true')
            ->setParameter('grille', $grille)
            ->orderBy('menu.specialCode', 'ASC')->addOrderBy('menu.dateMenu', 'ASC')->addOrderBy('typeRepas.ordre', 'ASC')
            ->getQuery()->getResult();
    }

    private function requeteComplete(): QueryBuilder
    {
        return $this->createQueryBuilder('menu')
            ->addSelect('typeRepas', 'menuDenree', 'denree', 'recette', 'conditionnement', 'quantite', 'publicCible')
            ->leftJoin('menu.typeRepas', 'typeRepas')
            ->leftJoin('menu.denrees', 'menuDenree')->leftJoin('menuDenree.denree', 'denree')
            ->leftJoin('menuDenree.recette', 'recette')->leftJoin('menuDenree.conditionnement', 'conditionnement')
            ->leftJoin('menuDenree.quantites', 'quantite')->leftJoin('quantite.publicCible', 'publicCible')
            ->andWhere('publicCible.id IS NULL OR publicCible.actif = true');
    }
}
