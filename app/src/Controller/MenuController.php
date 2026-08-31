<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\GrilleMenu;
use App\Entity\Menu;
use App\Entity\MenuDenree;
use App\Entity\MenuDenreeQuantite;
use App\Entity\Recette;
use App\Entity\TypeRepas;
use App\Entity\Utilisateur;
use App\Enum\RegimeAlimentaire;
use App\Enum\TypeDistributionMenu;
use App\Repository\ConfigurationDistributionRepository;
use App\Repository\DenreeRepository;
use App\Repository\GrilleMenuRepository;
use App\Repository\MenuRepository;
use App\Repository\PublicCibleRepository;
use App\Repository\RecetteRepository;
use App\Repository\TypeRepasRepository;
use App\Repository\UniteRepository;
use App\Service\ConversionConditionnement;
use App\Service\DuplicationGrilleMenu;
use App\Service\PreparationDistribution;
use App\Service\PresentationMenu;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted(new Expression("is_granted('ROLE_GESTIONNAIRE') or is_granted('ROLE_GROUPE')"))]
final class MenuController extends AbstractController
{
    private const CATEGORIES = Recette::CATEGORIES_MENU;
    private const SPECIAUX = ['EXPLO' => 'Explo', 'PIQUE_NIQUE_1' => 'Pique-nique 1', 'PIQUE_NIQUE_2' => 'Pique-nique 2'];

    #[Route('/menus', name: 'app_menus', methods: ['GET', 'POST'])]
    public function liste(Request $request, GrilleMenuRepository $grilles, MenuRepository $menus, TypeRepasRepository $repasRepository, PresentationMenu $presentation): Response
    {
        if ($this->isGranted(Utilisateur::ROLE_GROUPE)) {
            if ($request->isMethod('POST')) {
                throw $this->createAccessDeniedException('Les menus sont accessibles en lecture seule.');
            }
            $utilisateur = $this->getUser();
            $grille = $utilisateur instanceof Utilisateur ? $utilisateur->getGroupe()?->getGrilleMenu() : null;
            $date = null === $grille ? new \DateTimeImmutable('today') : $presentation->date($request, $grille->getDateDebut(), $grille->getDateFin());

            return $this->render('menu/groupe.html.twig', [
                'grille' => $grille, 'date_selectionnee' => $date,
                'date_libelle' => $presentation->libelleDate($date),
                'jour_precedent' => null !== $grille && $date > $grille->getDateDebut() ? $date->modify('-1 day') : null,
                'jour_suivant' => null !== $grille && $date < $grille->getDateFin() ? $date->modify('+1 day') : null,
                'menus_jour' => null === $grille ? [] : $presentation->menusDuJour($menus->findPourDateGrille($grille, $date), $repasRepository->findActifs()),
            ]);
        }

        return $this->render('menu/liste.html.twig', [
            'grilles' => $grilles->findActives(),
        ]);
    }

    #[Route('/menus/grilles/ajouter', name: 'app_grille_menu_ajouter', methods: ['GET', 'POST'])]
    public function ajouter(Request $request, GrilleMenuRepository $grilles, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(Utilisateur::ROLE_GESTIONNAIRE);
        $label = trim($request->request->getString('label'));
        $dateDebutSaisie = $this->date($request->request->getString('date_debut'));
        $dateFinSaisie = $this->date($request->request->getString('date_fin'));
        $dateDebut = $dateDebutSaisie ?? new \DateTimeImmutable('today');
        $dateFin = $dateFinSaisie ?? $dateDebut;
        $erreurs = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('ajouter_grille_menu', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }
            if ('' === $label) {
                $erreurs[] = 'Le libellé de la grille est obligatoire.';
            } elseif (mb_strlen($label) > 150) {
                $erreurs[] = 'Le libellé ne peut pas dépasser 150 caractères.';
            } elseif ($grilles->existeAvecLabel($label)) {
                $erreurs[] = 'Une grille portant ce libellé existe déjà.';
            }
            if (null === $dateDebutSaisie || null === $dateFinSaisie || $dateFin < $dateDebut) {
                $erreurs[] = 'Saisissez une période valide.';
            }
            if ([] === $erreurs) {
                $grille = new GrilleMenu($label, $dateDebut, $dateFin);
                $entityManager->persist($grille);
                $entityManager->flush();
                $this->addFlash('success', sprintf('La grille « %s » a bien été créée.', $label));

                return $this->redirectToRoute('app_grille_menu_modifier', ['id' => (string) $grille->getId()]);
            }
        }

