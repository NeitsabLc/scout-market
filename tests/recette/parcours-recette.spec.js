import { expect, test } from '@playwright/test';
import { seDeconnecter } from '../e2e/helpers.js';

const email = process.env.RECETTE_EMAIL;
const motDePasse = process.env.RECETTE_MOT_DE_PASSE;
const repere = 'Contrôle recette automatique';

// Garde-fou en plus de la configuration : jamais de parcours hors lancement explicite sur la recette.
test.skip(
  !process.env.SCOUT_MARKET_RECETTE_CONFIG || !process.env.RECETTE_URL || !!process.env.CI,
  'Suite réservée au lancement manuel sur la recette (make test-recette).',
);

test.describe.configure({ mode: 'serial' });

// Connexion avec le compte de contrôle (le mot de passe vient de l'environnement, pas des helpers e2e).
async function seConnecterRecette(page) {
  await page.goto('/login');
  await page.getByLabel('Adresse e-mail').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(motDePasse);
  await page.getByRole('button', { name: 'Se connecter' }).click();
  await expect(page).not.toHaveURL(/\/login$/);
  await expect(page.locator('.user-summary')).toBeVisible();
}

// Surveille les réponses serveur en erreur pendant un test.
function surveillerErreursServeur(page) {
  const erreurs = [];
  page.on('response', (reponse) => {
    if (reponse.status() >= 500) {
      erreurs.push(`${reponse.status()} ${new URL(reponse.url()).pathname}`);
    }
  });
  return erreurs;
}

async function verifierPage(page, chemin, titre) {
  const reponse = await page.goto(chemin);
  expect(reponse.status(), `${chemin} répond`).toBeLessThan(400);
  await expect(page.getByRole('heading', { level: 1 })).toContainText(titre);
  await expect(page.locator('body')).not.toContainText(/Exception|Stack trace/);
}

// Premier lien de la page dont l'adresse correspond au motif (sans dépendre d'un libellé de donnée).
async function premierLien(page, motif) {
  const liens = await page.locator('main a[href]').evaluateAll((noeuds) => noeuds.map((n) => n.getAttribute('href')));
  return liens.find((lien) => motif.test(lien)) ?? null;
}

// Ouvre le formulaire d'édition du premier objet d'une liste, sans rien enregistrer.
async function ouvrirFormulaireEdition(page, liste, motifLien) {
  await verifierPage(page, liste, /./);
  const lien = await premierLien(page, motifLien);
  expect(lien, `${liste} : aucun objet à ouvrir dans le jeu de recette`).not.toBeNull();
  const reponse = await page.goto(lien);
  expect(reponse.status(), `${lien} répond`).toBeLessThan(400);
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  await expect(page.locator('main form').first()).toBeVisible();
  await expect(page.locator('body')).not.toContainText(/Exception|Stack trace/);
}

function liensAnnulation(page) {
  return page.locator('a.button-link[href$="/annuler"]');
}

async function adressesAnnulation(page) {
  await page.goto('/stocks');
  await expect(page.getByRole('heading', { level: 1 })).toContainText('Mouvements de stock');
  return liensAnnulation(page).evaluateAll((noeuds) => noeuds.map((n) => n.getAttribute('href')));
}

test('lecture : parcours des pages principales', async ({ page }) => {
  const erreurs = surveillerErreursServeur(page);
  await seConnecterRecette(page);

  const pages = [
    ['/', 'Tableau de bord'],
    ['/denrees', 'Denrées'],
    ['/fournisseurs', 'Fournisseurs'],
    ['/recettes', 'Recettes'],
    ['/menus', /\S/],
    ['/groupes', 'Unités participantes'],
    ['/stocks', 'Mouvements de stock'],
    ['/stocks/mouvement', 'Ajouter un mouvement de stock'],
    ['/utilisateurs', 'Utilisateurs'],
    ['/intendance/commande', 'Commande'],
    ['/intendance/distribution', 'Distribution'],
    ['/intendance/distribution/configuration', 'Configuration distribution'],
    ['/mon-profil', 'Mon profil'],
  ];
  for (const [chemin, titre] of pages) {
    await verifierPage(page, chemin, titre);
  }

  await page.goto('/');
  await seDeconnecter(page);
  expect(erreurs).toEqual([]);
});

test('lecture : ouverture des formulaires d’édition sans enregistrer', async ({ page }) => {
  const erreurs = surveillerErreursServeur(page);
  await seConnecterRecette(page);

  // Formulaires à sous-collections (conditionnements, ingrédients, effectifs, dates de présence).
  await ouvrirFormulaireEdition(page, '/denrees', /^\/denrees\/[^/]+\/modifier/);
  await ouvrirFormulaireEdition(page, '/fournisseurs', /^\/fournisseurs\/[^/]+\/modifier/);
  await ouvrirFormulaireEdition(page, '/recettes', /^\/recettes\/[^/]+\/modifier/);
  await ouvrirFormulaireEdition(page, '/groupes', /^\/groupes\/[^/]+\/modifier/);
  await ouvrirFormulaireEdition(page, '/utilisateurs', /^\/utilisateurs\/[^/]+\/modifier/);

  // Grille de menus : paramètres de la première grille, lue depuis /menus.
  await page.goto('/menus');
  const grille = await premierLien(page, /^\/menus\/grilles\/[0-9a-fA-F-]{36}(\/parametres)?$/);
  expect(grille, '/menus : aucune grille dans le jeu de recette').not.toBeNull();
  const idGrille = grille.match(/[0-9a-fA-F-]{36}/)[0];
  for (const chemin of [`/menus/grilles/${idGrille}`, `/menus/grilles/${idGrille}/speciaux`, `/menus/grilles/${idGrille}/parametres`]) {
    const reponse = await page.goto(chemin);
    expect(reponse.status(), `${chemin} répond`).toBeLessThan(400);
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
    await expect(page.locator('body')).not.toContainText(/Exception|Stack trace/);
  }

  // Un identifiant inconnu donne un refus propre, pas une erreur serveur.
  const inconnu = await page.goto('/denrees/00000000-0000-7000-8000-000000000000/modifier');
  expect(inconnu.status()).toBe(404);

  await page.goto('/');
  await seDeconnecter(page);
  expect(erreurs).toEqual([]);
});

