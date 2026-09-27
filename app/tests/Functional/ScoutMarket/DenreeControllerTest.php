<?php

declare(strict_types=1);

namespace App\Tests\Functional\ScoutMarket;

use App\Entity\Denree;
use App\Entity\Fournisseur;
use App\Entity\ReferenceFournisseur;
use App\Entity\ReferenceFournisseurConditionnement;
use App\Entity\Unite;
use App\Entity\Utilisateur;
use App\Enum\TypeDenree;
use App\Repository\ReferenceFournisseurConditionnementRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DenreeControllerTest extends WebTestCase
{
    public function testUnNiveauPeutEtreAjouteAvantLesConditionnementsExistants(): void
    {
        $client = static::createClient();
        $client->loginUser($this->administrateur());
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $jeu = $this->creerJeuDeDonnees($em);

        try {
            $crawler = $client->request('GET', '/denrees/'.$jeu['denree']->getId().'/modifier');
            self::assertResponseIsSuccessful();
            self::assertStringContainsString('blur->food-form#validateReference', $client->getResponse()->getContent() ?: '');

            $client->request('POST', '/denrees/'.$jeu['denree']->getId().'/modifier', [
                '_token' => $crawler->filter('input[name="_token"]')->attr('value'),
                'nom' => $jeu['denree']->getNom(),
                'type' => TypeDenree::SEC->value,
                'unite_inventaire' => (string) $jeu['kilogramme']->getId(),
                'fournisseurs' => [[
                    'id' => (string) $jeu['reference']->getId(),
                    'fournisseur' => (string) $jeu['fournisseur']->getId(),
                    'reference' => $jeu['reference']->getReference(),
                    'principal' => '1',
                    'niveaux' => [
                        ['id' => '', 'conditionnement' => (string) $jeu['carton']->getId(), 'quantite' => '6'],
                        ['id' => (string) $jeu['niveauKilogramme']->getId(), 'conditionnement' => (string) $jeu['kilogramme']->getId(), 'quantite' => '1000'],
                        ['id' => (string) $jeu['niveauGramme']->getId(), 'conditionnement' => (string) $jeu['gramme']->getId(), 'quantite' => '1'],
                    ],
                ]],
            ]);

            self::assertResponseRedirects('/denrees');
            $em->clear();
            $reference = $em->find(ReferenceFournisseur::class, $jeu['reference']->getId());
            self::assertInstanceOf(ReferenceFournisseur::class, $reference);
            $niveaux = static::getContainer()->get(ReferenceFournisseurConditionnementRepository::class)->findPourReference($reference);
            self::assertSame([1, 2, 3], array_map(static fn (ReferenceFournisseurConditionnement $niveau): int => $niveau->getOrdre(), $niveaux));
            self::assertSame([$jeu['carton']->getNom(), $jeu['kilogramme']->getNom(), $jeu['gramme']->getNom()], array_map(static fn (ReferenceFournisseurConditionnement $niveau): string => $niveau->getConditionnement()->getNom(), $niveaux));
        } finally {
            $this->supprimerJeuDeDonnees($jeu);
        }
    }

    public function testUneReferenceDejaUtiliseeEstRefuseeAvecLeNomDeLaDenree(): void
    {
        $client = static::createClient();
        $client->loginUser($this->administrateur());
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $jeu = $this->creerJeuDeDonnees($em);
        $denreeExistante = (new Denree())
            ->setNom('Denrée avec référence '.$jeu['suffixe'])
            ->setType(TypeDenree::SEC)
            ->setUniteReference($jeu['gramme'])
            ->setUniteInventaire($jeu['kilogramme']);
        $referenceExistante = new ReferenceFournisseur($jeu['fournisseur'], $denreeExistante, 'REF-'.$jeu['suffixe']);
        $em->persist($denreeExistante);
        $em->persist($referenceExistante);
        $em->flush();
        $jeu['denreeExistante'] = $denreeExistante;
        $jeu['referenceExistante'] = $referenceExistante;

        try {
            $crawler = $client->request('GET', '/denrees/'.$jeu['denree']->getId().'/modifier');
            $client->request('POST', '/denrees/'.$jeu['denree']->getId().'/modifier', [
                '_token' => $crawler->filter('input[name="_token"]')->attr('value'),
                'nom' => $jeu['denree']->getNom(),
                'type' => TypeDenree::SEC->value,
                'unite_inventaire' => (string) $jeu['kilogramme']->getId(),
                'fournisseurs' => [[
                    'id' => (string) $jeu['reference']->getId(),
                    'fournisseur' => (string) $jeu['fournisseur']->getId(),
                    'reference' => $referenceExistante->getReference(),
                    'principal' => '1',
                    'niveaux' => [
                        ['id' => (string) $jeu['niveauKilogramme']->getId(), 'conditionnement' => (string) $jeu['kilogramme']->getId(), 'quantite' => '1000'],
                        ['id' => (string) $jeu['niveauGramme']->getId(), 'conditionnement' => (string) $jeu['gramme']->getId(), 'quantite' => '1'],
                    ],
                ]],
            ]);

            self::assertResponseStatusCodeSame(422);
            self::assertSelectorTextContains('.flash--error', sprintf('La référence fournisseur « %s » est déjà utilisée par la denrée « %s ».', $referenceExistante->getReference(), $denreeExistante->getNom()));
            self::assertStringContainsString($denreeExistante->getNom(), $client->getResponse()->getContent() ?: '');

            $em->clear();
            $reference = $em->find(ReferenceFournisseur::class, $jeu['reference']->getId());
            self::assertInstanceOf(ReferenceFournisseur::class, $reference);
            self::assertSame('INIT-'.$jeu['suffixe'], $reference->getReference());
        } finally {
            $this->supprimerJeuDeDonnees($jeu);
        }
    }

    public function testLaListeAfficheLeTypeEtLaReferenceEtPermetDeTrierParType(): void
    {
        $client = static::createClient();
        $client->loginUser($this->administrateur());
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $jeu = $this->creerJeuDeDonnees($em);
        $denreeFraiche = (new Denree())
            ->setNom('Aliment frais '.$jeu['suffixe'])
            ->setType(TypeDenree::FRAIS)
            ->setUniteReference($jeu['gramme'])
            ->setUniteInventaire($jeu['kilogramme']);
        $em->persist($denreeFraiche);
        $em->flush();
        $jeu['denreeFraiche'] = $denreeFraiche;

        try {
            $crawler = $client->request('GET', '/denrees?tri=type&ordre=asc');

            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('.foods-row--head', 'Type');
            self::assertSelectorTextContains('.foods-row--head', 'Référence');
            self::assertSelectorExists('.foods-sort-link[href*="tri=type"]');
            self::assertSelectorExists(sprintf('[data-food-catalog-target="row"][data-name*="init-%s"]', $jeu['suffixe']));
            self::assertSelectorTextContains(sprintf('[data-name*="init-%s"] .food-reference-code', $jeu['suffixe']), 'INIT-'.$jeu['suffixe']);

            $noms = $crawler->filter('.foods-swipe-row .food-name strong')->each(static fn ($noeud): string => $noeud->text());
            $positionFrais = array_search($denreeFraiche->getNom(), $noms, true);
            $positionSec = array_search($jeu['denree']->getNom(), $noms, true);
            self::assertIsInt($positionFrais);
            self::assertIsInt($positionSec);
            self::assertLessThan($positionSec, $positionFrais);
        } finally {
            $this->supprimerJeuDeDonnees($jeu);
        }
    }

    /** @return array<string, object|string> */
    private function creerJeuDeDonnees(EntityManagerInterface $em): array
    {
        $suffixe = bin2hex(random_bytes(4));
        $gramme = new Unite('gramme '.$suffixe, 'g'.$suffixe);
        $kilogramme = new Unite('kilogramme '.$suffixe, 'k'.$suffixe);
        $carton = new Unite('carton '.$suffixe, 'c'.$suffixe);
        $fournisseur = new Fournisseur('Fournisseur '.$suffixe);
        $denree = (new Denree())
            ->setNom('Denrée à modifier '.$suffixe)
            ->setType(TypeDenree::SEC)
            ->setUniteReference($gramme)
            ->setUniteInventaire($kilogramme);
        $reference = (new ReferenceFournisseur($fournisseur, $denree, 'INIT-'.$suffixe))->setPrincipal(true);
        $niveauKilogramme = new ReferenceFournisseurConditionnement($reference, 1, $kilogramme->getNom(), '1000', null, $gramme->getNom(), $kilogramme);
        $niveauGramme = new ReferenceFournisseurConditionnement($reference, 2, $gramme->getNom(), '1', $gramme, null, $gramme);

        foreach ([$gramme, $kilogramme, $carton, $fournisseur, $denree, $reference, $niveauKilogramme, $niveauGramme] as $entite) {
            $em->persist($entite);
        }
        $em->flush();

        return compact('suffixe', 'gramme', 'kilogramme', 'carton', 'fournisseur', 'denree', 'reference', 'niveauKilogramme', 'niveauGramme');
    }

    /** @param array<string, object|string> $jeu */
    private function supprimerJeuDeDonnees(array $jeu): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        foreach (['referenceExistante', 'reference'] as $cle) {
            if (isset($jeu[$cle]) && method_exists($jeu[$cle], 'getId')) {
                $reference = $em->find(ReferenceFournisseur::class, $jeu[$cle]->getId());
                if (null !== $reference) {
                    foreach (static::getContainer()->get(ReferenceFournisseurConditionnementRepository::class)->findPourReference($reference) as $niveau) {
                        $em->remove($niveau);
                    }
                    $em->remove($reference);
                }
            }
        }
        $em->flush();
        foreach (['denreeExistante', 'denreeFraiche', 'denree'] as $cle) {
            if (isset($jeu[$cle]) && method_exists($jeu[$cle], 'getId')) {
                $entite = $em->find(Denree::class, $jeu[$cle]->getId());
                if (null !== $entite) {
                    $em->remove($entite);
                }
            }
        }
        $fournisseur = $em->find(Fournisseur::class, $jeu['fournisseur']->getId());
        if (null !== $fournisseur) {
            $em->remove($fournisseur);
        }
        foreach (['gramme', 'kilogramme', 'carton'] as $cle) {
            $unite = $em->find(Unite::class, $jeu[$cle]->getId());
            if (null !== $unite) {
                $em->remove($unite);
            }
        }
        $em->flush();
    }

    private function administrateur(): Utilisateur
    {
        $utilisateur = static::getContainer()->get(UtilisateurRepository::class)->findOneBy(['email' => 'admin@scout-market.local']);
        self::assertInstanceOf(Utilisateur::class, $utilisateur);

        return $utilisateur;
    }
}
