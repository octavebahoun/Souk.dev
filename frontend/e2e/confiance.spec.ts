import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { expect, test, type APIRequestContext, type Page } from '@playwright/test'
import { auditAxe, connexionDemo, resumer } from './outils'

/**
 * Phase 3 — confiance : la sécurité avant publication refuse un dépôt qui contient un secret et
 * donne le badge vert à un dépôt sain (gitleaks et Trivy dans des conteneurs jetables, dépôts de
 * démo servis par le miroir local) ; un client qui a eu une copie note l'appli.
 */

const racine = fileURLToPath(new URL('../../', import.meta.url))

type OptionsRequete = Pick<NonNullable<Parameters<APIRequestContext['post']>[1]>, 'data' | 'multipart'>

/** Appel de l'API depuis la session de la page (Sanctum lit la session grâce au Referer). */
async function api(page: Page, methode: 'GET' | 'POST' | 'DELETE', chemin: string, options: OptionsRequete = {}) {
  const cookies = await page.context().cookies()
  const jeton = decodeURIComponent(cookies.find((cookie) => cookie.name === 'XSRF-TOKEN')?.value ?? '')
  const entetes = { Accept: 'application/json', Referer: 'http://localhost:5173/', 'X-XSRF-TOKEN': jeton }
  if (methode === 'GET') return page.request.get(`/api${chemin}`, { headers: entetes })
  if (methode === 'DELETE') return page.request.delete(`/api${chemin}`, { headers: entetes })
  return page.request.post(`/api${chemin}`, { headers: entetes, ...options })
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

    // L'appli d'essai ne reste pas dans le Store de la plateforme.
    expect((await api(page, 'DELETE', `/apps/${id}`)).status()).toBe(204)
  })

  test('un client qui a eu une copie note l’appli, la moyenne suit sur la fiche et dans le Store', async ({ page }) => {
    // L'auteur ne note pas sa propre appli.
    await connexionDemo(page, 'soukdemo-fatou')
    const recherche = (await (await api(page, 'GET', '/apps?q=Cotis')).json()) as { data: { id: number; nom: string }[] }
    const cotis = recherche.data.find((appli) => appli.nom === 'Cotis’Asso')
    expect(cotis, 'Cotis’Asso absente du jeu de démo').toBeTruthy()
    expect(((await (await api(page, 'GET', `/apps/${cotis!.id}/avis`)).json()) as { peut_noter: boolean }).peut_noter).toBe(false)

    // L'Association Lumière a hébergé Cotis'Asso (un mois payé).
    await page.context().clearCookies()
    await connexionDemo(page, 'soukdemo-association')
    await page.goto(`/store/${cotis!.id}`)
    const avis = page.getByRole('region', { name: 'Avis des clients' })
    // On clique l'étoile, comme un visiteur : le bouton radio est réservé aux lecteurs d'écran.
    const etoile = (nom: string) => avis.locator('label').filter({ has: page.getByRole('radio', { name: nom }) })
    await expect(avis.getByRole('heading', { name: 'Noter cette appli' })).toBeVisible()
    await avis.getByRole('button', { name: 'Publier mon avis' }).click()
    await expect(avis.getByText('Choisissez une note, de 1 à 5 étoiles.')).toBeVisible()

    await etoile('4 étoiles, très bien').click()
    await expect(avis.getByRole('radio', { name: '4 étoiles, très bien' })).toBeChecked()
    await avis.getByLabel('Votre avis').fill('Les cotisations rentrent à l’heure et chaque membre reçoit son reçu.')
    await avis.getByRole('button', { name: 'Publier mon avis' }).click()
    await expect(page.getByText('Merci, votre avis est publié.')).toBeVisible()
    await expect(avis.getByRole('heading', { name: 'Votre avis' })).toBeVisible()
    await expect(avis.getByText(/Note moyenne\s: 4 sur 5, 1 avis/)).toBeAttached()
    await expect(page.getByRole('link', { name: /4 sur 5\s*1 avis/ })).toBeVisible()

    await avis.getByRole('button', { name: 'Modifier' }).click()
    await etoile('5 étoiles, excellente').click()
    await avis.getByRole('button', { name: 'Enregistrer' }).click()
    await expect(page.getByText('Votre avis est modifié.')).toBeVisible()
    await expect(avis.getByText(/Note moyenne\s: 5 sur 5, 1 avis/)).toBeAttached()
    expect(resumer(await auditAxe(page))).toBe('')

    // Une seule note par client : le serveur refuse la seconde.
    const doublon = await api(page, 'POST', `/apps/${cotis!.id}/avis`, { data: { note: 1 } })
    expect(doublon.status()).toBe(409)

    // Les mieux notées d'abord : les deux applis notées 5/5 passent devant celles sans avis.
    await page.goto('/store?tri=note')
    const cartes = page.getByRole('article')
    await expect(cartes.first()).toBeVisible()
    const premieres = await cartes.evaluateAll((elements) => elements.slice(0, 2).map((element) => element.querySelector('a[href^="/store/"]:not([aria-label])')?.textContent ?? ''))
    expect(premieres.sort()).toEqual(['Cotis’Asso', 'PharmaStock'])
  })
})

