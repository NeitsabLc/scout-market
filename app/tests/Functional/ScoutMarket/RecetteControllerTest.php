<?php

declare(strict_types=1);

namespace App\Tests\Functional\ScoutMarket;

use App\Entity\Denree;
use App\Entity\Recette;
use App\Entity\Utilisateur;
use App\Enum\TypeDenree;
use App\Repository\DenreeRepository;
use App\Repository\PublicCibleRepository;
use App\Repository\RecetteRepository;
use App\Repository\UniteRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RecetteControllerTest extends WebTestCase
{
    public function testUneDescriptionStructureeEstEnregistreeEtAssainie(): void
    {
        $client = static::createClient();
        $client->loginUser($this->administrateur());

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $conditionnement = static::getContainer()->get(UniteRepository::class)->findOneBy(['symbole' => 'g']);
        self::assertNotNull($conditionnement);
        $suffixe = bin2hex(random_bytes(4));
        $denree = (new Denree())
            ->setNom('Denrée recette '.$suffixe)
            ->setType(TypeDenree::SEC)
            ->setUniteReference($conditionnement)
            ->setUniteInventaire($conditionnement);
        $entityManager->persist($denree);
        $entityManager->flush();
        self::assertContains($denree, static::getContainer()->get(DenreeRepository::class)->findActifs());
        $publics = static::getContainer()->get(PublicCibleRepository::class)->findActifs();
        self::assertNotEmpty($publics);

        $crawler = $client->request('GET', '/recettes/ajouter');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller="rich-text-editor"]');
        self::assertSelectorExists('[role="toolbar"][aria-label="Mise en forme de la description"]');

        $quantites = [];
        foreach ($publics as $public) {
            $quantites[(string) $public->getId()] = '1';
        }
        $nom = 'Recette avec description '.$suffixe;
        $client->request('POST', '/recettes/ajouter', [
            '_token' => $crawler->filter('input[name="_token"]')->attr('value'),
            'nom' => $nom,
            'categorie' => 'PLAT',
            'description' => '<p onclick="attaque()">Préparer <strong>doucement</strong>.</p><ol><li>Mélanger</li><li>Servir</li></ol><script>alert(1)</script>',
            'lignes' => [[
                'denree' => (string) $denree->getId(),
                'conditionnement' => (string) $conditionnement->getId(),
                'regime' => '',
                'quantites' => $quantites,
            ]],
        ]);
        self::assertResponseRedirects('/recettes');

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->clear();
        $recette = static::getContainer()->get(RecetteRepository::class)->findOneBy(['nom' => $nom]);
        self::assertInstanceOf(Recette::class, $recette);
        self::assertSame(
            '<p>Préparer <strong>doucement</strong>.</p><ol><li>Mélanger</li><li>Servir</li></ol>',
            $recette->getDescription(),
        );

        $entityManager->remove($recette);
        $entityManager->flush();
        $denree = $entityManager->find(Denree::class, $denree->getId());
        self::assertInstanceOf(Denree::class, $denree);
        $entityManager->remove($denree);
        $entityManager->flush();
    }

    private function administrateur(): Utilisateur
    {
        $utilisateur = static::getContainer()->get(UtilisateurRepository::class)
            ->findOneBy(['email' => 'admin@scout-market.local']);
        self::assertInstanceOf(Utilisateur::class, $utilisateur);

        return $utilisateur;
    }
}
