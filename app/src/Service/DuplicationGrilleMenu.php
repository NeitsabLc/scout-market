<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\GrilleMenu;
use App\Entity\Menu;
use App\Entity\MenuDenree;
use App\Entity\MenuDenreeQuantite;
use App\Repository\GrilleMenuRepository;
use App\Repository\MenuRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class DuplicationGrilleMenu
{
    public function __construct(
        private GrilleMenuRepository $grilles,
        private MenuRepository $menus,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function dupliquer(GrilleMenu $source): GrilleMenu
    {
        $copie = new GrilleMenu($this->prochainLabel($source), $source->getDateDebut(), $source->getDateFin());
        $this->entityManager->persist($copie);

        foreach ($this->menus->findActifsPourGrille($source) as $menuSource) {
            $menuCopie = (new Menu())
                ->setGrilleMenu($copie)
                ->setNom($menuSource->getNom())
                ->setTypeDistribution($menuSource->getTypeDistribution());
            if ($menuSource->isSpecial()) {
                $menuCopie->setSpecialCode($menuSource->getSpecialCode());
            } elseif (null !== $menuSource->getDateMenu() && null !== $menuSource->getTypeRepas()) {
                $menuCopie
                    ->setDateMenu($menuSource->getDateMenu())
                    ->setTypeRepas($menuSource->getTypeRepas());
            }

            /** @var array<string, Uuid> $instancesRecettes */
            $instancesRecettes = [];
            foreach ($menuSource->getDenrees() as $ligneSource) {
                $instanceSource = $ligneSource->getRecetteInstanceId();
                $instanceCopie = null;
                if (null !== $instanceSource) {
                    $cleInstance = (string) $instanceSource;
                    $instanceCopie = $instancesRecettes[$cleInstance] ??= Uuid::v7();
                }
                $ligneCopie = (new MenuDenree())
                    ->setDenree($ligneSource->getDenree())
                    ->setConditionnement($ligneSource->getConditionnement())
                    ->setCategorie($ligneSource->getCategorie())
                    ->setRegime($ligneSource->getRegime())
                    ->setRecette($ligneSource->getRecette())
                    ->setRecetteInstanceId($instanceCopie)
                    ->setOrdre($ligneSource->getOrdre());
                foreach ($ligneSource->getQuantites() as $quantiteSource) {
                    $ligneCopie->addQuantite((new MenuDenreeQuantite())
                        ->setPublicCible($quantiteSource->getPublicCible())
                        ->setQuantiteIndividuelle($quantiteSource->getQuantiteIndividuelle()));
                }
                $menuCopie->addDenree($ligneCopie);
            }
            $this->entityManager->persist($menuCopie);
        }

        $this->entityManager->flush();

        return $copie;
    }

    private function prochainLabel(GrilleMenu $source): string
    {
        $base = 'Copie de '.$source->getLabel();
        $numero = 1;
        do {
            $suffixe = 1 === $numero ? '' : sprintf(' (%d)', $numero);
            $label = mb_substr($base, 0, 150 - mb_strlen($suffixe)).$suffixe;
            ++$numero;
        } while ($this->grilles->existeAvecLabel($label));

        return $label;
    }
}
