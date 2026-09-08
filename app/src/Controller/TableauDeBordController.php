<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\DenreeRepository;
use App\Repository\GrilleMenuRepository;
use App\Repository\GroupeRepository;
use App\Repository\MenuRepository;
use App\Repository\RecetteRepository;
use App\Service\PresentationMenu;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(new Expression("is_granted('ROLE_GESTIONNAIRE') or is_granted('ROLE_GROUPE')"))]
final class TableauDeBordController extends AbstractController
{
    #[Route('/', name: 'app_tableau_de_bord', methods: ['GET'])]
    public function index(
        DenreeRepository $denrees,
        GrilleMenuRepository $grilles,
        GroupeRepository $groupes,
        MenuRepository $menus,
        RecetteRepository $recettes,
        PresentationMenu $presentation,
    ): Response {
        if ($this->isGranted(Utilisateur::ROLE_GROUPE)) {
            return $this->redirectToRoute('app_menus');
        }

        $catalogue = $denrees->findActifs();
        $aujourdhui = new \DateTimeImmutable('today');
        $unitesPresentes = $groupes->findActifsPresents($aujourdhui);
        $resumeUnites = [
            'totaux' => [
                'unites' => count($unitesPresentes),
                'jeunes' => 0,
                'adultes' => 0,
                'total' => 0,
                'vegetariens' => 0,
                'sans_gluten' => 0,
                'sans_lactose' => 0,
            ],
            'unites' => [],
        ];
        foreach ($unitesPresentes as $unite) {
            $effectifTotal = $unite->getEffectifJeune() + $unite->getEffectifAdulte();
            $resumeUnites['unites'][] = [
                'unite' => $unite,
                'jeunes' => $unite->getEffectifJeune(),
                'adultes' => $unite->getEffectifAdulte(),
                'total' => $effectifTotal,
                'vegetariens' => $unite->getNombreVegetariens(),
                'sans_gluten' => $unite->getNombreSansGluten(),
                'sans_lactose' => $unite->getNombreSansLactose(),
            ];
            $resumeUnites['totaux']['jeunes'] += $unite->getEffectifJeune();
            $resumeUnites['totaux']['adultes'] += $unite->getEffectifAdulte();
            $resumeUnites['totaux']['total'] += $effectifTotal;
            $resumeUnites['totaux']['vegetariens'] += $unite->getNombreVegetariens();
            $resumeUnites['totaux']['sans_gluten'] += $unite->getNombreSansGluten();
            $resumeUnites['totaux']['sans_lactose'] += $unite->getNombreSansLactose();
        }

        return $this->render('tableau_de_bord/index.html.twig', [
            'aujourdhui' => $aujourdhui,
            'nombre_denrees' => count($catalogue),
            'nombre_grilles' => count($grilles->findActives()),
            'nombre_recettes' => $recettes->countActives(),
            'resume_unites' => $resumeUnites,
            'menus_du_jour' => $presentation->resumesMenus($menus->findPourDate($aujourdhui)),
        ]);
    }
}
