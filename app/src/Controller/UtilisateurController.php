<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Groupe;
use App\Entity\Utilisateur;
use App\Repository\GroupeRepository;
use App\Repository\UtilisateurRepository;
use App\Service\InvitationUtilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted(Utilisateur::ROLE_ADMIN)]
final class UtilisateurController extends AbstractController
{
    private const ROLES = [
        Utilisateur::ROLE_ADMIN => 'Administrateur',
        Utilisateur::ROLE_GESTIONNAIRE => 'Gestionnaire',
        Utilisateur::ROLE_GROUPE => 'Unité participante',
    ];

    #[Route('/utilisateurs', name: 'app_utilisateurs', methods: ['GET', 'POST'])]
    #[Route('/utilisateurs/ajouter', name: 'app_utilisateur_ajouter', methods: ['GET', 'POST'])]
    #[Route('/utilisateurs/{id}/modifier', name: 'app_utilisateur_modifier', methods: ['GET', 'POST'])]
    public function index(Request $request, UtilisateurRepository $utilisateurs, GroupeRepository $groupes, InvitationUtilisateur $invitation, EntityManagerInterface $entityManager, ?string $id = null): Response
    {
        $roles = self::ROLES;
        $utilisateurRoute = null !== $id && Uuid::isValid($id) ? $utilisateurs->find($id) : null;
        if (null !== $id && !$utilisateurRoute instanceof Utilisateur) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        $donnees = [
            'utilisateur_id' => $request->request->getString('utilisateur_id', $id ?? ''),
            'prenom' => trim($request->request->getString('prenom')),
            'nom' => trim($request->request->getString('nom')),
            'email' => mb_strtolower(trim($request->request->getString('email'))),
            'role' => $request->request->getString('role'),
            'groupe' => $request->request->getString('groupe'),
        ];
        if (!$request->isMethod('POST') && $utilisateurRoute instanceof Utilisateur) {
            $donnees = [
                'utilisateur_id' => (string) $utilisateurRoute->getId(),
                'prenom' => $utilisateurRoute->getPrenom(), 'nom' => $utilisateurRoute->getNom(),
                'email' => $utilisateurRoute->getEmail(), 'role' => $utilisateurRoute->getRole(),
                'groupe' => (string) ($utilisateurRoute->getGroupe()?->getId() ?? ''),
            ];
        }
        $erreurs = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('creer_utilisateur', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            $utilisateurModifie = '' === $donnees['utilisateur_id'] ? null : (Uuid::isValid($donnees['utilisateur_id']) ? $utilisateurs->find($donnees['utilisateur_id']) : null);
            if ('' !== $donnees['utilisateur_id'] && !$utilisateurModifie instanceof Utilisateur) {
                throw $this->createNotFoundException('Utilisateur introuvable.');
            }
            if ('' === $donnees['prenom'] || mb_strlen($donnees['prenom']) > 100 || '' === $donnees['nom'] || mb_strlen($donnees['nom']) > 100) {
                $erreurs[] = 'Le prénom et le nom sont obligatoires et limités à 100 caractères.';
            }
            if (false === filter_var($donnees['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($donnees['email']) > 180) {
                $erreurs[] = 'Saisissez une adresse électronique valide.';
            } elseif (($existant = $utilisateurs->findOneBy(['email' => $donnees['email']])) instanceof Utilisateur && $existant !== $utilisateurModifie) {
                $erreurs[] = 'Un utilisateur possède déjà cette adresse électronique.';
            }
            if (!isset($roles[$donnees['role']])) {
                $erreurs[] = 'Sélectionnez un rôle valide.';
            }
            $groupe = null;
            if (Utilisateur::ROLE_GROUPE === $donnees['role']) {
                $groupe = Uuid::isValid($donnees['groupe']) ? $groupes->find($donnees['groupe']) : null;
                if (!$groupe instanceof Groupe || !$groupe->isActif()) {
                    $erreurs[] = 'Sélectionnez une unité participante active.';
                }
            }
            if ([] === $erreurs) {
                $creation = !$utilisateurModifie instanceof Utilisateur;
                $utilisateur = $utilisateurModifie ?? new Utilisateur();
                $utilisateur->setPrenom($donnees['prenom'])->setNom($donnees['nom'])->setEmail($donnees['email'])->setRole($donnees['role'])->setGroupe($groupe);
                if ($creation) {
                    $invitation->envoyer($utilisateur);
                } else {
                    $entityManager->flush();
                }
                $this->addFlash('success', $creation ? 'Le compte a été créé et son invitation envoyée.' : 'Le compte a été mis à jour.');

                return $this->redirectToRoute('app_utilisateurs');
            }
        }

        $vue = 'app_utilisateurs' === $request->attributes->get('_route') && !$request->isMethod('POST') ? 'utilisateur/index.html.twig' : 'utilisateur/formulaire.html.twig';

        return $this->render($vue, [
            'utilisateurs' => $utilisateurs->findPourAdministration(), 'groupes' => $groupes->findActifs(),
            'roles' => $roles, 'donnees' => $donnees, 'erreurs' => $erreurs,
        ], $request->isMethod('POST') ? new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY) : null);
    }

    #[Route('/utilisateurs/{id}/statut', name: 'app_utilisateurs_statut', methods: ['POST'])]
    public function changerStatut(Utilisateur $utilisateur, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('statut_utilisateur_'.$utilisateur->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        if ($utilisateur === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas désactiver votre propre compte.');
        } else {
            $utilisateur->setActif(!$utilisateur->isActif());
            $entityManager->flush();
            $this->addFlash('success', sprintf('Le compte a été %s.', $utilisateur->isActif() ? 'réactivé' : 'désactivé'));
        }

        return $this->redirectToRoute('app_utilisateurs');
    }
}
