<?php

declare(strict_types=1);

namespace App\Tests\Functional\ScoutMarket;

use App\Entity\GrilleMenu;
use App\Entity\Groupe;
use App\Entity\Menu;
use App\Entity\Utilisateur;
use App\Enum\ModeRepasGroupe;
use App\Enum\TypeDistributionMenu;
use App\Repository\GrilleMenuRepository;
use App\Repository\GroupeRepasRepository;
use App\Repository\MenuRepository;
use App\Repository\TypeRepasRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ScoutMarketTest extends WebTestCase
{
    public function testLaNavigationEstRecentreeSurLIntendanceSansSejour(): void
    {
        $client = static::createClient();
        $client->loginUser($this->administrateur());
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.sidebar__nav', 'Fournisseurs');
        self::assertSelectorTextContains('.sidebar__nav', 'Denrées');
        self::assertSelectorTextContains('.sidebar__nav', 'Recettes');
        self::assertSelectorTextContains('.sidebar__nav', 'Menus');
        self::assertSelectorTextContains('.sidebar__nav', 'Mouvements de stock');
        self::assertSelectorTextContains('.sidebar__nav', 'Distribution');
        self::assertSelectorTextContains('.sidebar__nav', 'Commande');
        self::assertSelectorTextContains('.sidebar__nav', 'Unités participantes');
        self::assertSelectorTextContains('.sidebar__nav', 'Utilisateurs');
        self::assertSelectorTextNotContains('body', 'Séjour actif');
        self::assertSelectorTextContains('.home-kpis--summary', 'recettes actives');
        self::assertSelectorExists('.home-kpis--summary a[href="/recettes"]');
        self::assertSelectorTextNotContains('.home-kpis--summary', 'stocks suivis');

        $client->request('GET', '/sejours');
        self::assertResponseStatusCodeSame(404);
    }

    public function testUneGrilleDeMenuPossedeUnLibelleEtUnePeriodeModifiables(): void
    {
        $client = static::createClient();
        $client->loginUser($this->administrateur());
        $suffixe = bin2hex(random_bytes(4));
        $labelInitial = 'Grille test '.$suffixe;
        $labelFinal = 'Grille annuelle '.$suffixe;

        $crawler = $client->request('GET', '/menus/grilles/ajouter');
        $client->submit($crawler->selectButton('Créer la grille')->form([
            'label' => $labelInitial,
            'date_debut' => '2027-01-10',
            'date_fin' => '2027-01-20',
        ]));
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('h1', $labelInitial);
        self::assertSelectorTextContains('.menus-heading', '10/01/2027 — 20/01/2027');
        self::assertSelectorExists('a[href$="/parametres"]');

        $grille = static::getContainer()->get(GrilleMenuRepository::class)->findOneBy(['label' => $labelInitial]);
        self::assertInstanceOf(GrilleMenu::class, $grille);

        $crawler = $client->request('GET', '/menus/grilles/'.$grille->getId().'/parametres');
        $client->submit($crawler->selectButton('Enregistrer')->form([
            'label' => $labelFinal,
            'date_debut' => '2027-02-01',
            'date_fin' => '2027-12-31',
        ]));
        self::assertResponseRedirects('/menus/grilles/'.$grille->getId());
        $client->followRedirect();
        self::assertSelectorTextContains('h1', $labelFinal);
        self::assertSelectorTextContains('.menus-heading', '01/02/2027 — 31/12/2027');

        $typeRepas = static::getContainer()->get(TypeRepasRepository::class)->findActifs();
        $valeurs = [];
        foreach ($typeRepas as $index => $type) {
            $valeurs[sprintf('repas[%s][type_distribution]', $type->getId())] = 0 === $index
                ? TypeDistributionMenu::EN_CAISSE->value
                : TypeDistributionMenu::SCOUT_MARKET->value;
        }
        $client->submit($client->getCrawler()->selectButton('Enregistrer la journée')->form($valeurs));
        self::assertResponseRedirects();
        $menus = static::getContainer()->get(MenuRepository::class)->findPourDateGrille($grille, new \DateTimeImmutable('2027-02-01'));
        self::assertCount(count($typeRepas), $menus);
        $modes = array_map(static fn (Menu $menu): TypeDistributionMenu => $menu->getTypeDistribution(), $menus);
        self::assertContains(TypeDistributionMenu::EN_CAISSE, $modes);
        self::assertContains(TypeDistributionMenu::SCOUT_MARKET, $modes);

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $grillePersistante = $entityManager->find(GrilleMenu::class, $grille->getId());
        self::assertInstanceOf(GrilleMenu::class, $grillePersistante);
        $entityManager->remove($grillePersistante);
        $entityManager->flush();
    }

    public function testLeTableauDeBordAfficheLesEffectifsEtBesoinsDesUnitesPresentes(): void
    {
        $client = static::createClient();
        $client->loginUser($this->administrateur());
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $aujourdhui = new \DateTimeImmutable('today');
        $unite = (new Groupe())
            ->setNom('Unité tableau de bord '.bin2hex(random_bytes(4)))
            ->setType('test')
            ->setEffectifJeune(18)
            ->setEffectifAdulte(4)
            ->setNombreVegetariens(3)
            ->setNombreSansGluten(2)
            ->setNombreSansLactose(1)
            ->setDateDebutPresence($aujourdhui)
            ->setDateFinPresence($aujourdhui);
        $entityManager->persist($unite);
        $entityManager->flush();
        $uniteId = (string) $unite->getId();

        try {
            $client->request('GET', '/');

            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('#unites-presentes-title', 'Unités présentes aujourd’hui');
            self::assertSelectorTextContains('.home-attendance-row--head', 'Végétariens');
            self::assertSelectorTextContains('.home-attendance-row--head', 'Sans gluten');
            self::assertSelectorTextContains('.home-attendance-row--head', 'Sans lactose');
            self::assertSelectorTextSame(sprintf('[data-unit-id="%s"] .home-attendance__young', $uniteId), '18');
            self::assertSelectorTextSame(sprintf('[data-unit-id="%s"] .home-attendance__adults', $uniteId), '4');
            self::assertSelectorTextSame(sprintf('[data-unit-id="%s"] .home-attendance__total', $uniteId), '22');
            self::assertSelectorTextSame(sprintf('[data-unit-id="%s"] .home-attendance__vegetarians', $uniteId), '3');
            self::assertSelectorTextSame(sprintf('[data-unit-id="%s"] .home-attendance__gluten-free', $uniteId), '2');
            self::assertSelectorTextSame(sprintf('[data-unit-id="%s"] .home-attendance__lactose-free', $uniteId), '1');
        } finally {
            $entityManager = static::getContainer()->get(EntityManagerInterface::class);
            $unitePersistante = $entityManager->find(Groupe::class, $uniteId);
            if (null !== $unitePersistante) {
                $entityManager->remove($unitePersistante);
                $entityManager->flush();
            }
        }
    }

    public function testLaDistributionProposeLesDeuxSousPagesSansModifierLesRepasSpecifiques(): void
    {
        $client = static::createClient();
        $client->loginUser($this->administrateur());

        $client->request('GET', '/intendance/distribution/scout-market');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.distribution-tabs', 'Scout Market');
        self::assertSelectorTextContains('.distribution-tabs', 'En caisse');
        self::assertSelectorTextContains('.order-calculation-note', 'Explo, pique-nique et repas non pris');

        $client->request('GET', '/intendance/distribution/en-caisse');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Distribution');
        self::assertSelectorTextContains('.order-calculation-note', 'produits frais, fruits et légumes');
        self::assertSelectorTextContains('.order-calculation-note', 'Explo, pique-nique et repas non pris');
    }

    public function testLaFicheUnitePermetDeDeclarerLesRepasSpecifiques(): void
    {
        $client = static::createClient();
        $client->loginUser($this->administrateur());
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $typeRepasRepository = static::getContainer()->get(TypeRepasRepository::class);
        $suffixe = bin2hex(random_bytes(4));
        $debut = new \DateTimeImmutable('2027-03-01');
        $fin = new \DateTimeImmutable('2027-03-03');
        $grille = new GrilleMenu('Grille repas unité '.$suffixe, $debut, $fin);
        $groupe = (new Groupe())
            ->setGrilleMenu($grille)
            ->setNom('Unité repas '.$suffixe)
            ->setType('farfadets')
            ->setEffectifJeune(18)
            ->setEffectifAdulte(4)
            ->setDateDebutPresence($debut)
            ->setDateFinPresence($fin);
        $menus = [];
        foreach (['PETIT_DEJEUNER', 'DEJEUNER', 'GOUTER', 'DINER'] as $code) {
            $typeRepas = $typeRepasRepository->findOneBy(['code' => $code]);
            self::assertNotNull($typeRepas);
            $menus[] = (new Menu())
                ->setGrilleMenu($grille)
                ->setTypeRepas($typeRepas)
                ->setDateMenu($debut);
        }
        $entityManager->persist($grille);
        $entityManager->persist($groupe);
        foreach ($menus as $menu) {
            $entityManager->persist($menu);
        }
        $entityManager->flush();
        $groupeId = (string) $groupe->getId();

        try {
            $crawler = $client->request('GET', '/groupes/'.$groupeId.'/modifier');

            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('.group-meals-section', 'Repas d’explo');
            self::assertSelectorTextContains('.group-meals-section', 'Pique-nique 1');
            self::assertSelectorTextContains('.group-meals-section', 'Pique-nique 2');
            self::assertSelectorTextContains('.group-meals-section', 'Repas non pris');
            self::assertSelectorCount(4, 'select[name="repas_explo[]"] option');

            $client->submit($crawler->selectButton('Enregistrer')->form([
                'repas_explo' => [(string) $menus[0]->getId()],
                'repas_pique_nique_1' => [(string) $menus[1]->getId()],
                'repas_pique_nique_2' => [(string) $menus[2]->getId()],
                'repas_non_pris' => [(string) $menus[3]->getId()],
            ]));
            self::assertResponseRedirects('/groupes');

            $entityManager->clear();
            $groupePersistant = $entityManager->find(Groupe::class, $groupeId);
            self::assertInstanceOf(Groupe::class, $groupePersistant);
            $configurations = static::getContainer()->get(GroupeRepasRepository::class)->findPourGroupe($groupePersistant);
            self::assertCount(4, $configurations);
            $modesParMenu = [];
            foreach ($configurations as $configuration) {
                $modesParMenu[(string) $configuration->getMenu()->getId()] = $configuration->getMode();
            }
            self::assertSame(ModeRepasGroupe::EXPLO, $modesParMenu[(string) $menus[0]->getId()]);
            self::assertSame(ModeRepasGroupe::PIQUE_NIQUE_1, $modesParMenu[(string) $menus[1]->getId()]);
            self::assertSame(ModeRepasGroupe::PIQUE_NIQUE_2, $modesParMenu[(string) $menus[2]->getId()]);
            self::assertSame(ModeRepasGroupe::NON_PRIS, $modesParMenu[(string) $menus[3]->getId()]);
        } finally {
            $entityManager->clear();
            $groupePersistant = $entityManager->find(Groupe::class, $groupeId);
            if (null !== $groupePersistant) {
                $entityManager->remove($groupePersistant);
                $entityManager->flush();
            }
            $grillePersistante = $entityManager->find(GrilleMenu::class, $grille->getId());
            if (null !== $grillePersistante) {
                $entityManager->remove($grillePersistante);
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
