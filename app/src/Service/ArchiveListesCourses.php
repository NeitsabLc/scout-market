<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ConfigurationDistribution;
use App\Entity\Groupe;
use App\Entity\Menu;
use App\Enum\ModeRepasGroupe;
use App\Repository\GroupeRepasRepository;
use App\Repository\GroupeRepository;
use App\Repository\MenuRepository;

final class ArchiveListesCourses
{
    public function __construct(
        private GroupeRepository $groupes,
        private GroupeRepasRepository $groupeRepas,
        private MenuRepository $menus,
        private ListeCoursesPdf $pdf,
    ) {
    }

    public function generer(ConfigurationDistribution $configuration, \DateTimeImmutable $dateDebut, \DateTimeImmutable $dateFin): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'scout-market-listes-courses-');
        if (false === $chemin) {
            throw new \RuntimeException('Impossible de créer l’archive temporaire.');
        }

        $zip = new \ZipArchive();
        if (true !== $zip->open($chemin, \ZipArchive::OVERWRITE)) {
            @unlink($chemin);
            throw new \RuntimeException('Impossible d’ouvrir l’archive temporaire.');
        }

        $groupes = $this->groupes->findActifs();
        $menus = $this->menus->findActifs();
        $modes = [];
        foreach ($this->groupeRepas->findPourGroupes($groupes) as $configuration) {
            $modes[(string) $configuration->getGroupe()->getId()][(string) $configuration->getMenu()->getId()] = $configuration->getMode();
        }
        $menusSpeciaux = [];
        foreach ($menus as $menu) {
            if ($menu->isSpecial()) {
                $grilleId = (string) ($menu->getGrilleMenu()?->getId() ?? 'SANS_GRILLE');
                $menusSpeciaux[$grilleId][$menu->getSpecialCode() ?? ''] = $menu;
            }
        }

        $nombreFiches = 0;
        foreach ($menus as $menu) {
            $dateMenu = $menu->getDateMenu();
            if (
                $this->ignorer($menu, $configuration)
                || null === $dateMenu
                || $dateMenu < $dateDebut
                || $dateMenu > $dateFin
            ) {
                continue;
            }
            $dossier = $this->dossier($menu);
            foreach ($groupes as $groupe) {
                if (!$groupe->estPresentLe($dateMenu) || $groupe->getGrilleMenu() !== $menu->getGrilleMenu()) {
                    continue;
                }
                $source = $this->sourcePour($menu, $groupe, $modes, $menusSpeciaux);
                if (!$source instanceof Menu) {
                    continue;
                }
                $menusSupplementaires = [];
                foreach ($this->menusFusionnes($menu, $menus, $configuration) as $menuFusionne) {
                    $sourceFusionnee = $this->sourcePour($menuFusionne, $groupe, $modes, $menusSpeciaux);
                    if ($sourceFusionnee instanceof Menu) {
                        $menusSupplementaires[] = $sourceFusionnee;
                    }
                }
                $zip->addFromString(
                    $dossier.'/'.$this->nomFichier($menu, $source, $groupe),
                    $this->pdf->generer($source, $groupe, $menusSupplementaires, $menu),
                );
                ++$nombreFiches;
            }
        }
        if (0 === $nombreFiches) {
            $zip->addFromString(
                'AUCUNE_LISTE.txt',
                "Aucun repas daté et actif n’est configuré sur la période sélectionnée.\n",
            );
        }
        $zip->close();

        return $chemin;
    }

    /**
     * @param array<string, array<string, ModeRepasGroupe>> $modes
     * @param array<string, array<string, Menu>>            $menusSpeciaux
     */
    private function sourcePour(Menu $menu, Groupe $groupe, array $modes, array $menusSpeciaux): ?Menu
    {
        $mode = $modes[(string) $groupe->getId()][(string) $menu->getId()] ?? null;
        if (ModeRepasGroupe::NON_PRIS === $mode) {
            return null;
        }
        if (null === $mode) {
            return $menu;
        }

        $grilleId = (string) ($groupe->getGrilleMenu()?->getId() ?? 'SANS_GRILLE');

        return $menusSpeciaux[$grilleId][$mode->value] ?? null;
    }

    private function ignorer(Menu $menu, ConfigurationDistribution $configuration): bool
    {
        if ($menu->isSpecial() || null === $menu->getDateMenu()) {
            return true;
        }

        return $configuration->isDistribuerGouterDejeuner()
            && 'GOUTER' === $menu->getTypeRepas()?->getCode();
    }

    private function dossier(Menu $menu): string
    {
        $date = $menu->getDateMenu();

        return null === $date ? 'sans-date' : $date->format('Y-m-d').'_'.self::slug($menu->getLibelle());
    }

    /**
     * @param list<Menu> $menus
     *
     * @return list<Menu>
     */
    private function menusFusionnes(Menu $menu, array $menus, ConfigurationDistribution $configuration): array
    {
        if (
            !$configuration->isDistribuerGouterDejeuner()
            || 'DEJEUNER' !== $menu->getTypeRepas()?->getCode()
        ) {
            return [];
        }

        return array_values(array_filter(
            $menus,
            static fn (Menu $candidat): bool => $candidat->getDateMenu()?->format('Y-m-d') === $menu->getDateMenu()?->format('Y-m-d')
                && $candidat->getGrilleMenu() === $menu->getGrilleMenu()
                && 'GOUTER' === $candidat->getTypeRepas()?->getCode(),
        ));
    }

    private function nomFichier(Menu $menuPlanifie, Menu $source, Groupe $groupe): string
    {
        return sprintf(
            '%s_%s_%s.pdf',
            self::slug($groupe->getNom()),
            $menuPlanifie->getDateMenu()?->format('Y-m-d') ?? 'sans-date',
            self::slug($source->getLibelle()),
        );
    }

    private static function slug(string $valeur): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valeur);
        $slug = strtolower((string) $ascii);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-') ?: 'element';
    }
}
