<?php

declare(strict_types=1);

namespace App\Tests\Functional\ScoutMarket;

use App\Entity\Denree;
use App\Entity\GrilleMenu;
use App\Entity\Menu;
use App\Entity\MenuDenree;
use App\Entity\MenuDenreeQuantite;
use App\Entity\Recette;
use App\Entity\Utilisateur;
use App\Enum\TypeDenree;
use App\Repository\PublicCibleRepository;
use App\Repository\TypeRepasRepository;
use App\Repository\UniteRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\UuidV7;

final class FichesRecettesControllerTest extends WebTestCase
{
    public function testLeBoutonGenereLesFichesRecettesDeLaGrille(): void
    {
        $client = static::createClient();
        $client->loginUser($this->administrateur());
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $unite = static::getContainer()->get(UniteRepository::class)->findOneBy(['symbole' => 'g']);
        $repas = static::getContainer()->get(TypeRepasRepository::class)->findOneBy(['code' => 'DEJEUNER']);
        $publics = static::getContainer()->get(PublicCibleRepository::class)->findActifs();
        self::assertNotNull($unite);
        self::assertNotNull($repas);
        self::assertNotEmpty($publics);

        $suffixe = bin2hex(random_bytes(4));
        $date = new \DateTimeImmutable('2026-10-17');
        $denree = (new Denree())
            ->setNom('Carottes PDF '.$suffixe)
            ->setType(TypeDenree::SEC)
            ->setUniteReference($unite)
            ->setUniteInventaire($unite);
        $recette = (new Recette())
            ->setNom('Recette PDF '.$suffixe)
            ->setCategorie('PLAT')
            ->setDescription('<p>Préparer puis servir.</p>');
        $grille = new GrilleMenu('Stage PDF '.$suffixe, $date, $date);
        $ligne = (new MenuDenree())
            ->setDenree($denree)
            ->setConditionnement($unite)
            ->setCategorie('PLAT')
            ->setRecette($recette)
            ->setRecetteInstanceId(new UuidV7());
        foreach ($publics as $public) {
            $ligne->addQuantite((new MenuDenreeQuantite())
                ->setPublicCible($public)
                ->setQuantiteIndividuelle('120.000'));
        }
        $menu = (new Menu())
            ->setGrilleMenu($grille)
            ->setDateMenu($date)
            ->setTypeRepas($repas)
            ->addDenree($ligne);
        foreach ([$denree, $recette, $grille, $menu] as $entite) {
            $entityManager->persist($entite);
        }
        $entityManager->flush();

        $client->request('GET', '/menus');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf(
            'a.menu-grid-pdf-button[href="/menus/grilles/%s/fiches-recettes.pdf"][data-turbo="false"]',
            $grille->getId(),
        ));

        $client->request('GET', sprintf('/menus/grilles/%s/fiches-recettes.pdf', $grille->getId()));
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/pdf');
        self::assertStringContainsString('attachment;', $client->getResponse()->headers->get('content-disposition', ''));
        self::assertStringStartsWith('%PDF-', $client->getResponse()->getContent() ?: '');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        foreach ([Menu::class => $menu->getId(), Recette::class => $recette->getId(), Denree::class => $denree->getId(), GrilleMenu::class => $grille->getId()] as $classe => $id) {
            $entite = $entityManager->find($classe, $id);
            if (null !== $entite) {
                $entityManager->remove($entite);
                $entityManager->flush();
            }
        }
    }

    private function administrateur(): Utilisateur
    {
        $utilisateur = static::getContainer()->get(UtilisateurRepository::class)
            ->findOneBy(['email' => 'admin@scout-market.local']);
        self::assertInstanceOf(Utilisateur::class, $utilisateur);

        return $utilisateur;
    }
}
