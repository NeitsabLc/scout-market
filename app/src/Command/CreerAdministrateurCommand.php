<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:utilisateur:creer-administrateur',
    description: 'Crée le premier compte administrateur sans envoyer de courriel.',
)]
final class CreerAdministrateurCommand extends Command
{
    public function __construct(
        private readonly UtilisateurRepository $utilisateurs,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse électronique de l’administrateur')
            ->addArgument('prenom', InputArgument::REQUIRED, 'Prénom de l’administrateur')
            ->addArgument('nom', InputArgument::REQUIRED, 'Nom de l’administrateur');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = mb_strtolower(trim((string) $input->getArgument('email')));
        $prenom = trim((string) $input->getArgument('prenom'));
        $nom = trim((string) $input->getArgument('nom'));

        if (false === filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
            $io->error('L’adresse électronique n’est pas valide.');

            return Command::INVALID;
        }
        if ('' === $prenom || mb_strlen($prenom) > 100 || '' === $nom || mb_strlen($nom) > 100) {
            $io->error('Le prénom et le nom sont obligatoires et limités à 100 caractères.');

            return Command::INVALID;
        }
        if ($this->utilisateurs->loadUserByIdentifier($email) instanceof Utilisateur) {
            $io->error('Un utilisateur possède déjà cette adresse électronique.');

            return Command::FAILURE;
        }

        $question = (new Question('Mot de passe initial (saisie masquée)'))
            ->setHidden(true)
            ->setHiddenFallback(false)
            ->setMaxAttempts(3)
            ->setValidator(static function (mixed $valeur): string {
                $motDePasse = is_string($valeur) ? $valeur : '';
                if (mb_strlen($motDePasse) < 12
                    || !preg_match('/[a-z]/', $motDePasse)
                    || !preg_match('/[A-Z]/', $motDePasse)
                    || !preg_match('/\d/', $motDePasse)
                    || !preg_match('/[^a-zA-Z0-9]/', $motDePasse)) {
                    throw new \RuntimeException('Le mot de passe doit contenir au moins 12 caractères, une minuscule, une majuscule, un chiffre et un caractère spécial.');
                }

                return $motDePasse;
            });
        $motDePasse = $io->askQuestion($question);
        if (!is_string($motDePasse)) {
            $io->error('Le mot de passe est obligatoire.');

            return Command::INVALID;
        }

        $utilisateur = (new Utilisateur())
            ->setEmail($email)
            ->setPrenom($prenom)
            ->setNom($nom)
            ->setRole(Utilisateur::ROLE_ADMIN)
            ->setActif(true)
            ->setChangementMotDePasseRequis(false);
        $utilisateur->setPassword($this->hasher->hashPassword($utilisateur, $motDePasse));

        $this->entityManager->persist($utilisateur);
        $this->entityManager->flush();

        $io->success(sprintf('Le compte administrateur %s a été créé.', $email));

        return Command::SUCCESS;
    }
}
