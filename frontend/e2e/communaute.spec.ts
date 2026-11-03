import { execFileSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'
import { expect, test, type Browser, type Page } from '@playwright/test'
import { auditAxe, connexionDemo, resumer } from './outils'

/**
 * Phase 8 — les parcours de la communauté, tous à l'écran : une discussion avec sa copie de test,
 * un correctif proposé, accepté et lancé ; une mission demandée, suivie et notée ; un événement
 * créé, modifié, annulé, et l'avis d'un participant ; le profil, un canal ; la modération qui
 * supprime ; une appli publiée depuis le formulaire. Les copies sont de vraies constructions
 * (dépôts de démo servis par le miroir local). Les comptes de démo ne reçoivent aucun e-mail.
 */

const racine = fileURLToPath(new URL('../../', import.meta.url))
const base = process.env.E2E_URL || 'http://localhost:5173'

test.beforeAll(() => {
  execFileSync('docker', ['compose', 'exec', '-T', '-u', '1000', 'api', 'php', 'artisan', 'db:seed', '--class=DemoSeeder', '--force'], { cwd: racine })
})

/** Une session de plus, dans son propre contexte (deux comptes à la fois). */
async function session(browser: Browser, compte: string): Promise<Page> {
  const contexte = await browser.newContext({ baseURL: base, locale: 'fr-FR', viewport: { width: 1440, height: 900 } })
  const page = await contexte.newPage()
  await connexionDemo(page, compte)
  return page
}

/** Appel de l'API avec la session de la page (Sanctum lit la session grâce au Referer). */
async function api(page: Page, methode: 'GET' | 'POST' | 'DELETE', chemin: string, data?: unknown) {
  const cookies = await page.context().cookies()
  const jeton = decodeURIComponent(cookies.find((cookie) => cookie.name === 'XSRF-TOKEN')?.value ?? '')
  const headers = { Accept: 'application/json', Referer: `${base}/`, 'X-XSRF-TOKEN': jeton }
  if (methode === 'GET') return page.request.get(`/api${chemin}`, { headers })
  if (methode === 'DELETE') return page.request.delete(`/api${chemin}`, { headers })
  return page.request.post(`/api${chemin}`, { headers, data })
}

test.describe.serial('communauté', () => {
  test('une discussion, sa copie de test, un correctif proposé, accepté puis lancé', async ({ page, browser }) => {
    test.setTimeout(900_000)
    await connexionDemo(page, 'soukdemo-awa')
    await page.goto('/echange')

    // Une discussion avec sa fiche de bug et le dépôt, depuis le formulaire.
    await page.getByRole('button', { name: 'Nouvelle discussion' }).click()
    const formulaire = page.getByRole('dialog', { name: 'Nouvelle discussion' })
    await formulaire.getByLabel('Titre').fill('Le récapitulatif du jour reste vide')
    await formulaire.getByLabel('Texte', { exact: true }).fill('Après une vente, la page « Stock du jour » ne montre aucun total. Testé sur la branche main.')
    await formulaire.getByLabel('Canal').selectOption({ index: 1 })
    await formulaire.getByRole('button', { name: 'bug', exact: true }).click()
    await formulaire.getByLabel('Erreur obtenue').fill('Le total affiché vaut 0 F.')
    await formulaire.getByLabel('Comportement attendu').fill('Le total des ventes du jour.')
    await formulaire.getByLabel('Dépôt GitHub').fill('https://github.com/soukdemo/pharmastock')
    expect(resumer(await auditAxe(page, '[role="dialog"]'))).toBe('')
    await formulaire.getByRole('button', { name: 'Publier la discussion' }).click()
    await page.getByRole('button', { name: 'Voir' }).click()
    await expect(page.getByRole('heading', { level: 1, name: 'Le récapitulatif du jour reste vide' })).toBeVisible()
    const adresse = page.url()
    const discussion = Number(/\/echange\/(\d+)/.exec(adresse)?.[1] ?? 0)
    expect(discussion).toBeGreaterThan(0)

    // La copie de test, construite pour de vrai, s'affiche dans un cadre isolé.
    await page.getByRole('button', { name: 'Lancer une copie de test' }).click()
    await page.getByRole('dialog', { name: 'Lancer une copie de test ?' }).getByRole('button', { name: 'Lancer la copie' }).click()
    await expect(page.getByRole('link', { name: /Ouvrir la copie/ })).toBeVisible({ timeout: 420_000 })
    await expect(page.getByTitle('Démo isolée')).toHaveAttribute('src', /^http:\/\/d[a-z0-9-]+\.localhost/)
    expect(resumer(await auditAxe(page))).toBe('')

    // Un autre dev répond et propose sa branche.
    const yao = await session(browser, 'soukdemo-yao')
    await yao.goto(adresse)
    await yao.getByLabel('Votre réponse').fill('La branche v2 recalcule le total à chaque vente : je propose un correctif.')
    await yao.getByRole('button', { name: 'Envoyer', exact: true }).click()
    await expect(yao.getByText('La branche v2 recalcule le total à chaque vente')).toBeVisible()
    await yao.getByRole('button', { name: 'Proposer un correctif' }).click()
    const proposition = yao.getByRole('dialog', { name: 'Proposer un correctif' })
    await proposition.getByLabel('URL de la branche').fill('https://github.com/soukdemo/pharmastock/tree/v2')
    await proposition.getByLabel('Description').fill('Recalcule le total du jour après chaque vente.')
    await proposition.getByRole('button', { name: 'Envoyer le correctif' }).click()
    await expect(yao.getByText('Recalcule le total du jour après chaque vente.')).toBeVisible()

    // L'auteur lit la réponse, accepte le correctif ; son auteur lance la version corrigée.
    await page.reload()
    await expect(page.getByText('La branche v2 recalcule le total à chaque vente')).toBeVisible()
    await page.getByRole('button', { name: 'Accepter', exact: true }).click()
    await page.getByRole('dialog', { name: 'Accepter ce correctif ?' }).getByRole('button', { name: 'Accepter le correctif' }).click()
    await expect(page.getByText('Accepté', { exact: true })).toBeVisible()

    await yao.reload()
    await yao.getByRole('button', { name: 'Lancer ma version corrigée' }).click()
    await expect(yao.getByText('Version corrigée : En ligne')).toBeVisible({ timeout: 420_000 })

    // La copie de test s'arrête.
    await page.getByRole('button', { name: 'Arrêter la copie' }).click()
    await page.getByRole('dialog', { name: 'Arrêter la copie de test ?' }).getByRole('button', { name: 'Arrêter la copie' }).click()
    // Copie arrêtée : le cadre disparaît, une nouvelle copie de test peut partir.
    await expect(page.getByRole('button', { name: 'Lancer une copie de test' })).toBeVisible({ timeout: 60_000 })
    await expect(page.getByTitle('Démo isolée')).toBeHidden()
    // Accepter un correctif résout la discussion : l'auteur peut la rouvrir.
    await expect(page.getByText('Résolue', { exact: true })).toBeVisible()
    await expect(page.getByRole('button', { name: 'Rouvrir' })).toBeVisible()

    // La version corrigée ne reste pas en ligne après le parcours.
    const correctifs = (await (await api(yao, 'GET', `/discussions/${discussion}/correctifs`)).json()) as { data: Array<{ deploiement: { id: number } | null }> }
    const copieCorrigee = correctifs.data.find((correctif) => correctif.deploiement)?.deploiement?.id
    if (copieCorrigee) expect((await api(yao, 'DELETE', `/deployments/${copieCorrigee}`)).status()).toBeLessThan(300)
    await yao.context().close()
  })

  test('une mission demandée à un dev, suivie dans son fil privé, terminée et notée', async ({ page, browser }) => {
    test.setTimeout(180_000)
    await connexionDemo(page, 'soukdemo-pharmacie')
    await page.goto('/devs/soukdemo-awa')
    await page.getByRole('button', { name: 'Proposer une mission' }).click()
    const demande = page.getByRole('dialog')
    await demande.getByLabel('Votre besoin').fill('Ajouter l’impression des étiquettes de prix depuis PharmaStock.')
    await demande.getByLabel('Budget').fill('120000')
    await demande.getByRole('button', { name: 'Envoyer la demande' }).click()
    await expect(demande).toBeHidden()

    const missions = (await (await api(page, 'GET', '/missions')).json()) as { data: Array<{ id: number; message: string }> }
    const mission = missions.data.find((item) => item.message.startsWith('Ajouter l’impression des étiquettes'))?.id
    expect(mission, 'la mission créée').toBeTruthy()

    // Le dev la retrouve et répond dans le fil privé.
    const awa = await session(browser, 'soukdemo-awa')
    await awa.goto(`/missions?mission=${mission}`)
    await expect(awa.getByText('Ajouter l’impression des étiquettes de prix depuis PharmaStock.')).toBeVisible()
    await awa.getByLabel('Votre message').fill('Avec plaisir : je vous propose un premier jet la semaine prochaine.')
    await awa.getByRole('button', { name: 'Envoyer' }).click()
    await expect(awa.getByText('je vous propose un premier jet')).toBeVisible()
    expect(resumer(await auditAxe(awa))).toBe('')
    await awa.context().close()

    // Le client lit la réponse, termine la mission et la note.
    await page.goto(`/missions?mission=${mission}`)
    await expect(page.getByText('je vous propose un premier jet')).toBeVisible()
    await page.getByRole('button', { name: 'Terminer et noter' }).click()
    const notation = page.getByRole('dialog', { name: 'Terminer et noter la mission' })
    await notation.getByRole('radio', { name: '5 sur 5' }).click()
    await notation.getByLabel('Commentaire').fill('Réactive et claire.')
    await notation.getByRole('button', { name: 'Terminer la mission' }).click()
    await expect(page.getByText('Mission terminée')).toBeVisible()
  })

  test('un événement créé, modifié puis annulé ; un participant donne son avis', async ({ page, browser }) => {
    test.setTimeout(180_000)
    await connexionDemo(page, 'soukdemo-awa')
    await page.goto('/evenements')
    await page.getByRole('button', { name: 'Créer un événement' }).first().click()
    const creation = page.getByRole('dialog', { name: 'Créer un événement' })
    const date = new Date(Date.now() + 20 * 86_400_000)
    const locale = new Date(date.getTime() - date.getTimezoneOffset() * 60_000).toISOString().slice(0, 16)
    await creation.getByLabel('Titre').fill('Atelier PWA hors connexion')
    await creation.getByLabel('Description').fill('Rendre une appli utilisable sans réseau : cache, file d’attente, synchronisation.')
    await creation.getByLabel('Date et heure').fill(locale)
    await creation.getByText('En ligne', { exact: true }).click()
    await creation.getByLabel('Lien de la rencontre').fill('https://meet.example.com/atelier-pwa')
    await creation.getByRole('button', { name: 'Créer l’événement' }).click()
    await expect(page.getByRole('heading', { name: 'Atelier PWA hors connexion' })).toBeVisible()

    // L'organisateur modifie son événement, puis l'annule.
    const carte = page.getByRole('listitem').filter({ has: page.getByRole('heading', { name: 'Atelier PWA hors connexion' }) })
    await carte.getByRole('button', { name: 'Modifier' }).click()
    const modification = page.getByRole('dialog', { name: 'Modifier l’événement' })
    await expect(modification.getByLabel('Titre')).toHaveValue('Atelier PWA hors connexion')
    await modification.getByLabel('Titre').fill('Atelier PWA : travailler hors connexion')
    await modification.getByRole('button', { name: 'Enregistrer' }).click()
    await expect(page.getByRole('heading', { name: 'Atelier PWA : travailler hors connexion' })).toBeVisible()

    const modifiee = page.getByRole('listitem').filter({ has: page.getByRole('heading', { name: 'Atelier PWA : travailler hors connexion' }) })
    await modifiee.getByRole('button', { name: 'Annuler l’événement' }).click()
    const annulation = page.getByRole('dialog', { name: 'Annuler cet événement ?' })
    expect(resumer(await auditAxe(page, '[role="dialog"]'))).toBe('')
    await annulation.getByRole('button', { name: 'Annuler l’événement' }).click()
    await expect(page.getByText('Événement annulé.')).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Atelier PWA : travailler hors connexion' })).toBeHidden()

    // Un participant du meetup passé laisse son avis.
    const chinedu = await session(browser, 'soukdemo-chinedu')
    await chinedu.goto('/evenements')
    const meetup = chinedu.getByRole('listitem').filter({ has: chinedu.getByRole('heading', { name: 'Meetup Laravel Cotonou #4' }) })
    await meetup.getByLabel('Votre note').selectOption('5')
    await meetup.getByLabel('Commentaire').fill('Des exemples concrets, merci.')
    await meetup.getByRole('button', { name: 'Publier l’avis' }).click()
    await expect(meetup.getByText('Avis publié. Merci.')).toBeVisible()
    await chinedu.context().close()

    // Un membre s'inscrit à un événement à venir, puis se désinscrit.
    const atelier = page.getByRole('listitem').filter({ has: page.getByRole('heading', { name: /Atelier en ligne : encaisser par Mobile Money/ }) })
    await atelier.getByRole('button', { name: 'S’inscrire' }).click()
    await expect(atelier.getByText('Inscrit', { exact: true })).toBeVisible()
    await expect(atelier.getByText('https://meet.example.com/souk-atelier-saspay')).toBeVisible()
    await atelier.getByRole('button', { name: 'Se désinscrire' }).click()
    await expect(atelier.getByRole('button', { name: 'S’inscrire' })).toBeVisible()
  })

  test('un dev complète son profil et ouvre un canal', async ({ page }) => {
    await connexionDemo(page, 'soukdemo-awa')
    await page.goto('/profil')
    await expect(page.getByRole('heading', { level: 1, name: 'Mon profil' })).toBeVisible()
    await page.getByLabel('Bio').fill('Je construis des applis de gestion pour les pharmacies et les boutiques de Cotonou.')
    await page.getByRole('button', { name: 'go', exact: true }).click()
    expect(resumer(await auditAxe(page))).toBe('')
    await page.getByRole('button', { name: 'Enregistrer mon profil' }).click()
    await expect(page.getByText('Profil enregistré.')).toBeVisible()
    await page.getByRole('link', { name: 'Voir mon profil public' }).click()
    await expect(page.getByText('Je construis des applis de gestion pour les pharmacies')).toBeVisible()

    await page.goto('/echange')
    await page.getByRole('button', { name: 'Créer un canal' }).first().click()
    const canal = page.getByRole('dialog', { name: 'Créer un canal' })
    // « Canal E2E … » : le jeu de démo efface ces canaux à chaque chargement.
    await canal.getByLabel('Nom').fill(`Canal E2E ${Date.now() % 100000}`)
    await canal.getByLabel('Description').fill('Applis web installables et hors connexion.')
    expect(resumer(await auditAxe(page, '[role="dialog"]'))).toBe('')
    await canal.getByRole('button', { name: 'Créer le canal' }).click()
    await expect(page.getByText(/Canal #canal-e2e-\d+ créé\./)).toBeVisible()
    await expect(page.getByRole('button', { name: /#canal-e2e-\d+/ }).first()).toHaveAttribute('aria-current', 'true')
  })

  test('l’équipe supprime un message puis une discussion signalés', async ({ page, browser }) => {
    const yao = await session(browser, 'soukdemo-yao')
    const creee = await api(yao, 'POST', '/discussions', { titre: 'Achetez des abonnés pas chers', texte: 'Lien en message privé.', canal: 'entraide-generale', etiquettes: ['question'] })
    expect(creee.status()).toBe(201)
    const { id } = (await creee.json()) as { id: number }
    expect((await api(yao, 'POST', `/discussions/${id}/messages`, { texte: 'Promo : 1000 abonnés pour 500 F.' })).status()).toBe(201)
    await yao.context().close()

    await connexionDemo(page, 'soukdemo-admin')
    await page.goto(`/echange/${id}`)
    await page.getByRole('button', { name: /Supprimer le message de/ }).click()
    const confirmationMessage = page.getByRole('dialog', { name: 'Supprimer ce message ?' })
    await confirmationMessage.getByRole('button', { name: 'Supprimer' }).click()
    await expect(confirmationMessage).toBeHidden()
    await expect(page.getByText('Promo : 1000 abonnés pour 500 F.')).toBeHidden()

    await page.getByRole('button', { name: 'Supprimer', exact: true }).click()
    const confirmation = page.getByRole('dialog', { name: 'Supprimer cette discussion ?' })
    expect(resumer(await auditAxe(page, '[role="dialog"]'))).toBe('')
    await confirmation.getByRole('button', { name: 'Supprimer' }).click()
    await expect(page).toHaveURL(/\/echange$/)
    await expect(page.getByText('Discussion supprimée.')).toBeVisible()
    expect((await api(page, 'GET', `/discussions/${id}`)).status()).toBe(404)
  })

  test('un dev publie une appli depuis le formulaire et voit sa mémoire mesurée', async ({ page }) => {
    test.setTimeout(300_000)
    await connexionDemo(page, 'soukdemo-awa')
    await page.goto('/publier')
    await page.getByLabel('Adresse du dépôt').fill('https://github.com/soukdemo/pharmastock')
    await page.getByRole('button', { name: 'Vérifier le dépôt' }).click()
    await expect(page.getByText(/Dépôt valide\s:\s+service web/)).toBeVisible({ timeout: 150_000 })
    await page.getByLabel('Nom').fill('Stock Atelier')
    await page.getByLabel('Description').fill('Le stock d’une petite boutique, vente par vente, avec le total du jour.')
    await page.getByLabel('URL de l’application en ligne').fill('https://stock-atelier.souk.dev')
    await page.getByLabel('Catégorie').selectOption('sante')
    await page.getByLabel('Prix de l’appli').fill('0')
    // Choix en cartes : le bouton radio est masqué, on clique la carte (son libellé).
    await page.getByText('Mensuel', { exact: true }).click()
    await expect(page.getByRole('radio', { name: /Mensuel/ })).toBeChecked()
    await page.getByText('Petite', { exact: true }).first().click()
    await expect(page.getByRole('radio', { name: /Petite/ })).toBeChecked()
    await page.locator('#captures-appli').setInputFiles(`${racine}backend/database/seeders/demo/captures/stock-boutique.webp`)
    await page.getByRole('button', { name: 'autre', exact: true }).click()
    expect(resumer(await auditAxe(page))).toBe('')
    await page.getByRole('button', { name: 'Publier l’appli' }).click()

    await expect(page).toHaveURL(/\/store\/\d+$/, { timeout: 60_000 })
    await expect(page.getByRole('heading', { level: 1, name: 'Stock Atelier' })).toBeVisible()
    await expect(page.getByRole('heading', { name: 'Mémoire mesurée' })).toBeVisible()
    expect(resumer(await auditAxe(page))).toBe('')

    // L'appli d'essai ne reste pas dans le Store.
    const id = Number(/\/store\/(\d+)$/.exec(page.url())?.[1] ?? 0)
    expect((await api(page, 'DELETE', `/apps/${id}`)).status()).toBe(204)
  })
})
