import { expect, test } from '@playwright/test';
import { comptes, motDePasse, seConnecter, suffixeUnique } from './helpers.js';

test('une page protégée redirige vers la connexion', async ({ page }) => {
  await page.goto('/denrees');
  await expect(page).toHaveURL(/\/login$/);
  await expect(page.getByRole('heading', { name: 'Ravi de vous revoir' })).toBeVisible();
});

test('des identifiants incorrects sont refusés', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Adresse e-mail').fill('inconnu@example.test');
  await page.getByLabel('Mot de passe', { exact: true }).fill(`${motDePasse}-incorrect`);
  await page.getByRole('button', { name: 'Se connecter' }).click();
  await expect(page.getByRole('alert')).toHaveText('Identifiants incorrects.');
});

test('la navigation Scout Market regroupe les fonctions d’intendance', async ({ page }) => {
  await seConnecter(page, comptes.administrateur);
  const navigation = page.getByRole('navigation', { name: 'Navigation principale' });

  for (const groupe of ['Catalogue', 'Préparation', 'Flux', 'Gestion']) {
    await expect(navigation.getByText(groupe, { exact: true }).first()).toBeVisible();
  }
  await expect(page.locator('body')).not.toContainText('Séjour actif');
  expect((await page.goto('/sejours'))?.status()).toBe(404);
});

test('une grille de menus datée peut être créée et modifiée', async ({ page }) => {
  const suffixe = suffixeUnique();
  const label = `Grille E2E ${suffixe}`;
  await seConnecter(page, comptes.administrateur);
  await page.goto('/menus/grilles/ajouter');
  await page.getByLabel('Libellé de la grille').fill(label);
  await page.getByLabel('Date de début').fill('2027-01-01');
  await page.getByLabel('Date de fin').fill('2027-12-31');
  await page.getByRole('button', { name: 'Créer la grille' }).click();

  await expect(page.getByRole('heading', { name: label })).toBeVisible();
  await expect(page.locator('.menus-heading')).toContainText('01/01/2027 — 31/12/2027');
  await page.getByRole('link', { name: 'Modifier le libellé et les dates' }).click();
  await page.getByLabel('Libellé de la grille').fill(`${label} modifiée`);
  await page.getByRole('button', { name: 'Enregistrer' }).click();
  await expect(page.getByRole('heading', { name: `${label} modifiée` })).toBeVisible();
});

test('les distributions Scout Market et en caisse présentent les besoins attendus', async ({ page }) => {
  await seConnecter(page, comptes.administrateur);
  await page.goto('/intendance/distribution/scout-market');

  await expect(page.getByRole('navigation', { name: 'Modes de distribution' })).toBeVisible();
  await expect(page.getByText('Quantités totales à sortir au comptoir')).toBeVisible();
  await page.locator('.order-meal-card summary').first().click();
  await expect(page.getByRole('columnheader', { name: 'Quantité totale à sortir' })).toBeVisible();

  await page.getByRole('link', { name: 'En caisse' }).click();
  await expect(page.getByText('Produits frais, fruits et légumes à regrouper dans la caisse quotidienne de chaque unité.')).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Produits secs à livrer' })).toBeVisible();
  await expect(page.locator('.dry-delivery-card')).toContainText('Pâtes');
  await expect(page.getByRole('heading', { name: 'Grille École des bois' }).first()).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Farfadets de la Clairière' }).first()).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Déjeuner italien' })).toHaveCount(0);
  await expect(page.locator('main')).not.toContainText('Composition : Explo');
  await expect(page.locator('.crate-menus').filter({ hasText: 'Pâtes' })).toHaveCount(0);

  const livraisonSeche = page.locator('.dry-delivery-card');
  await livraisonSeche.locator('summary').click();
  await expect(livraisonSeche.locator('table')).not.toBeVisible();

  const premiereJournee = page.locator('.order-meal-card').first();
  await premiereJournee.locator('summary').click();
  await expect(premiereJournee.locator('.crate-menus')).not.toBeVisible();
});

test('la commande tient compte des livraisons déjà effectuées', async ({ page }) => {
  await seConnecter(page, comptes.administrateur);
  await page.goto('/intendance/commande');

  const secDejaLivre = page.getByRole('checkbox', { name: 'Sec en caisse déjà livrée' });
  const fraisDejaLivre = page.getByRole('checkbox', { name: 'Frais de la journée déjà livré' });
  await expect(secDejaLivre).toBeVisible();
  await expect(fraisDejaLivre).toBeVisible();
  await secDejaLivre.check();
  await fraisDejaLivre.check();
  await page.getByRole('button', { name: 'Calculer la commande' }).click();

  await expect(secDejaLivre).toBeChecked();
  await expect(fraisDejaLivre).toBeChecked();
  await expect(page.locator('.final-order-summary')).toContainText('Sec des grilles en caisse déjà livré');
  await expect(page.locator('.final-order-summary')).toContainText('Journée initiale déjà livrée');
});

test('@compatibilite la connexion et les menus fonctionnent dans Firefox', async ({ page }) => {
  await seConnecter(page, comptes.administrateur);
  await page.goto('/menus');
  await expect(page.getByRole('heading', { name: 'Menus', exact: true })).toBeVisible();
});

test('@mobile la navigation donne accès aux unités participantes', async ({ page }) => {
  await page.setViewportSize({ width: 393, height: 851 });
  await seConnecter(page, comptes.administrateur);
  await page.getByRole('button', { name: 'Ouvrir le menu' }).click();
  await page.getByText('Gestion', { exact: true }).first().click();
  await page.getByRole('link', { name: /Unités participantes/ }).click();
  await expect(page).toHaveURL(/\/groupes$/);
  await expect(page.getByRole('heading', { name: 'Unités participantes' })).toBeVisible();
});
