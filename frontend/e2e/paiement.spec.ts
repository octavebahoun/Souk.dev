import { execFileSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'
import { expect, test, type Page } from '@playwright/test'
import { auditAxe, connexionDemo, resumer } from './outils'

/**
 * Phase 2 — paiement de bout en bout, contre la plateforme Docker (paiement simulé, moteur réel) :
 * un client paie et sa copie démarre ; un impayé est suspendu puis réactivé au paiement ; un
 * paiement refusé s'explique et se retente ; un dev est reversé (simulé) ; l'admin voit ses finances.
 */

const racine = fileURLToPath(new URL('../../', import.meta.url))

/** Commande artisan dans le conteneur de l'API (développement seulement). */
function artisan(...arguments_: string[]): string {
  return execFileSync('docker', ['compose', 'exec', '-T', '-u', '1000', 'api', 'php', 'artisan', ...arguments_], { cwd: racine, encoding: 'utf8' })
}

/** Ouvre l'adresse d'une copie jusqu'à voir le titre attendu (le proxy recharge ses routes). */
async function ouvrirLaCopie(page: Page, adresse: string, titre: string) {
  await expect(async () => {
    await page.goto(adresse)
    await expect(page.getByRole('heading', { name: titre })).toBeVisible({ timeout: 3_000 })
  }).toPass({ timeout: 30_000 })
}

async function payerSurLeSimulateur(page: Page, issue: 'Payer (succès simulé)' | 'Refuser (échec simulé)') {
  await expect(page).toHaveURL(/\/simulateur\/saspay\//)
  await expect(page.getByText('Simulateur SasPay — aucun argent réel n’est débité.')).toBeVisible()
  await page.getByRole('button', { name: issue }).click()
}

test.describe.serial('paiement', () => {
  let copie = 0

  test.beforeAll(() => {
    artisan('db:seed', '--class=DemoSeeder', '--force')
  })

  test('un client commande une copie, paie, et la voit en ligne', async ({ page }) => {
    test.setTimeout(420_000)
    await connexionDemo(page, 'soukdemo-pharmacie')
    await page.goto('/store')
    await page.getByText('PharmaStock', { exact: true }).first().click()
    await page.getByRole('link', { name: 'Déployer ma copie' }).click()

    await expect(page.getByRole('heading', { level: 1, name: 'Commander votre copie de PharmaStock' })).toBeVisible()
    // Une commande attend déjà le paiement (jeu de démo) : on la paie au lieu d'en créer une autre.
    await expect(page.getByText('Une commande attend déjà votre paiement')).toBeVisible()
    expect(resumer(await auditAxe(page))).toBe('')
    await page.getByRole('button', { name: 'Payer 22 000 F' }).click()

    await payerSurLeSimulateur(page, 'Payer (succès simulé)')
    await expect(page).toHaveURL(/\/paiement\/retour\?facture=\d+/)
    await expect(page.getByRole('heading', { level: 1, name: 'Paiement confirmé' })).toBeVisible()
    expect(resumer(await auditAxe(page))).toBe('')

    // Le moteur construit la vraie copie (dépôt de démo local) : jusqu'à quelques minutes.
    const ouvrir = page.getByRole('link', { name: 'Ouvrir ma copie' })
    await expect(ouvrir).toBeVisible({ timeout: 360_000 })
    const adresse = (await ouvrir.getAttribute('href')) ?? ''
    copie = Number(/\/\/d(\d+)\./.exec(adresse)?.[1] ?? 0)
    expect(copie).toBeGreaterThan(0)

    await ouvrirLaCopie(page, adresse, 'Stock du jour')

    await page.goto('/paiements')
    await expect(page.getByRole('heading', { level: 1, name: 'Mes paiements' })).toBeVisible()
    await expect(page.getByRole('row', { name: /PharmaStock.*Payée/ }).first()).toBeVisible()
    expect(resumer(await auditAxe(page))).toBe('')
  })

  test('un impayé est suspendu, puis réactivé dès le paiement', async ({ page }) => {
    test.setTimeout(300_000)
    expect(copie, 'la copie du test précédent').toBeGreaterThan(0)
    // Facture du mois suivant émise, puis 8 jours de retard : la grâce est passée.
    artisan('demo:avancer-facturation', String(copie), '--jours=39')

    await connexionDemo(page, 'soukdemo-pharmacie')
    await page.goto('/copies')
    const carte = page.getByRole('article', { name: /PharmaStock/ }).first()
    await expect(carte.getByText('Suspendue', { exact: true })).toBeVisible({ timeout: 120_000 })

    await ouvrirLaCopie(page, `http://d${copie}.localhost/`, 'Ce service est momentanément indisponible')

    await page.goto('/paiements')
    await expect(page.getByRole('heading', { name: 'À payer' })).toBeVisible()
    await page.getByRole('button', { name: 'Payer 22 000 F' }).first().click()
    await payerSurLeSimulateur(page, 'Payer (succès simulé)')
    await expect(page.getByRole('heading', { level: 1, name: 'Paiement confirmé' })).toBeVisible()

    await page.goto('/copies')
    await expect(page.getByRole('article', { name: /PharmaStock/ }).first().getByText('En ligne', { exact: true })).toBeVisible({ timeout: 180_000 })
    await ouvrirLaCopie(page, `http://d${copie}.localhost/`, 'Stock du jour')
  })

  test('un paiement refusé s’explique et se retente', async ({ page }) => {
    await connexionDemo(page, 'soukdemo-association')
    await page.goto('/store')
    await page.getByText('Cotis’Asso', { exact: true }).first().click()
    await page.getByRole('link', { name: 'Déployer ma copie' }).click()
    await page.getByRole('button', { name: 'Payer 3 500 F' }).click()

    await payerSurLeSimulateur(page, 'Refuser (échec simulé)')
    await expect(page.getByRole('alert')).toContainText('Paiement refusé')
    await page.getByRole('link', { name: 'Revenir sur Souk.dev' }).click()

    await expect(page.getByRole('heading', { level: 1, name: 'Paiement refusé' })).toBeVisible()
    await expect(page.getByText('Aucun montant n’a été débité')).toBeVisible()
    expect(resumer(await auditAxe(page))).toBe('')
    await page.getByRole('button', { name: 'Réessayer le paiement' }).click()
    await expect(page).toHaveURL(/\/simulateur\/saspay\//)
  })

  test('un dev demande son reversement et le reçoit (simulé)', async ({ page }) => {
    await connexionDemo(page, 'soukdemo-awa')
    await page.goto('/revenus')
    await expect(page.getByRole('heading', { level: 1, name: 'Mes revenus' })).toBeVisible()
    await expect(page.getByText('+229 01 •• •• 34 56')).toBeVisible()
    expect(resumer(await auditAxe(page))).toBe('')

    const recevoir = page.getByRole('button', { name: /^Recevoir [\d\s ]+ F$/ })
    await recevoir.click()
    const confirmation = page.getByRole('dialog')
    await expect(confirmation).toContainText('MTN MoMo')
    await confirmation.getByRole('button', { name: 'Confirmer le reversement' }).click()

    // Le payout part en file (worker) : « en cours », puis versé ; le solde est réservé tout de suite.
    await expect(page.getByText(/Reversement (en cours|effectué)/).first()).toBeVisible()
    await expect(page.getByTestId('solde')).toHaveText('0 F')
    await page.getByRole('tab', { name: /Reversements/ }).click()
    await expect(page.getByRole('row', { name: /Versé/ }).first()).toBeVisible({ timeout: 30_000 })
  })

  test('l’admin voit la vue financière tirée du grand livre', async ({ page }) => {
    await connexionDemo(page, 'soukdemo-admin')
    await page.goto('/admin/finances')
    await expect(page.getByRole('heading', { level: 1, name: 'Finances' })).toBeVisible()
    await expect(page.getByText('Marge de Souk.dev')).toBeVisible()
    expect(resumer(await auditAxe(page))).toBe('')

    await connexionDemo(page, 'soukdemo-awa')
    await page.goto('/admin/finances')
    await expect(page.getByRole('heading', { name: 'Accès réservé' })).toBeVisible()
  })
})
