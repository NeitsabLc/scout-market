import { expect } from '@playwright/test';

export const motDePasse = 'ScoutMarket?2026!';

export const comptes = {
  administrateur: 'admin@scout-market.local',
};

export async function seConnecter(page, email) {
  await page.goto('/login');
  await page.getByLabel('Adresse e-mail').fill(email);
  await page.getByLabel('Mot de passe', { exact: true }).fill(motDePasse);
  await page.getByRole('button', { name: 'Se connecter' }).click();

  await expect(page).not.toHaveURL(/\/login$/);
  await expect(page.locator('.user-summary')).toBeVisible();
}

export async function seDeconnecter(page) {
  await page.getByRole('button', { name: 'Se déconnecter' }).click();

  await expect(page).toHaveURL(/\/login$/);
  await expect(page.getByRole('heading', { name: 'Ravi de vous revoir' })).toBeVisible();
}

export function suffixeUnique() {
  const execution = process.env.SCOUT_MARKET_E2E_RUN_ID ?? Date.now().toString();

  return `${execution}-${Math.random().toString(16).slice(2, 8)}`;
}
