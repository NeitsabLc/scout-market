<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\GroupeRepasRepository;
use App\Repository\GroupeRepository;
use App\Repository\MenuRepository;
use App\Repository\ReferenceFournisseurConditionnementRepository;
use App\Repository\ReferenceFournisseurRepository;
use App\Service\CalculCommande;
use App\Service\CalculCommandeFinale;
use App\Service\CalculStockDynamique;
use App\Service\RegroupementCommandeFournisseur;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(Utilisateur::ROLE_GESTIONNAIRE)]
final class CommandeController extends AbstractController
{
    #[Route('/intendance/commande', name: 'app_commande', methods: ['GET'])]
    public function index(
        Request $request,
        MenuRepository $menus,
        GroupeRepository $groupes,
        GroupeRepasRepository $groupeRepas,
        ReferenceFournisseurConditionnementRepository $conditionnements,
        ReferenceFournisseurRepository $referencesFournisseur,
        CalculCommande $calcul,
        CalculCommandeFinale $calculFinal,
        CalculStockDynamique $calculStock,
        RegroupementCommandeFournisseur $regroupementFournisseur,
        ClockInterface $clock,
    ): Response {
        $groupesActifs = $groupes->findActifs();
        $commandes = $calcul->calculer(
            $menus->findActifs(),
            $groupesActifs,
            $groupeRepas->findPourGroupes($groupesActifs),
        );
        $aujourdhui = $clock->now()->setTime(0, 0);
        $commandes = array_values(array_filter(
            $commandes,
            static fn (array $commande): bool => null !== $commande['menu']->getDateMenu()
                && $commande['menu']->getDateMenu() >= $aujourdhui,
        ));
        $commandesCalculables = array_values(array_filter(
            $commandes,
            static fn (array $commande): bool => 'PETIT_DEJEUNER' !== $commande['menu']->getTypeRepas()?->getCode(),
        ));
        $indexRepas = [];
        foreach ($commandesCalculables as $index => $commande) {
            $indexRepas[(string) $commande['menu']->getId()] = $index;
        }
        $premierId = [] === $commandesCalculables ? '' : (string) $commandesCalculables[0]['menu']->getId();
        $dernierId = [] === $commandesCalculables ? '' : (string) $commandesCalculables[array_key_last($commandesCalculables)]['menu']->getId();
        $selection = [
            'repas_deduction' => $request->query->getString('repas_deduction'),
            'repas_debut' => $request->query->getString('repas_debut', $premierId),
            'repas_fin' => $request->query->getString('repas_fin', $dernierId),
            'sec_en_caisse_deja_livre' => $request->query->getBoolean('sec_en_caisse_deja_livre'),
            'frais_journee_deja_livre' => $request->query->getBoolean('frais_journee_deja_livre'),
        ];
        $erreursCalcul = [];
        $commandeFinale = null;
        $commandeFinaleParFournisseur = null;
        if ($request->query->getBoolean('calculer')) {
            foreach ([$selection['repas_debut'], $selection['repas_fin']] as $repasId) {
                if (!isset($indexRepas[$repasId])) {
                    $erreursCalcul[] = 'Sélectionnez uniquement des repas disponibles.';
                    break;
                }
            }
            if ('' !== $selection['repas_deduction'] && !isset($indexRepas[$selection['repas_deduction']])) {
                $erreursCalcul[] = 'Sélectionnez un premier repas à déduire valide ou laissez ce champ vide.';
            }
            if ([] === $erreursCalcul) {
                $indexDebut = $indexRepas[$selection['repas_debut']];
                $indexFin = $indexRepas[$selection['repas_fin']];
                $indexDeduction = '' === $selection['repas_deduction'] ? $indexDebut : $indexRepas[$selection['repas_deduction']];
                if ($indexDeduction > $indexDebut) {
                    $erreursCalcul[] = 'Le premier repas à déduire doit précéder ou correspondre au début de la commande.';
                }
                if ($indexDebut > $indexFin) {
                    $erreursCalcul[] = 'Le dernier repas de la commande doit suivre son premier repas.';
                }
            }
            if ([] === $erreursCalcul) {
                $denrees = [];
                foreach ($commandesCalculables as $commande) {
                    foreach ($commande['lignes'] as $ligne) {
                        $denrees[(string) $ligne['denree']->getId()] = $ligne['denree'];
                    }
                }
                $denrees = array_values($denrees);
                $commandeFinale = $calculFinal->calculer(
                    $commandesCalculables,
                    $calculStock->pourDenrees($denrees),
                    $conditionnements->findPourDenrees($denrees),
                    $indexDeduction,
                    $indexDebut,
                    $indexFin,
                    $selection['sec_en_caisse_deja_livre'],
                    $selection['frais_journee_deja_livre'],
                );
                $commandeFinaleParFournisseur = $regroupementFournisseur->regrouper(
                    $commandeFinale,
                    $referencesFournisseur->findActifsPourDenrees(array_map(
                        static fn (array $ligne) => $ligne['denree'],
                        $commandeFinale,
                    )),
                );
            }
        }

        return $this->render('commande/index.html.twig', [
            'commandes' => $commandes,
            'commandes_calculables' => $commandesCalculables,
            'commande_finale' => $commandeFinale,
            'commande_finale_par_fournisseur' => $commandeFinaleParFournisseur,
            'erreurs_calcul' => $erreursCalcul,
            'selection' => $selection,
            'index_repas' => $indexRepas,
        ]);
    }
}
