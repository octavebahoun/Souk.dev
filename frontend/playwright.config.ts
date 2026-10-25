import { defineConfig, devices } from '@playwright/test'

/**
 * Parcours de bout en bout, captures et audits axe, contre la plateforme lancée par
 * `docker compose up` (le jeu de démo chargé : php artisan db:seed --class=DemoSeeder).
 */
export default defineConfig({
  testDir: './e2e',
  timeout: 120_000,
  expect: { timeout: 15_000 },
  fullyParallel: false,
  workers: 1,
  reporter: [['list']],
  use: {
    baseURL: process.env.E2E_URL || 'http://localhost:5173',
    locale: 'fr-FR',
    trace: 'retain-on-failure',
  },
  projects: [
    { name: 'parcours', testIgnore: /captures\.spec\.ts/, use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } } },
    { name: 'captures', testMatch: /captures\.spec\.ts/, use: { ...devices['Desktop Chrome'] } },
  ],
})
