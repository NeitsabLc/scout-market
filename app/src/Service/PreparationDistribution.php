<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ConfigurationDistribution;
use App\Entity\Menu;
use App\Entity\TypeRepas;
use App\Repository\MenuRepository;
use App\Repository\TypeRepasRepository;
use Doctrine\ORM\EntityManagerInterface;

final class PreparationDistribution
{
    public function __construct(
        private readonly MenuRepository $menus,
        private readonly TypeRepasRepository $typesRepas,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Crée les déjeuners vides nécessaires à la fusion des goûters.
     * Cette méthode doit uniquement être appelée depuis un parcours authentifié.
     */
    public function completerDejeuners(ConfigurationDistribution $configuration, ?Menu $menuEnCours = null): int
    {
        if (!$configuration->isDistribuerGouterDejeuner()) {
            return 0;
        }

        $dejeuner = null;
        foreach ($this->typesRepas->findActifs() as $typeRepas) {
            if ('DEJEUNER' === $typeRepas->getCode()) {
                $dejeuner = $typeRepas;
                break;
            }
        }
        if (!$dejeuner instanceof TypeRepas) {
            return 0;
        }

        $menus = $this->menus->findActifs();
        if ($menuEnCours instanceof Menu && !in_array($menuEnCours, $menus, true)) {
            $menus[] = $menuEnCours;
        }

        $datesAvecDejeuner = [];
        foreach ($menus as $menu) {
            if ($this->repasEst($menu, 'DEJEUNER') && null !== $menu->getDateMenu()) {
                $datesAvecDejeuner[$this->cleGrilleDate($menu)] = true;
            }
        }

        $crees = 0;
        foreach ($menus as $menu) {
            $date = $menu->getDateMenu();
            if (!$this->repasEst($menu, 'GOUTER')
                || $menu->getDenrees()->isEmpty()
                || null === $date
                || isset($datesAvecDejeuner[$this->cleGrilleDate($menu)])) {
                continue;
            }

            $nouveau = (new Menu())
                ->setGrilleMenu($menu->getGrilleMenu() ?? throw new \LogicException('Le goûter doit appartenir à une grille.'))
                ->setDateMenu($date)
                ->setTypeRepas($dejeuner)
                ->setTypeDistribution($menu->getTypeDistribution());
            $this->entityManager->persist($nouveau);
            $datesAvecDejeuner[$this->cleGrilleDate($menu)] = true;
            ++$crees;
        }

        return $crees;
    }

    private function repasEst(Menu $menu, string $code): bool
    {
        return !$menu->isSpecial() && $menu->getTypeRepas()?->getCode() === $code;
    }

    private function cleGrilleDate(Menu $menu): string
    {
        return (string) ($menu->getGrilleMenu()?->getId() ?? 'SANS_GRILLE').'|'.$menu->getDateMenu()?->format('Y-m-d');
    }
}
