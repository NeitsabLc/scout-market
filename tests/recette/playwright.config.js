import { defineConfig, devices } from '@playwright/test';

// Suite lancée à la main par `verificateur` (mode recette) avant une promotion,
// jamais en CI ni par `npm run test:e2e` / `npm run test:accessibility`.
const url = process.env.RECETTE_URL;
const manquantes = ['RECETTE_URL', 'RECETTE_EMAIL', 'RECETTE_MOT_DE_PASSE'].filter((nom) => !process.env[nom]);

if (process.env.CI) {
  throw new Error('Refus de démarrer : cette suite ne se lance jamais en CI.');
}

if (manquantes.length > 0) {
  throw new Error(`Variables d’environnement manquantes : ${manquantes.join(', ')}. Voir docs/context/infra.md.`);
}

try {
  const adresse = new URL(url);
  if (adresse.protocol !== 'https:') {
    throw new Error('protocole');
  }
  if (!/(^|[.-])recette([.-]|$)/.test(adresse.hostname)) {
    throw new Error('hôte');
  }
} catch (erreur) {
  if (erreur.message === 'hôte') {
    throw new Error('Refus de démarrer : le nom d’hôte de RECETTE_URL doit contenir le segment « recette » (protection de la production).');
  }
  throw new Error('RECETTE_URL doit être une URL HTTPS valide.');
}

// En cas d'échec, Playwright écrit `error-context.md` sous test-results/ (ignoré par Git).
// Cette variable supprime l'instantané ARIA de toute la page ; le fichier garde le message
// d'erreur et l'extrait ARIA de l'élément testé.
process.env.PLAYWRIGHT_NO_COPY_PROMPT = '1';

// Marqueur lu par la spec : sans cette configuration, la suite ne s'exécute jamais.
process.env.SCOUT_MARKET_RECETTE_CONFIG = '1';

export default defineConfig({
  testDir: '.',
  testMatch: '**/*.spec.js',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: 'list',
  outputDir: '../../test-results/recette',
  use: {
    baseURL: url,
    // Pas de trace ni de vidéo : elles enregistreraient le mot de passe saisi.
    // Pas de capture : les pages affichent des données de la recette.
    screenshot: 'off',
    trace: 'off',
    video: 'off',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