test.describe.serial('notifications', () => {
  test('la cloche reçoit en direct, mène à l’écran concerné et les réglages tiennent', async ({ page, browser }) => {
    await connexionDemo(page, 'soukdemo-awa')
    // Le direct se vérifie une fois la cloche abonnée à son canal privé (autorisation Reverb reçue).
    const abonnee = page.waitForResponse((reponse) => reponse.url().endsWith('/broadcasting/auth') && reponse.ok())
    await page.goto('/notifications')
    await abonnee
    const cloche = page.getByRole('button', { name: /^Notifications/ })
    await expect(cloche).toBeVisible()
    const nomCloche = (nombre: number) => (nombre === 0 ? 'Notifications' : `Notifications, ${nombre} non ${nombre > 1 ? 'lues' : 'lue'}`)
    const avant = Number((await cloche.getAttribute('aria-label'))?.match(/\d+/)?.[0] ?? 0)

    // La pharmacie écrit dans le fil de la mission : la cloche d'Awa s'allume sans recharger.
    const pharmacie = await browser.newPage()
    await connexionDemo(pharmacie, 'soukdemo-pharmacie')
    const missions = (await (await api(pharmacie, 'GET', '/missions')).json()) as { data: { id: number; client: { username: string } }[] }
    const mission = missions.data.find((item) => item.client.username === 'soukdemo-pharmacie')
    expect(mission, 'mission de démo absente').toBeTruthy()
    const texte = `Nous avons ajouté deux écrans à la liste (essai ${Date.now()}).`
    expect((await api(pharmacie, 'POST', `/missions/${mission!.id}/messages`, { data: { texte } })).status()).toBe(201)
    await pharmacie.close()
    await expect(cloche).toHaveAccessibleName(nomCloche(avant + 1), { timeout: 30_000 })

    await cloche.click()
    const panneau = page.getByRole('dialog', { name: 'Notifications' })
    await expect(panneau.getByText(texte)).toBeVisible()
    expect(resumer(await auditAxe(page, '[role="dialog"]'))).toBe('')
    await panneau.getByRole('link', { name: new RegExp(texte.replace(/[().]/g, '\\$&')) }).click()
    await expect(page).toHaveURL(new RegExp(`/missions\\?mission=${mission!.id}$`))
    await expect(page.getByText(texte).first()).toBeVisible()
    await expect(cloche).toHaveAccessibleName(nomCloche(avant))

    // Couper l'e-mail des messages de mission : le réglage survit au rechargement.
    await page.goto('/notifications/reglages')
    const missionsReglage = page.getByRole('group', { name: 'Missions' })
    const email = missionsReglage.getByRole('switch', { name: 'Par e-mail' })
    await expect(email).toBeChecked()
    const enregistre = page.waitForResponse((reponse) => reponse.url().endsWith('/api/me/preferences-notifications') && reponse.request().method() === 'PUT')
    await email.click()
    await expect(email).not.toBeChecked()
    expect((await enregistre).status()).toBe(200)
    await page.reload()
    await expect(page.getByRole('group', { name: 'Missions' }).getByRole('switch', { name: 'Par e-mail' })).not.toBeChecked()
    await expect(page.getByRole('group', { name: 'Suspension d’une copie' }).getByText('Toujours envoyé')).toBeVisible()
    expect(resumer(await auditAxe(page))).toBe('')
    const retabli = page.waitForResponse((reponse) => reponse.url().endsWith('/api/me/preferences-notifications') && reponse.request().method() === 'PUT')
    await page.getByRole('group', { name: 'Missions' }).getByRole('switch', { name: 'Par e-mail' }).click()
    expect((await retabli).status()).toBe(200)
    await expect(page.getByRole('group', { name: 'Missions' }).getByRole('switch', { name: 'Par e-mail' })).toBeChecked()

    await page.goto('/notifications')
    await page.getByRole('tab', { name: /Non lues/ }).click()
    expect(resumer(await auditAxe(page))).toBe('')
  })
})