        return $this->render('menu/formulaire_grille.html.twig', compact('label', 'dateDebut', 'dateFin', 'erreurs'));
    }

    #[Route('/menus/grilles/{id}/parametres', name: 'app_grille_menu_parametres', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['GET', 'POST'])]
    public function parametres(string $id, Request $request, GrilleMenuRepository $grilles, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(Utilisateur::ROLE_GESTIONNAIRE);
        $grille = Uuid::isValid($id) ? $grilles->find($id) : null;
        if (!$grille instanceof GrilleMenu || !$grille->isActif()) {
            throw $this->createNotFoundException('Grille de menus introuvable.');
        }
        $label = $request->isMethod('POST') ? trim($request->request->getString('label')) : $grille->getLabel();
        $dateDebut = $request->isMethod('POST') ? $this->date($request->request->getString('date_debut')) : $grille->getDateDebut();
        $dateFin = $request->isMethod('POST') ? $this->date($request->request->getString('date_fin')) : $grille->getDateFin();
        $erreurs = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('modifier_grille_menu_'.$id, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }
            if ('' === $label || mb_strlen($label) > 150) {
                $erreurs[] = 'Le libellé est obligatoire et limité à 150 caractères.';
            } elseif ($grilles->existeAvecLabel($label, $grille)) {
                $erreurs[] = 'Une grille portant ce libellé existe déjà.';
            }
            if (null === $dateDebut || null === $dateFin || $dateFin < $dateDebut) {
                $erreurs[] = 'Saisissez une période valide.';
            }
            if ([] === $erreurs) {
                $grille->setLabel($label)->setDates($dateDebut, $dateFin);
                $entityManager->flush();
                $this->addFlash('success', 'La grille de menus a bien été modifiée.');

                return $this->redirectToRoute('app_grille_menu_modifier', ['id' => $id]);
            }
        }

        return $this->render('menu/formulaire_grille.html.twig', ['grille' => $grille, 'label' => $label, 'dateDebut' => $dateDebut, 'dateFin' => $dateFin, 'erreurs' => $erreurs]);
    }

    #[Route('/menus/grilles/{id}/dupliquer', name: 'app_grille_menu_dupliquer', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['POST'])]
    public function dupliquer(string $id, Request $request, GrilleMenuRepository $grilles, DuplicationGrilleMenu $duplication): Response
    {
        $this->denyAccessUnlessGranted(Utilisateur::ROLE_GESTIONNAIRE);
        $grille = Uuid::isValid($id) ? $grilles->find($id) : null;
        if (null === $grille || !$grille->isActif()) {
            throw $this->createNotFoundException('Grille de menus introuvable.');
        }
        if (!$this->isCsrfTokenValid('dupliquer_grille_menu_'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $copie = $duplication->dupliquer($grille);
        $this->addFlash('success', sprintf('La grille « %s » a bien été dupliquée.', $grille->getLabel()));

        return $this->redirectToRoute('app_grille_menu_modifier', ['id' => (string) $copie->getId()]);
    }

    #[Route('/menus/speciaux', name: 'app_menus_speciaux', methods: ['GET'])]
    public function anciensSpeciaux(GrilleMenuRepository $grilles): Response
    {
        if ($this->isGranted(Utilisateur::ROLE_GROUPE)) {
            return $this->redirectToRoute('app_menus');
        }
        $grille = $grilles->findActives()[0] ?? null;

        return null === $grille ? $this->redirectToRoute('app_menus') : $this->redirectToRoute('app_grille_menu_speciaux', ['id' => (string) $grille->getId()]);
    }

    #[Route('/menus/grilles/{id}', name: 'app_grille_menu_modifier', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['GET', 'POST'])]
    #[Route('/menus/grilles/{id}/speciaux', name: 'app_grille_menu_speciaux', requirements: ['id' => '[0-9a-fA-F-]{36}'], methods: ['GET', 'POST'])]
    public function editer(
        string $id,
        Request $request,
        GrilleMenuRepository $grilles,
        TypeRepasRepository $repasRepository,
        MenuRepository $menus,
        DenreeRepository $denrees,
        PublicCibleRepository $publics,
        RecetteRepository $recettes,
        UniteRepository $unites,
        ConversionConditionnement $conversion,
        PreparationDistribution $preparationDistribution,
        ConfigurationDistributionRepository $configurationDistribution,
        PresentationMenu $presentation,
        EntityManagerInterface $entityManager,
        ClockInterface $clock,
    ): Response {
        $this->denyAccessUnlessGranted(Utilisateur::ROLE_GESTIONNAIRE);
        $grille = Uuid::isValid($id) ? $grilles->find($id) : null;
        if (null === $grille || !$grille->isActif()) {
            throw $this->createNotFoundException('Grille de menus introuvable.');
        }
        $pageSpeciaux = 'app_grille_menu_speciaux' === $request->attributes->get('_route');
        $repas = $repasRepository->findActifs();
        if ([] === $repas) {
            return $this->render('menu/index.html.twig', ['grille' => $grille, 'repas' => [], 'page_speciaux' => $pageSpeciaux]);
        }

        $specialDemande = $request->query->getString('special');
        $special = $pageSpeciaux && array_key_exists($specialDemande, self::SPECIAUX) ? $specialDemande : null;
        $date = $presentation->date($request, $grille->getDateDebut(), $grille->getDateFin(), $clock->now());
        $repasSelectionne = $presentation->repas($request->query->getString('repas'), $repas);
        $menu = null !== $special ? $menus->findSpecialGrille($grille, $special) : $menus->findPourRepasGrille($grille, $date, $repasSelectionne);
        $publicsActifs = $publics->findActifs();
        $avecCategories = null === $special && $presentation->avecCategories($repasSelectionne->getCode());
        $repasSuivant = null === $special ? $presentation->repasSuivant($date, $repasSelectionne, $repas, $grille->getDateFin()) : null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('enregistrer_menu', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException();
            }
            $journeeComplete = null === $special && $request->request->getBoolean('journee');
            $soumissions = [];
            if ($journeeComplete) {
                $repasSoumis = $request->request->all('repas');
                foreach ($repas as $configuration) {
                    $donneesRepas = $repasSoumis[(string) $configuration->getId()] ?? [];
                    $soumissions[] = [
                        'repas' => $configuration,
                        'special' => null,
                        'menu' => $menus->findPourRepasGrille($grille, $date, $configuration),
                        'avec_categories' => $presentation->avecCategories($configuration->getCode()),
                        'type_distribution' => is_array($donneesRepas) ? (string) ($donneesRepas['type_distribution'] ?? '') : '',
                        'lignes' => is_array($donneesRepas) && is_array($donneesRepas['lignes'] ?? null) ? $donneesRepas['lignes'] : [],
                    ];
                }
            } else {
                $soumissions[] = [
                    'repas' => $repasSelectionne,
                    'special' => $special,
                    'menu' => $menu,
                    'avec_categories' => $avecCategories,
                    'type_distribution' => $request->request->getString('type_distribution'),
                    'lignes' => $request->request->all('lignes'),
                ];
            }

            foreach ($soumissions as $soumission) {
                $typeDistribution = null !== $soumission['special']
                    ? TypeDistributionMenu::SCOUT_MARKET
                    : TypeDistributionMenu::tryFrom($soumission['type_distribution']);
                if (null === $typeDistribution) {
                    $this->addFlash('error', sprintf('Sélectionnez un mode de distribution pour le repas %s.', $soumission['repas']->getLibelle()));

                    return $this->redirectMenu($grille, $date, $repasSelectionne, $special);
                }
                $composition = [];
                foreach ($soumission['lignes'] as $donneesLigne) {
                    if (!is_array($donneesLigne)) {
                        continue;
                    }
                    $denreeId = (string) ($donneesLigne['denree'] ?? '');
                    $uniteId = (string) ($donneesLigne['conditionnement'] ?? '');
                    $denree = Uuid::isValid($denreeId) ? $denrees->find($denreeId) : null;
                    $unite = Uuid::isValid($uniteId) ? $unites->find($uniteId) : null;
                    if (null === $denree || null === $unite || !in_array($unite, $conversion->conditionnementsPour($denree), true)) {
                        continue;
                    }
                    $quantites = [];
                    foreach ($publicsActifs as $public) {
                        $valeur = str_replace(',', '.', trim((string) ($donneesLigne['quantites'][(string) $public->getId()] ?? '')));
                        if (!is_numeric($valeur) || (float) $valeur < 0) {
                            $this->addFlash('error', sprintf('Quantité invalide pour %s.', $denree->getNom()));

                            return $this->redirectMenu($grille, $date, $repasSelectionne, $special);
                        }
                        $quantites[(string) $public->getId()] = number_format((float) $valeur, 3, '.', '');
                    }
                    $categorie = $soumission['avec_categories'] && in_array($donneesLigne['categorie'] ?? null, self::CATEGORIES, true) ? $donneesLigne['categorie'] : null;
                    $regimeBrut = (string) ($donneesLigne['regime'] ?? '');
                    $regime = '' === $regimeBrut ? null : RegimeAlimentaire::tryFrom($regimeBrut);
                    if ('' !== $regimeBrut && null === $regime) {
                        $this->addFlash('error', sprintf('Régime alimentaire invalide pour %s.', $denree->getNom()));

                        return $this->redirectMenu($grille, $date, $repasSelectionne, $special);
                    }
                    $recetteId = (string) ($donneesLigne['recette'] ?? '');
                    $instanceId = (string) ($donneesLigne['recette_instance'] ?? '');
                    $recette = Uuid::isValid($recetteId) ? $recettes->find($recetteId) : null;
                    $instance = Uuid::isValid($instanceId) ? Uuid::fromString($instanceId) : null;
                    if (null === $recette || !$recette->isActif()) {
                        $recette = null;
                        $instance = null;
                    }
                    $composition[] = [$denree, $unite, $categorie, $regime, $quantites, $recette, $instance];
                }

                $menuCible = $soumission['menu'] ?? (new Menu())->setGrilleMenu($grille);
                if (null !== $soumission['special']) {
                    $menuCible->setSpecialCode($soumission['special']);
                } else {
                    $menuCible->setDateMenu($date)->setTypeRepas($soumission['repas'])->setTypeDistribution($typeDistribution);
                }
                foreach ($menuCible->getDenrees()->toArray() as $ancienne) {
                    $menuCible->removeDenree($ancienne);
                }
                foreach ($composition as $ordre => [$denree, $unite, $categorie, $regime, $quantites, $recette, $instance]) {
                    $ligne = (new MenuDenree())->setDenree($denree)->setConditionnement($unite)->setCategorie($categorie)->setRegime($regime)->setRecette($recette)->setRecetteInstanceId($instance)->setOrdre($ordre);
                    foreach ($publicsActifs as $public) {
                        $ligne->addQuantite((new MenuDenreeQuantite())->setPublicCible($public)->setQuantiteIndividuelle($quantites[(string) $public->getId()]));
                    }
                    $menuCible->addDenree($ligne);
                }
                $entityManager->persist($menuCible);
            }

            $entityManager->flush();
            $preparationDistribution->completerDejeuners($configurationDistribution->unique());
            $entityManager->flush();
            $this->addFlash('success', $journeeComplete ? 'Les menus de la journée ont bien été enregistrés.' : 'Le repas a bien été enregistré.');
            if (!$journeeComplete && 'suivant' === $request->request->getString('action') && null !== $repasSuivant) {
                return $this->redirectMenu($grille, $repasSuivant['date'], $repasSuivant['repas'], null);
            }

            return $this->redirectMenu($grille, $date, $repasSelectionne, $special);
        }

        $denreesActives = $denrees->findActifs();
        $catalogue = $presentation->catalogue($denreesActives, $conversion->conditionnementsPourDenrees($denreesActives));
        $recettesActives = $recettes->findActives();
        $menusDate = $menus->findPourDateGrille($grille, $date);
        $menusDateParRepas = [];
        foreach ($menusDate as $menuDate) {
            if (null !== ($configuration = $menuDate->getTypeRepas())) {
                $menusDateParRepas[(string) $configuration->getId()] = $menuDate;
            }
        }
        $editeursMenus = [];
        foreach ($repas as $configuration) {
            $codeRepas = $configuration->getCode();
            $menuDate = $menusDateParRepas[(string) $configuration->getId()] ?? null;
            $categories = $presentation->avecCategories($codeRepas);
            $editeursMenus[] = ['id' => (string) $configuration->getId(), 'code' => $codeRepas, 'libelle' => $configuration->getLibelle(), 'renseigne' => null !== $menuDate && !$menuDate->getDenrees()->isEmpty(), 'type_distribution' => ($menuDate?->getTypeDistribution() ?? TypeDistributionMenu::SCOUT_MARKET)->value, 'avec_categories' => $categories, 'categories_recettes' => $presentation->categoriesRecettesPourRepas($codeRepas), 'composition' => $presentation->composition($menuDate, $categories)];
        }
        $editeursSpeciaux = [];
        foreach (self::SPECIAUX as $code => $libelle) {
            $menuSpecial = $menus->findSpecialGrille($grille, $code);
            $editeursSpeciaux[] = ['code' => $code, 'libelle' => $libelle, 'renseigne' => null !== $menuSpecial && !$menuSpecial->getDenrees()->isEmpty(), 'avec_categories' => false, 'categories_recettes' => null, 'composition' => $presentation->composition($menuSpecial, false)];
        }

        return $this->render('menu/index.html.twig', [
            'grille' => $grille, 'page_speciaux' => $pageSpeciaux, 'repas' => $repas, 'repas_selectionne' => $repasSelectionne,
            'repas_suivant' => $repasSuivant, 'date_selectionnee' => $date, 'menu' => $menu, 'special' => $special, 'specials' => self::SPECIAUX,
            'publicsCibles' => $publicsActifs, 'catalogue' => $catalogue, 'regimes' => RegimeAlimentaire::choix(), 'recettes' => $recettesActives,
            'types_distribution' => TypeDistributionMenu::choix(),
            'recettes_json' => $presentation->recettesJson($recettesActives), 'categories_recettes' => null === $special ? $presentation->categoriesRecettesPourRepas($repasSelectionne->getCode()) : null,
            'avec_categories' => $avecCategories, 'editeurs_menus' => $editeursMenus, 'editeurs_speciaux' => $editeursSpeciaux, 'lecture_seule' => false,
            'date_libelle' => $presentation->libelleDate($date), 'jour_precedent' => $date > $grille->getDateDebut() ? $date->modify('-1 day') : null,
            'jour_suivant' => $date < $grille->getDateFin() ? $date->modify('+1 day') : null, 'menus_jour' => $presentation->menusDuJour($menusDate, $repas),
        ]);
    }

    private function redirectMenu(GrilleMenu $grille, \DateTimeImmutable $date, TypeRepas $repas, ?string $special): Response
    {
        return $this->redirectToRoute(null === $special ? 'app_grille_menu_modifier' : 'app_grille_menu_speciaux', null !== $special
            ? ['id' => (string) $grille->getId(), 'special' => $special]
            : ['id' => (string) $grille->getId(), 'date' => $date->format('Y-m-d'), 'repas' => (string) $repas->getId()]);
    }

    private function date(string $valeur): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);

        return false !== $date && $date->format('Y-m-d') === $valeur ? $date : null;
    }
}
