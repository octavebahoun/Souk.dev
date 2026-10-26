import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { expect, test, type Page } from '@playwright/test'
import { auditAxe, connexionDemo, resumer } from './outils'

/**
 * Phase 3 — confiance : la sécurité avant publication refuse un dépôt qui contient un secret et
 * donne le badge vert à un dépôt sain (gitleaks et Trivy dans des conteneurs jetables, dépôts de
 * démo servis par le miroir local).
 */

const racine = fileURLToPath(new URL('../../', import.meta.url))

/** Appel de l'API depuis la session de la page (Sanctum lit la session grâce au Referer). */
async function api(page: Page, methode: 'GET' | 'POST', chemin: string, options: { data?: unknown; multipart?: Record<string, unknown> } = {}) {
  const cookies = await page.context().cookies()
  const jeton = decodeURIComponent(cookies.find((cookie) => cookie.name === 'XSRF-TOKEN')?.value ?? '')
  const entetes = { Accept: 'application/json', Referer: 'http://localhost:5173/', 'X-XSRF-TOKEN': jeton }
  return methode === 'GET'
    ? page.request.get(`/api${chemin}`, { headers: entetes })
    : page.request.post(`/api${chemin}`, { headers: entetes, ...options })
}

test.describe.serial('confiance', () => {
  test('un dépôt avec un secret est refusé, un dépôt sain reçoit le badge vert', async ({ page }) => {
    test.setTimeout(240_000)
    await connexionDemo(page, 'soukdemo-awa')

    const refus = await api(page, 'POST', '/apps/verification', { data: { depot_url: 'https://github.com/soukdemo/fuite' } })
    expect(refus.status()).toBe(422)
    const message = ((await refus.json()) as { errors: { depot_url: string[] } }).errors.depot_url[0]
    expect(message).toContain('config/paiement.js')
    expect(message).not.toContain('sk_live_')

    const publication = await api(page, 'POST', '/apps', {
      multipart: {
        nom: 'PharmaStock Confiance',
        description: 'Publication d’essai : la sécurité avant publication doit donner le badge vert.',
        categorie: 'sante',
        depot_url: 'https://github.com/soukdemo/pharmastock',
        demo_url: 'https://pharmastock-demo.souk.dev',
        prix: '0',
        type_prix: 'mensuel',
        taille: 'petite',
        'stack[]': 'autre',
        'captures[]': { name: 'capture.webp', mimeType: 'image/webp', buffer: readFileSync(`${racine}backend/database/seeders/demo/captures/stock-boutique.webp`) },
      },
    })
    expect(publication.status()).toBe(201)
    const { id } = (await publication.json()) as { id: number }

    await expect
      .poll(async () => ((await (await api(page, 'GET', `/apps/${id}`)).json()) as { securite: { statut: string } }).securite.statut, { timeout: 180_000, intervals: [3_000] })
      .toBe('verifie')

    await page.goto(`/store/${id}`)
    await expect(page.getByRole('heading', { name: 'Sécurité : Security Checked' })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Relancer l’analyse' })).toBeVisible()
    expect(resumer(await auditAxe(page))).toBe('')
  })
})
