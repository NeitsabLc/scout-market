<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Groupe;
use App\Entity\Menu;
use App\Entity\MenuDenree;
use App\Entity\MenuDenreeQuantite;

final class PreparationFichesRecettes
{
    public function __construct(
        private readonly AffichageQuantite $affichageQuantite,
        private readonly DescriptionRecetteSanitizer $descriptionSanitizer,
    ) {
    }

    /** @return list<string> */
    public function elementsMenu(Menu $menu): array
    {
        $elements = [];
        $recettesVues = [];
        foreach ($menu->getDenrees() as $ligne) {
            $recette = $ligne->getRecette();
            if (null === $recette) {
                $elements[] = $ligne->getDenree()->getNom();
                continue;
            }
            $instance = (string) ($ligne->getRecetteInstanceId() ?? $recette->getId());
            if (!isset($recettesVues[$instance])) {
                $recettesVues[$instance] = true;
                $elements[] = $recette->getNom();
            }
        }

        return $elements;
    }

    /**
     * @param array<string, true>|null $codesPublics
     *
     * @return list<array{
     *     nom: string,
     *     categorie: string,
     *     description: ?string,
     *     lignes: list<array{nom: string, quantites: list<array{public: string, valeur: string}>}>
     * }>
     */
    public function groupes(Menu $menu, ?array $codesPublics): array
    {
        /** @var list<array{nom: string, categorie: string, description: ?string, lignes: list<array{nom: string, quantites: list<array{public: string, valeur: string}>}>}> $groupes */
        $groupes = [];
        $indexParCle = [];
        foreach ($menu->getDenrees() as $ligne) {
            $recette = $ligne->getRecette();
            $cle = null === $recette
                ? 'denree:'.(string) $ligne->getId()
                : 'recette:'.(string) ($ligne->getRecetteInstanceId() ?? $recette->getId());
            if (!isset($indexParCle[$cle])) {
                $indexParCle[$cle] = count($groupes);
                $groupes[] = [
                    'nom' => $recette?->getNom() ?? $ligne->getDenree()->getNom(),
                    'categorie' => $ligne->getCategorie() ?? '',
                    'description' => null === $recette ? null : $this->descriptionSanitizer->nettoyer($recette->getDescription() ?? ''),
                    'lignes' => [],
                ];
            }
            $index = $indexParCle[$cle];
            $groupe = $groupes[$index];
            $groupe['lignes'][] = $this->ligne($ligne, $codesPublics);
            $groupes[$index] = $groupe;
        }

        return $groupes;
    }

    /**
     * @param list<array{
     *     nom: string,
     *     categorie: string,
     *     description: ?string,
     *     lignes: list<array{nom: string, quantites: list<array{public: string, valeur: string}>}>
     * }> $groupes
     *
     * @return list<list<array{
     *     nom: string,
     *     categorie: string,
     *     description: ?string,
     *     lignes: list<array{nom: string, quantites: list<array{public: string, valeur: string}>}>
     * }>>
     */
    public function repartirParPage(array $groupes): array
    {
        $pages = [];
        $page = [];
        $charge = 0.0;

        foreach ($groupes as $groupe) {
            $description = strip_tags($groupe['description'] ?? '');
            $poids = 1.0
                + max(0, count($groupe['lignes']) - 1) * 0.35
                + min(4.0, mb_strlen($description) / 300);

            if ([] !== $page && (count($page) >= 6 || $charge + $poids > 6.0)) {
                $pages[] = $page;
                $page = [];
                $charge = 0.0;
            }

            $page[] = $groupe;
            $charge += $poids;
        }

        if ([] !== $page) {
            $pages[] = $page;
        }

        return $pages;
    }

    /**
     * @param list<Groupe> $groupes
     *
     * @return array<string, true>|null
     */
    public function codesPublics(array $groupes): ?array
    {
        if ([] === $groupes) {
            return null;
        }

        $codes = [];
        foreach ($groupes as $groupe) {
            $code = mb_strtoupper(str_replace('-', '_', trim($groupe->getType())));
            if ('' !== $code) {
                $codes[$code] = true;
            }
        }

        return $codes;
    }

    /**
     * @param array<string, true>|null $codesPublics
     *
     * @return array{nom: string, quantites: list<array{public: string, valeur: string}>}
     */
    private function ligne(MenuDenree $ligne, ?array $codesPublics): array
    {
        $quantites = [];
        /** @var MenuDenreeQuantite $quantite */
        foreach ($ligne->getQuantites() as $quantite) {
            $public = $quantite->getPublicCible();
            if (null !== $codesPublics && !isset($codesPublics[$public->getCode()])) {
                continue;
            }
            $quantites[] = [
                'ordre' => $public->getOrdre(),
                'public' => $public->getLibelle(),
                'valeur' => $this->affichageQuantite->parPersonne($quantite->getQuantiteIndividuelle()).' '.$ligne->getConditionnement()->getSymbole().'/pers.',
            ];
        }
        usort($quantites, static fn (array $a, array $b): int => $a['ordre'] <=> $b['ordre']);

        return [
            'nom' => $ligne->getDenree()->getNom().(null === $ligne->getRegime() ? '' : ' - '.$ligne->getRegime()->libelle()),
            'quantites' => array_map(static fn (array $quantite): array => [
                'public' => $quantite['public'],
                'valeur' => $quantite['valeur'],
            ], $quantites),
        ];
    }
}
