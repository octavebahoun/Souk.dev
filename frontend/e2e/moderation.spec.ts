import { execFileSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'
import { expect, test, type Page } from '@playwright/test'
import { auditAxe, connexionDemo, resumer } from './outils'

/**
 * Phase 6 — modération et pages légales : un membre signale un contenu, l'équipe traite depuis
 * /admin/moderation, suspend une copie pour abus puis la réactive, suspend un compte qui ne peut
 * plus agir puis le rétablit, gère les canaux. Les pages légales portent leur bandeau « brouillon ».
 * Rejouable : un signalement déjà ouvert est réutilisé, la suspension d'awa est levée au début.
 */

const racine = fileURLToPath(new URL('../../', import.meta.url))
const REFERER = 'http://localhost:5173/'

function artisan(...arguments_: string[]): string {
  return execFileSync('docker', ['compose', 'exec', '-T', 'api', 'php', 'artisan', ...arguments_], { cwd: racine, encoding: 'utf8' })
}

/** Appel API authentifié (session + CSRF + Referer Sanctum). */
async function api(page: Page, methode: 'get' | 'post' | 'patch' | 'delete', chemin: string, donnees?: object) {
  const cookies = await page.context().cookies()
  const jeton = cookies.find((cookie) => cookie.name === 'XSRF-TOKEN')?.value
  return page.request[methode](`/api${chemin}`, {
    data: donnees,
    headers: { 'X-XSRF-TOKEN': decodeURIComponent(jeton ?? ''), Accept: 'application/json', Referer: REFERER },
  })
}

type Discussion = { id: number; titre: string }
type Signalement = { id: number; cible: string; cible_id: number; statut: string; cible_resume: string }
type Compte = { id: number; username: string | null }
type Canal = { slug: string; nom: string }

/** Une copie cliente « en ligne » sans conteneurs (état seul : les travaux moteur s'arrêtent faute de dossier). */
function creerCopieFictive(): number {
  const sortie = artisan(
    'tinker',
    '--execute',
    `
      $appli = App\\Models\\Appli::query()->where("nom", "PharmaStock")->firstOrFail();
      $client = App\\Models\\User::query()->where("username", "soukdemo-pharmacie")->firstOrFail();
      $copie = App\\Models\\Deploiement::query()->create(["user_id" => $client->id, "appli_id" => $appli->id, "type" => "client", "taille" => "petite", "etat" => "en_ligne", "variables" => []]);
      echo "COPIE=".$copie->id;`,
  )
  return Number(/COPIE=(\d+)/.exec(sortie)?.[1])
}

test('une copie signalée est suspendue depuis l’admin', async ({ browser }) => {
  const admin = await browser.newPage()
  const awa = await browser.newPage()
  await connexionDemo(admin, 'soukdemo-admin')
  await connexionDemo(awa, 'soukdemo-awa')

  // Awa signale la première discussion (rejouable : un signalement ouvert est réutilisé).
  const discussions = (await (await api(awa, 'get', '/discussions?per_page=1')).json()) as { data: Discussion[] }
  const cible = discussions.data[0].id
  const envoi = await api(awa, 'post', '/signalements', { cible: 'discussion', cible_id: cible, motif: 'spam', details: 'Parcours de bout en bout.' })
  expect([201, 409], 'signalement').toContain(envoi.status())
  const ouverts = (await (await api(admin, 'get', '/admin/signalements?statut=ouvert')).json()) as { data: Signalement[] }
  const signalement = ouverts.data.find((item) => item.cible === 'discussion' && item.cible_id === cible)
  expect(signalement, 'signalement ouvert retrouvé').toBeTruthy()

  // L'équipe le traite depuis la page de modération.
  await admin.goto('/admin/moderation')
  // Le tableau a aussi sa version mobile (cartes) : on vise sa ligne dans le tableau.
  const ligneSignalement = admin.getByRole('table').getByRole('row').filter({ hasText: signalement?.cible_resume ?? 'Discussion' })
  await expect(ligneSignalement).toBeVisible()
  await ligneSignalement.getByRole('button', { name: 'Traiter' }).click()
  await admin.getByLabel('Note de l’équipe').fill('Contenu vérifié, sans suite.')
  await admin.getByRole('button', { name: 'Marquer traité' }).click()
  await expect(admin.getByText('Signalement traité.')).toBeVisible()

  // Puis elle suspend une copie pour abus, et la réactive.
  const copie = creerCopieFictive()
  await admin.getByRole('tab', { name: 'Copies' }).click()
  const ligne = admin.locator('tr', { hasText: `#${copie}` })
  await expect(ligne).toBeVisible()
  await ligne.getByRole('button', { name: 'Suspendre' }).click()
  await admin.getByLabel('Motif').fill('Minage détecté (parcours de bout en bout).')
  await admin.getByRole('dialog').getByRole('button', { name: 'Suspendre', exact: true }).click()
  await expect(admin.getByText('Copie suspendue : ses conteneurs s’arrêtent, ses données sont gardées.')).toBeVisible()
  await expect(admin.locator('tr', { hasText: `#${copie}` }).getByText('Minage détecté (parcours de bout en bout).')).toBeVisible()
  await admin.locator('tr', { hasText: `#${copie}` }).getByRole('button', { name: 'Réactiver' }).click()
  await expect(admin.getByText('Suspension levée : la copie repart avec ses données.')).toBeVisible()

  const violations = await auditAxe(admin)
  expect(violations, resumer(violations)).toEqual([])

  await admin.close()
  await awa.close()
})

test('un compte suspendu ne peut plus agir, puis est rétabli', async ({ browser }) => {
  const admin = await browser.newPage()
  const awa = await browser.newPage()
  await connexionDemo(admin, 'soukdemo-admin')
  await connexionDemo(awa, 'soukdemo-awa')

  const comptes = (await (await api(admin, 'get', '/admin/comptes?recherche=soukdemo-awa')).json()) as { data: Compte[] }
  const awaId = comptes.data[0].id
  // Ménage d'un passage précédent interrompu : la suspension est levée d'abord (409 sinon).
  await api(admin, 'delete', `/admin/comptes/${awaId}/suspension`)

  const suspension = await api(admin, 'post', `/admin/comptes/${awaId}/suspension`, { motif: 'Spam (parcours de bout en bout).' })
  expect(suspension.status()).toBe(200)

  // Le compte lit son motif, mais ne peut plus rien faire.
  await awa.goto('/copies')
  await expect(awa.getByText('Votre compte est suspendu')).toBeVisible()
  const refus = await api(awa, 'post', '/discussions', { titre: 'x', texte: 'y' })
  expect(refus.status()).toBe(403)

  // Ni se reconnecter.
  await awa.request.get('/sanctum/csrf-cookie')
  const cookies = await awa.context().cookies()
  const jeton = cookies.find((cookie) => cookie.name === 'XSRF-TOKEN')?.value
  const reconnexion = await awa.request.post('/auth/demo', {
    data: { compte: 'soukdemo-awa' },
    headers: { 'X-XSRF-TOKEN': decodeURIComponent(jeton ?? ''), Accept: 'application/json' },
  })
  expect(reconnexion.status()).toBe(403)

  const levee = await api(admin, 'delete', `/admin/comptes/${awaId}/suspension`)
  expect(levee.status()).toBe(200)
  await connexionDemo(awa, 'soukdemo-awa')

  await admin.close()
  await awa.close()
})

test('l’admin renomme et supprime un canal', async ({ browser }) => {
  const admin = await browser.newPage()
  await connexionDemo(admin, 'soukdemo-admin')

  const nom = `Canal E2E ${Date.now()}`
  const creation = await api(admin, 'post', '/canaux', { nom, description: 'Canal du parcours de bout en bout.' })
  expect(creation.status()).toBe(201)
  const canal = (await creation.json()) as Canal

  await admin.goto('/admin/moderation')
  await admin.getByRole('tab', { name: 'Canaux' }).click()
  const ligne = admin.locator('tr', { hasText: `#${canal.slug}` })
  await expect(ligne).toBeVisible()
  await ligne.getByRole('button', { name: 'Modifier' }).click()
  await admin.getByLabel('Nom').fill(`${nom} renommé`)
  await admin.getByRole('button', { name: 'Enregistrer' }).click()
  await expect(admin.getByText('Canal modifié.')).toBeVisible()

  await admin.locator('tr', { hasText: `#${canal.slug}` }).getByRole('button', { name: 'Supprimer' }).click()
  await admin.getByRole('dialog').getByRole('button', { name: 'Supprimer', exact: true }).click()
  await expect(admin.getByText('Canal supprimé.')).toBeVisible()
  await expect(admin.locator('tr', { hasText: `#${canal.slug}` })).toHaveCount(0)

  await admin.close()
})

test('les pages légales portent leur bandeau brouillon', async ({ browser }) => {
  const page = await browser.newPage()
  for (const chemin of ['/legal/cgu', '/legal/cgv', '/legal/confidentialite', '/legal/abus']) {
    await page.goto(chemin)
    await expect(page.getByText('Brouillon à faire valider par un juriste')).toBeVisible()
  }
  await page.goto('/')
  await expect(page.getByRole('link', { name: 'Politique d’abus' })).toBeVisible()

  await page.goto('/legal/cgu')
  const violations = await auditAxe(page)
  expect(violations, resumer(violations)).toEqual([])

  await page.close()
})
