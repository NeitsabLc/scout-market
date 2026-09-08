<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Groupe;
use App\Entity\Menu;
use App\Entity\MouvementStock;
use App\Entity\MouvementStockLigne;
use App\Repository\ConfigurationDistributionRepository;
use App\Repository\GroupeRepository;
use App\Repository\MenuRepository;
use App\Repository\OrigineMouvementRepository;
use App\Repository\TypeMouvementRepository;
use App\Repository\UtilisateurRepository;
use App\Service\ConversionConditionnement;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\RateLimit;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

final class SortieConsommationController extends AbstractController
{
    private const ORDRE_PUBLICS_DISTRIBUTION = [
        'FARFADETS' => 10,
        'LOUVETEAUX_JEANNETTES' => 20,
        'SCOUTS_GUIDES' => 30,
        'PIONNIERS_CARAVELLES' => 40,
        'ADULTE' => 100,
    ];

    #[Route('/distribution/{jeton}', name: 'app_sortie_consommation', requirements: ['jeton' => '[0-9a-fA-F-]{36}'], methods: ['GET', 'POST'])]
    #[RateLimit('public_distribution', methods: ['POST'])]
    public function index(
        string $jeton,
        Request $request,
        ConfigurationDistributionRepository $configurations,
        GroupeRepository $groupes,
        MenuRepository $menus,
        TypeMouvementRepository $types,
        OrigineMouvementRepository $origines,
        UtilisateurRepository $utilisateurs,
        ConversionConditionnement $conversion,
        EntityManagerInterface $entityManager,
    ): Response {
        $configuration = Uuid::isValid($jeton) ? $configurations->trouverParJeton($jeton) : null;
        if (null === $configuration) {
            return $this->render('sortie_consommation/index.html.twig', ['configuration' => null]);
        }

        $groupesActifs = $groupes->findActifsPresents(new \DateTimeImmutable('today'));
        $tousLesMenus = $menus->findActifs();
        $cleSoumission = $request->request->getString('cle_soumission');
        if (!Uuid::isValid($cleSoumission)) {
            $cleSoumission = Uuid::v7()->toRfc4122();
        }
        $menusActifs = array_values(array_filter(
            $tousLesMenus,
            static fn (Menu $menu): bool => !$menu->isSpecial() || !$menu->getDenrees()->isEmpty(),
        ));
        if ($configuration->isDistribuerGouterDejeuner()) {
            $menusActifs = array_values(array_filter(
                $menusActifs,
                fn (Menu $menu): bool => $menu->isSpecial() || !$this->repasEst($menu, 'GOUTER'),
            ));
        }

        $vues = [];
        foreach ($menusActifs as $menu) {
            $vues[(string) $menu->getId()] = $this->vueMenu(
                $menu,
                $tousLesMenus,
                $configuration->isDistribuerGouterDejeuner(),
                $conversion,
            );
        }

        $selection = null;
        $erreurs = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('sortie_consommation', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }

            $groupe = $this->selection($request->request->getString('groupe'), $groupesActifs);
            $menu = $this->selection($request->request->getString('menu'), $menusActifs);
            if (null === $groupe) {
                $erreurs[] = 'Sélectionnez un groupe valide.';
            }
            if (!$menu instanceof Menu) {
                $erreurs[] = 'Sélectionnez un repas valide.';
            }
            if ($menu instanceof Menu && $groupe instanceof Groupe && $menu->getGrilleMenu() !== $groupe->getGrilleMenu()) {
                $erreurs[] = 'Ce repas ne correspond pas à la grille de menus de l’unité.';
            }

            $quantites = [];
            $vueSelectionnee = null;
            if ($menu instanceof Menu && $groupe instanceof Groupe && $menu->getGrilleMenu() === $groupe->getGrilleMenu()) {
                $vueSelectionnee = $this->vuePourGroupe($vues[(string) $menu->getId()], $groupe);
                $quantitesSoumises = $request->request->all('quantites');
                foreach ($vueSelectionnee['lignes'] as $ligne) {
                    $brut = str_replace([' ', ','], ['', '.'], trim((string) ($quantitesSoumises[$ligne['cle']] ?? '')));
                    if ('' === $brut || !is_numeric($brut) || (float) $brut < 0) {
                        $erreurs[] = sprintf('Renseignez une quantité positive ou nulle pour %s.', $ligne['denree']->getNom());
                        continue;
                    }
                    $quantites[$ligne['cle']] = number_format((float) $brut, 3, '.', '');
                }
                if ([] === array_filter($quantites, static fn (string $quantite): bool => (float) $quantite > 0)) {
                    $erreurs[] = 'Au moins une denrée doit avoir une quantité supérieure à zéro.';
                }
            }

            if ([] === $erreurs && null !== $groupe && $menu instanceof Menu) {
                $selection = [
                    'groupe' => $groupe,
                    'menu' => $menu,
                    'vue' => $vueSelectionnee,
                    'quantites' => $quantites,
                ];
                if ('confirmer' === $request->request->getString('action')) {
                    $dejaEnregistre = $entityManager->getRepository(MouvementStock::class)->findOneBy(['cleSoumission' => $cleSoumission]);
                    if ($dejaEnregistre instanceof MouvementStock) {
                        return $this->render('sortie_consommation/succes.html.twig', compact('groupe', 'menu', 'configuration'));
                    }
                    $type = $types->findOneBy(['code' => 'SORTIE', 'actif' => true]);
                    $origine = $origines->findOneBy(['code' => 'DISTRIBUTION', 'actif' => true]);
                    $utilisateur = $utilisateurs->loadUserByIdentifier('saisie-consommation@scout-market.local');
                    if (null === $type || null === $origine || null === $utilisateur) {
                        throw new \RuntimeException('Référentiel incomplet.');
                    }

                    $mouvement = (new MouvementStock($utilisateur, $type, $origine))
                        ->setGroupe($groupe)
                        ->setMenu($menu)
                        ->setCleSoumission(Uuid::fromString($cleSoumission))
                        ->setDateMouvement($this->dateMouvementNavigateur($request, $menu));
                    $entityManager->persist($mouvement);
                    $sorties = [];
                    foreach ($selection['vue']['lignes'] as $ligne) {
                        $quantite = (float) $quantites[$ligne['cle']];
                        if ($quantite <= 0) {
                            continue;
                        }
                        $denreeId = (string) $ligne['denree']->getId();
                        $sorties[$denreeId] ??= [
                            'denree' => $ligne['denree'],
                            'conditionnement' => $ligne['unite'],
                            'quantite' => 0.0,
                            'quantiteInventaire' => 0.0,
                            'conditionnementUnique' => true,
                        ];
                        if ($sorties[$denreeId]['conditionnement'] !== $ligne['unite']) {
                            $sorties[$denreeId]['conditionnementUnique'] = false;
                        }
                        $sorties[$denreeId]['quantite'] += $quantite;
                        $sorties[$denreeId]['quantiteInventaire'] += $conversion->convertir(
                            $ligne['denree'],
                            $ligne['unite'],
                            $ligne['denree']->getUniteInventaire(),
                            $quantite,
                        );
                    }
                    foreach ($sorties as $sortie) {
                        $conditionnement = $sortie['conditionnementUnique']
                            ? $sortie['conditionnement']
                            : $sortie['denree']->getUniteInventaire();
                        $quantite = $sortie['conditionnementUnique']
                            ? $sortie['quantite']
                            : $sortie['quantiteInventaire'];
                        $mouvementLigne = new MouvementStockLigne(
                            $mouvement,
                            $sortie['denree'],
                            number_format($quantite, 3, '.', ''),
                        );
                        $mouvementLigne->setConditionnementSaisie($conditionnement);
                        $entityManager->persist($mouvementLigne);
                    }
                    try {
                        $entityManager->flush();
                    } catch (UniqueConstraintViolationException) {
                        // Deux confirmations concurrentes portant la même clé : la première a gagné.
                    }

                    return $this->render('sortie_consommation/succes.html.twig', compact('groupe', 'menu', 'configuration'));
                }
            }
        }