test.describe('écriture : mouvement de stock', () => {
  // Adresses d'annulation des mouvements actifs avant le test.
  let avant = null;
  // Adresse d'annulation du mouvement créé, dès qu'elle est identifiée.
  let adresseControle = null;

  test.afterEach(async ({ page }) => {
    // Nettoyage garanti, même après un échec ; n'annule que le mouvement de contrôle créé par le test.
    if (null === avant) {
      return;
    }
    await page.goto('/login');
    if (new URL(page.url()).pathname === '/login') {
      await seConnecterRecette(page);
    }
    const etaitConnu = null !== adresseControle;
    const adresseCreee = adresseControle;
    const inattendus = [];
    const candidats = etaitConnu
      ? [adresseControle]
      : (await adressesAnnulation(page)).filter((adresse) => !avant.includes(adresse));
    for (const adresse of candidats) {
      const reponse = await page.goto(adresse);
      if (reponse.status() === 404) {
        continue; // déjà annulé
      }
      // Identité inconnue : on n'annule que ce qui ressemble au mouvement de contrôle.
      const estControle = etaitConnu || (await page.locator('main p', { hasText: 'Entrée · Correction manuelle' }).count()) > 0;
      if (!estControle) {
        inattendus.push(adresse);
        continue;
      }
      await page.getByLabel('Motif').fill(`${repere} : nettoyage`);
      await page.getByRole('button', { name: 'Confirmer l’annulation' }).click();
      await expect(page.getByRole('status')).toContainText('Le mouvement a été annulé');
    }
    const restants = (await adressesAnnulation(page)).filter((adresse) => !avant.includes(adresse));
    avant = null;
    adresseControle = null;
    expect(inattendus, 'nouveau mouvement actif non identifié comme mouvement de contrôle : laissé en place').toEqual([]);
    if (etaitConnu) {
      expect(restants, 'le mouvement de contrôle est encore actif').not.toContain(adresseCreee);
    } else {
      expect(restants, 'nouveau mouvement actif après nettoyage (créé par le test ou par un tiers)').toEqual([]);
    }
  });

  test('création, modification et annulation', async ({ page }) => {
    const erreurs = surveillerErreursServeur(page);
    await seConnecterRecette(page);
    avant = await adressesAnnulation(page);

    // Création : une entrée « Correction manuelle » (ni fournisseur ni inventaire requis).
    await page.goto('/stocks/mouvement');
    await page.locator('.movement-type-switch label').filter({ hasText: 'Entrée' }).click();
    await page.getByLabel(/Origine du mouvement/).selectOption({ label: 'Correction manuelle' });
    const champDenree = page.locator('.movement-line-food').first();
    await champDenree.locator('.searchable-select__trigger').click();
    await expect(champDenree.locator('.searchable-select__option'), 'aucune denrée active dans la recette').not.toHaveCount(0);
    await champDenree.locator('.searchable-select__option').first().click();
    const conditionnement = page.locator('select[data-line-exit-unit]').first();
    await expect(conditionnement.locator('option')).not.toHaveCount(1);
    if ('' === (await conditionnement.inputValue())) {
      await conditionnement.selectOption({ index: 1 });
    }
    await page.locator('input[data-line-quantity]').first().fill('1');
    await page.getByRole('button', { name: 'Enregistrer le mouvement' }).click();
    await expect(page.getByRole('status')).toContainText('Mouvement de stock enregistré');

    // Identification du mouvement créé : la seule adresse d'annulation nouvelle.
    const apres = await adressesAnnulation(page);
    const nouvelles = apres.filter((adresse) => !avant.includes(adresse));
    expect(nouvelles, 'un seul nouveau mouvement actif attendu').toHaveLength(1);
    adresseControle = nouvelles[0];

    // Modification : le motif porte le repère.
    await page.goto('/stocks');
    const ligneControle = page.locator('.stock-swipe-row').filter({ has: page.locator(`a[href="${adresseControle}"]`) });
    const adresseModification = await ligneControle.locator('a.stock-edit-button').first().getAttribute('href');
    expect(adresseModification, 'lien de modification du mouvement créé').toMatch(/\/stocks\/mouvement\/[^/?]+\?ligne=/);
    await page.goto(adresseModification);
    await expect(page.getByRole('button', { name: 'Enregistrer les modifications' })).toBeVisible();
    await page.locator('input[data-line-quantity]').first().fill('2');
    await page.locator('textarea[name="motif_audit"]').fill(`${repere} : modification`);
    await page.getByRole('button', { name: 'Enregistrer les modifications' }).click();
    await expect(page.getByRole('status')).toContainText('Mouvement de stock modifié');

    // Annulation : le mouvement reste dans le journal, marqué « Annulé ».
    await page.goto(adresseControle);
    await page.getByLabel('Motif').fill(`${repere} : annulation`);
    await page.getByRole('button', { name: 'Confirmer l’annulation' }).click();
    await expect(page.getByRole('status')).toContainText('Le mouvement a été annulé');
    expect(await adressesAnnulation(page)).not.toContain(adresseControle);
    await expect(page.locator('.stock-list-row').filter({ hasText: 'Annulé' })).not.toHaveCount(0);
    expect(erreurs).toEqual([]);
  });
});
