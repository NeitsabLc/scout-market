<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\ConfigurationDistributionRepository;
use App\Repository\GroupeRepasRepository;
use App\Repository\GroupeRepository;
use App\Repository\MenuRepository;
use App\Service\ArchiveListesCourses;
use App\Service\CalculCommande;
use App\Service\PreparationDistribution;
use App\Service\PreparationVuesDistribution;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(Utilisateur::ROLE_GESTIONNAIRE)]
final class DistributionController extends AbstractController
{
    #[Route('/intendance/distribution', name: 'app_distribution', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('app_distribution_scout_market');
    }

    #[Route('/intendance/distribution/scout-market', name: 'app_distribution_scout_market', methods: ['GET'])]
    public function scoutMarket(
        MenuRepository $menus,
        GroupeRepository $groupes,
        GroupeRepasRepository $groupeRepas,
        CalculCommande $calcul,
        PreparationVuesDistribution $vues,
        ClockInterface $clock,
    ): Response {
        return $this->render('distribution/scout_market.html.twig', [
            'commandes' => $vues->scoutMarket($this->prochainesCommandes($menus, $groupes, $groupeRepas, $calcul, $clock)),
        ]);
    }

    #[Route('/intendance/distribution/en-caisse', name: 'app_distribution_en_caisse', methods: ['GET'])]
    public function enCaisse(
        MenuRepository $menus,
        GroupeRepository $groupes,
        GroupeRepasRepository $groupeRepas,
        CalculCommande $calcul,
        PreparationVuesDistribution $vues,
        ClockInterface $clock,
    ): Response {
        $commandes = $this->prochainesCommandes($menus, $groupes, $groupeRepas, $calcul, $clock);

        return $this->render('distribution/en_caisse.html.twig', [
            'produits_secs' => $vues->produitsSecsEnCaisse($commandes),
            'commandes' => $vues->enCaisse($commandes),
        ]);
    }

    #[Route('/intendance/distribution/configuration', name: 'app_distribution_configuration', methods: ['GET', 'POST'])]
    public function configuration(Request $request, ConfigurationDistributionRepository $configurations, EntityManagerInterface $em, PreparationDistribution $preparation): Response
    {
        $configuration = $configurations->unique();
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('configurer_distribution_'.$configuration->getId(), $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            if ('renouveler' === $request->request->getString('action')) {
                $configuration->renouvelerJetonDistributionPublique();
                $message = 'Un nouveau lien public a été généré. L’ancien lien ne fonctionne plus.';
            } else {
                $configuration->setDistributionPubliqueActive($request->request->has('distribution_publique_active'));
                $configuration->setDistribuerGouterDejeuner($request->request->has('distribuer_gouter_dejeuner'));
                $preparation->completerDejeuners($configuration);
                $message = 'La configuration de la distribution a bien été enregistrée.';
            }
            $em->flush();
            $this->addFlash('success', $message);

            return $this->redirectToRoute('app_distribution_configuration');
        }

        return $this->render('distribution/configuration.html.twig', [
            'configuration' => $configuration,
            'lien_public' => $this->generateUrl('app_sortie_consommation', ['jeton' => $configuration->getJetonDistributionPublique()], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    #[Route('/intendance/distribution/qr-code', name: 'app_distribution_qr_code', methods: ['GET'])]
    public function qrCode(Request $request, ConfigurationDistributionRepository $configurations): Response
    {
        $configuration = $configurations->unique();
        $url = $this->generateUrl('app_sortie_consommation', ['jeton' => $configuration->getJetonDistributionPublique()], UrlGeneratorInterface::ABSOLUTE_URL);
        $resultat = (new SvgWriter())->write(new QrCode(
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 420,
            margin: 16,
            foregroundColor: new Color(0, 58, 93),
            backgroundColor: new Color(255, 255, 255),
        ));
        $reponse = new Response($resultat->getString(), Response::HTTP_OK, ['Content-Type' => 'image/svg+xml']);
        if ($request->query->getBoolean('telecharger')) {
            $reponse->headers->set('Content-Disposition', 'attachment; filename="qr-distribution-'.$configuration->getId().'.svg"');
        }
        $reponse->headers->addCacheControlDirective('no-store');

        return $reponse;
    }

    #[Route('/intendance/distribution/listes-courses', name: 'app_distribution_listes_courses', methods: ['GET'])]
    public function listesCourses(Request $request, ConfigurationDistributionRepository $configurations, ArchiveListesCourses $archive): Response
    {
        $configuration = $configurations->unique();

        $dateDebut = $this->date($request->query->getString('date_debut'));
        $dateFin = $this->date($request->query->getString('date_fin'));
        if (
            null === $dateDebut
            || null === $dateFin
            || $dateDebut > $dateFin
        ) {
            $this->addFlash('error', 'Sélectionnez une période valide.');

            return $this->redirectToRoute('app_distribution_configuration');
        }

        $reponse = new BinaryFileResponse($archive->generer($configuration, $dateDebut, $dateFin));
        $reponse->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            sprintf('listes-courses-%s-au-%s.zip', $dateDebut->format('Y-m-d'), $dateFin->format('Y-m-d')),
        );
        $reponse->headers->set('Content-Type', 'application/zip');
        $reponse->headers->addCacheControlDirective('no-store');
        $reponse->deleteFileAfterSend();

        return $reponse;
    }

    private function date(string $valeur): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);

        return false !== $date && $date->format('Y-m-d') === $valeur ? $date : null;
    }

    /** @return list<array<string, mixed>> */
    private function prochainesCommandes(
        MenuRepository $menus,
        GroupeRepository $groupes,
        GroupeRepasRepository $groupeRepas,
        CalculCommande $calcul,
        ClockInterface $clock,
    ): array {
        $groupesActifs = $groupes->findActifs();
        $commandes = $calcul->calculer(
            $menus->findActifs(),
            $groupesActifs,
            $groupeRepas->findPourGroupes($groupesActifs),
        );
        $aujourdhui = $clock->now()->setTime(0, 0);

        return array_values(array_filter(
            $commandes,
            static fn (array $commande): bool => null !== $commande['menu']->getDateMenu()
                && $commande['menu']->getDateMenu() >= $aujourdhui,
        ));
    }
}