        $menuSoumis = $this->selection($request->request->getString('menu'), $menusActifs);

        return $this->render('sortie_consommation/index.html.twig', [
            'configuration' => $configuration,
            'groupes' => $groupesActifs,
            'menus' => $menusActifs,
            'vues' => $vues,
            'selection' => $selection,
            'erreurs' => $erreurs,
            'valeurs' => $request->request->all('quantites'),
            'groupe_soumis' => $request->request->getString('groupe'),
            'menu_soumis' => $request->request->getString('menu'),
            'date_soumise' => $menuSoumis instanceof Menu ? ($menuSoumis->getDateMenu()?->format('Y-m-d') ?? 'special') : '',
            'cle_soumission' => $cleSoumission,
        ]);
    }

    #[Route('/sortie-consommation', name: 'app_sortie_consommation_sans_jeton', methods: ['GET'])]
    public function sansJeton(): Response
    {
        return $this->render('sortie_consommation/index.html.twig', ['configuration' => null]);
    }

    private function repasEst(Menu $menu, string $code): bool
    {
        return !$menu->isSpecial() && $menu->getTypeRepas()?->getCode() === $code;
    }

    /**
     * @param list<Menu> $menus
     *
     * @return array{menu: Menu, lignes: list<array<string, mixed>>}
     */
    private function vueMenu(Menu $menu, array $menus, bool $fusion, ConversionConditionnement $conversion): array
    {
        $sources = [$menu];
        if ($fusion && $this->repasEst($menu, 'DEJEUNER')) {
            foreach ($menus as $candidat) {
                if ($candidat->getDateMenu()?->format('Y-m-d') === $menu->getDateMenu()?->format('Y-m-d')
                    && $candidat->getGrilleMenu() === $menu->getGrilleMenu()
                    && $this->repasEst($candidat, 'GOUTER')) {
                    $sources[] = $candidat;
                }
            }
        }

        $groupes = [];
        foreach ($sources as $source) {
            foreach ($source->getDenrees() as $ligne) {
                $denreeId = (string) $ligne->getDenree()->getId();
                $regime = $ligne->getRegime();
                $cle = $denreeId.'|'.(null === $regime ? 'STANDARD' : $regime->value);
                $groupes[$cle]['cle'] = $cle;
                $groupes[$cle]['denree'] = $ligne->getDenree();
                $groupes[$cle]['regime'] = $regime;
                $groupes[$cle]['sources'][] = $ligne;
            }
        }

        $resultat = [];
        foreach ($groupes as $groupe) {
            $premiereUnite = $groupe['sources'][0]->getConditionnement();
            $memeUnite = true;
            foreach ($groupe['sources'] as $ligne) {
                if ($ligne->getConditionnement() !== $premiereUnite) {
                    $memeUnite = false;
                }
            }
            $unite = $memeUnite ? $premiereUnite : $groupe['denree']->getUniteReference();
            $facteurSortie = $conversion->versUniteReference($groupe['denree'], $unite, 1);
            $quantites = [];
            foreach ($groupe['sources'] as $ligne) {
                $facteur = $conversion->versUniteReference($groupe['denree'], $ligne->getConditionnement(), 1);
                foreach ($ligne->getQuantites() as $quantite) {
                    $publicId = (string) $quantite->getPublicCible()->getId();
                    $quantites[$publicId] ??= [
                        'code' => $quantite->getPublicCible()->getCode(),
                        'libelle' => $quantite->getPublicCible()->getLibelle(),
                        'quantite' => 0.0,
                    ];
                    $quantites[$publicId]['quantite'] += (float) $quantite->getQuantiteIndividuelle() * $facteur / $facteurSortie;
                }
            }
            uasort($quantites, static function (array $a, array $b): int {
                $ordreA = self::ORDRE_PUBLICS_DISTRIBUTION[$a['code']] ?? 90;
                $ordreB = self::ORDRE_PUBLICS_DISTRIBUTION[$b['code']] ?? 90;

                return $ordreA <=> $ordreB ?: $a['libelle'] <=> $b['libelle'];
            });
            $resultat[] = [
                'cle' => $groupe['cle'],
                'denree' => $groupe['denree'],
                'regime' => $groupe['regime'],
                'unite' => $unite,
                'quantites' => $quantites,
            ];
        }

        return ['menu' => $menu, 'lignes' => $resultat];
    }

    /**
     * @param array{menu: Menu, lignes: list<array<string, mixed>>} $vue
     *
     * @return array{menu: Menu, lignes: list<array<string, mixed>>}
     */
    private function vuePourGroupe(array $vue, Groupe $groupe): array
    {
        $vue['lignes'] = array_values(array_filter(
            $vue['lignes'],
            static fn (array $ligne): bool => $groupe->aBesoinDuRegime($ligne['regime']),
        ));

        return $vue;
    }

    /**
     * @template T of object
     *
     * @param list<T> $items
     *
     * @return T|null
     */
    private function selection(string $id, array $items): ?object
    {
        if (!Uuid::isValid($id)) {
            return null;
        }
        foreach ($items as $item) {
            if ((string) $item->getId() === $id) {
                return $item;
            }
        }

        return null;
    }

    private function dateMouvementNavigateur(Request $request, Menu $menu): \DateTimeImmutable
    {
        $iso = $request->request->getString('date_navigateur');
        $heure = $request->request->getString('heure_navigateur');
        $decalageBrut = $request->request->getString('decalage_utc');
        $decalage = preg_match('/^-?\d+$/', $decalageBrut) ? (int) $decalageBrut : 0;
        try {
            $instant = '' !== $iso ? new \DateTimeImmutable($iso) : new \DateTimeImmutable();
        } catch (\Exception) {
            $instant = new \DateTimeImmutable();
        }
        if (null === $menu->getDateMenu() || !preg_match('/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $heure)) {
            return $instant;
        }

        $decalage = max(-840, min(840, $decalage));
        $minutes = -$decalage;
        $signe = $minutes >= 0 ? '+' : '-';
        $minutes = abs($minutes);
        $offset = sprintf('%s%02d:%02d', $signe, intdiv($minutes, 60), $minutes % 60);

        return new \DateTimeImmutable($menu->getDateMenu()->format('Y-m-d').'T'.$heure.$offset);
    }
}
