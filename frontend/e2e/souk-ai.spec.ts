import { execFileSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'
import { expect, test, type APIRequestContext, type Page } from '@playwright/test'
import { auditAxe, connexionDemo, resumer } from './outils'

/**
 * Phase 4 — Souk AI : l'auteur d'un bug demande une analyse (consentement explicite), la réponse
 * de DeepSeek arrive à côté des correctifs sans recharger ; la 4ᵉ demande en 24 heures reçoit 429.
 * Le jeu de démo est rechargé avant : la limite de chaque compte repart de zéro.
 */

const racine = fileURLToPath(new URL('../../', import.meta.url))

type OptionsRequete = Pick<NonNullable<Parameters<APIRequestContext['post']>[1]>, 'data'>

async function api(page: Page, methode: 'GET' | 'POST', chemin: string, options: OptionsRequete = {}) {
  const cookies = await page.context().cookies()
  const jeton = decodeURIComponent(cookies.find((cookie) => cookie.name === 'XSRF-TOKEN')?.value ?? '')
  const entetes = { Accept: 'application/json', Referer: 'http://localhost:5173/', 'X-XSRF-TOKEN': jeton }
  return methode === 'GET' ? page.request.get(`/api${chemin}`, { headers: entetes }) : page.request.post(`/api${chemin}`, { headers: entetes, ...options })
}

async function nouveauBug(page: Page, titre: string): Promise<number> {
  const reponse = await api(page, 'POST', '/discussions', {
    data: {
      titre,
      texte: 'La page de paiement reste blanche après la redirection de l’opérateur.',
      canal: 'mobile-money',
      etiquettes: ['bug'],
      bug: { erreur_obtenue: 'Page blanche, aucune erreur dans la console.', comportement_attendu: 'Le reçu s’affiche.', techno: 'react', code: 'useEffect(() => { verifier(id) }, [])', difficulte: 'facile' },
    },
  })
  expect(reponse.status()).toBe(201)
  return ((await reponse.json()) as { id: number }).id
}

test.describe.serial('souk ai', () => {
  test.beforeAll(() => {
    execFileSync('docker', ['compose', 'exec', '-T', '-u', '1000', 'api', 'php', 'artisan', 'db:seed', '--class=DemoSeeder', '--force'], { cwd: racine, encoding: 'utf8' })
  })

  test('l’auteur d’un bug demande l’avis de Souk AI et la réponse arrive sans recharger', async ({ page }) => {
    test.setTimeout(240_000)
    await connexionDemo(page, 'soukdemo-yao')
    const discussions = (await (await api(page, 'GET', '/discussions?per_page=50')).json()) as { data: { id: number; titre: string }[] }
    const bug = discussions.data.find((discussion) => discussion.titre.startsWith('Wave CI'))
    expect(bug, 'bug de démo absent').toBeTruthy()

    await page.goto(`/echange/${bug!.id}`)
    const analyse = page.getByRole('region', { name: /Analyse IA/ })
    await analyse.getByRole('button', { name: 'Demander l’avis de Souk AI' }).click()
    const consentement = page.getByRole('dialog', { name: 'Demander l’avis de Souk AI' })
    await expect(consentement.getByRole('button', { name: 'Lancer l’analyse' })).toBeDisabled()
    await consentement.getByLabel('J’accepte que ma fiche et mon code partent chez DeepSeek').check()
    await consentement.getByRole('button', { name: 'Lancer l’analyse' }).click()
    await expect(analyse.getByText('Analyse en cours. La réponse arrive ici, sans recharger la page.')).toBeVisible()
    await expect(analyse.getByRole('heading', { name: /Analyse IA/ })).toBeFocused()

    // La réponse de DeepSeek (quelques secondes à une minute) remplace l'attente, rendue en Markdown,
    // et la zone annoncée aux lecteurs d'écran le dit.
    await expect(analyse.getByRole('status')).toHaveText('Analyse de Souk AI prête.', { timeout: 180_000 })
    await expect(analyse.getByText('Analyse en cours. La réponse arrive ici, sans recharger la page.')).toBeHidden()
    expect(await analyse.locator('p, li, pre').count()).toBeGreaterThan(3)
    await expect(analyse.getByRole('button', { name: 'Relancer l’analyse' })).toBeVisible()
    expect(resumer(await auditAxe(page))).toBe('')

    // Un visiteur la lit aussi, sans pouvoir en demander.
    await page.context().clearCookies()
    await page.reload()
    const vue = page.getByRole('region', { name: /Analyse IA/ })
    await expect(vue.locator('p, li, pre').nth(3)).toBeVisible()
    await expect(vue.getByRole('button', { name: /Relancer|Demander/ })).toHaveCount(0)
  })

  test('la 4ᵉ demande en 24 heures reçoit 429', async ({ page }) => {
    await connexionDemo(page, 'soukdemo-yao')
    const bugs = [await nouveauBug(page, 'Paiement Wave : page blanche au retour (essai 2)'), await nouveauBug(page, 'Paiement Wave : page blanche au retour (essai 3)'), await nouveauBug(page, 'Paiement Wave : page blanche au retour (essai 4)')]

    for (const id of bugs.slice(0, 2)) {
      expect((await api(page, 'POST', `/discussions/${id}/analyse-ia`, { data: { consentement: true } })).status()).toBe(202)
    }
    const refus = await api(page, 'POST', `/discussions/${bugs[2]}/analyse-ia`, { data: { consentement: true } })
    expect(refus.status()).toBe(429)
    expect(Number(refus.headers()['retry-after'])).toBeGreaterThan(80_000)
    expect(((await refus.json()) as { message: string }).message).toContain('3 analyses par 24 heures')

    // Sans consentement, rien ne part.
    expect((await api(page, 'POST', `/discussions/${bugs[2]}/analyse-ia`, { data: {} })).status()).toBe(422)
  })
})
