import { execFileSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'
import { expect, test, type Page } from '@playwright/test'
import { auditAxe, connexionDemo, resumer } from './outils'

/**
 * Phase 5 — plusieurs serveurs : l'équipe enregistre un second démon Docker (conteneur
 * Docker-in-Docker du profil « machines »), le placement y envoie la copie quand la machine
 * locale est pleine, la maintenance l'évite, et le retrait est refusé tant qu'une copie y tourne.
 */

const racine = fileURLToPath(new URL('../../', import.meta.url))
const HOTE_DIND = 'unix:///run/dind/docker.sock'
const REFERER = 'http://localhost:5173/'

function compose(...arguments_: string[]): string {
  return execFileSync('docker', ['compose', ...arguments_], { cwd: racine, encoding: 'utf8' })
}

function artisan(...arguments_: string[]): string {
  return execFileSync('docker', ['compose', 'exec', '-T', '-u', '1000', 'api', 'php', 'artisan', ...arguments_], { cwd: racine, encoding: 'utf8' })
}

/** Attend le démon intérieur du conteneur Docker-in-Docker. */
function attendreDind(): void {
  const fin = Date.now() + 120_000
  const pause = () => Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, 2000)
  for (;;) {
    try {
      execFileSync('docker', ['exec', 'soukdev-dind-1', 'docker', 'info'], { stdio: 'pipe' })
      return
    } catch {
      if (Date.now() > fin) throw new Error('Le second démon Docker ne répond pas.')
      pause()
    }
  }
}

type Machine = { id: number; nom: string; copies: number }
type Copie = { etat: string; machine_nom: string | null }

/** Appel API authentifié (session + CSRF + Referer Sanctum). */
async function api(page: Page, methode: 'get' | 'post' | 'patch' | 'delete', chemin: string, donnees?: object) {
  const cookies = await page.context().cookies()
  const jeton = cookies.find((cookie) => cookie.name === 'XSRF-TOKEN')?.value
  const reponse = await page.request[methode](`/api${chemin}`, {
    data: donnees,
    headers: { 'X-XSRF-TOKEN': decodeURIComponent(jeton ?? ''), Accept: 'application/json', Referer: REFERER },
  })
  return reponse
}

async function lireMachine(page: Page, nom: string): Promise<Machine> {
  const reponse = await api(page, 'get', '/admin/machines')
  const corps = (await reponse.json()) as { data: Machine[] }
  const machine = corps.data.find((candidate) => candidate.nom === nom)
  expect(machine, `machine ${nom} introuvable`).toBeTruthy()
  return machine as Machine
}

/** Une copie PharmaStock réelle, lancée par le moteur (le paiement a son propre parcours). */
async function lancerUneCopie(): Promise<number> {
  const sortie = artisan(
    'tinker',
    '--execute',
    `
      $appli = App\\Models\\Appli::query()->where("nom", "PharmaStock")->firstOrFail();
      $client = App\\Models\\User::query()->where("username", "soukdemo-pharmacie")->firstOrFail();
      $copie = App\\Models\\Deploiement::query()->create(["user_id" => $client->id, "appli_id" => $appli->id, "type" => "client", "taille" => "petite", "etat" => "en_file", "variables" => []]);
      $copie->forceFill(["version_id" => $appli->versionPubliee->id])->save();
      App\\Jobs\\LancerDeploiement::dispatch($copie->id);
      echo "COPIE=".$copie->id;`,
  )
  return Number(/COPIE=(\d+)/.exec(sortie)?.[1])
}

async function lireLaCopie(page: Page, id: number): Promise<Copie> {
  const reponse = await page.request.get(`/api/deployments/${id}`, { headers: { Accept: 'application/json', Referer: REFERER } })
  return (await reponse.json()) as Copie
}

test.describe.serial('plusieurs serveurs', () => {
  const copies: number[] = []
  let locale = 0

  test.beforeAll(() => {
    artisan('db:seed', '--class=DemoSeeder', '--force')
    // Repart d'une liste propre : le aux d'un parcours précédent (sans copie, le réensemencement
    // les a emportées) et la capacité de la locale.
    artisan('tinker', '--execute', `
      App\\Models\\Machine::query()->where("nom", "aux")->whereDoesntHave("deploiements")->delete();
      App\\Models\\Machine::query()->where("nom", "locale")->update(["capacite_mo" => null]);`)
    compose('--profile', 'machines', 'up', '-d', 'dind', 'route-dind')
    attendreDind()
  })

  test.afterAll(() => {
    for (const id of copies) {
      try {
        artisan('tinker', '--execute', `App\\Models\\Deploiement::query()->find(${id})?->demanderArret();`)
      } catch {
        // Au mieux : le réensemencement du prochain parcours repart d'une base propre.
      }
    }
    try {
      artisan('tinker', '--execute', 'App\\Models\\Machine::query()->where("nom", "locale")->update(["capacite_mo" => null]);')
    } catch {
      // Remise à zéro au prochain parcours.
    }
    try {
      compose('--profile', 'machines', 'rm', '-sf', 'dind', 'route-dind')
    } catch {
      // Le conteneur reste pour un examen manuel.
    }
  })

  test('la machine locale existe et le second serveur s’enregistre, sondé', async ({ page }) => {
    await connexionDemo(page, 'soukdemo-admin')
    locale = (await lireMachine(page, 'locale')).id
    expect(locale).toBeGreaterThan(0)

    await page.goto('/admin/serveurs')
    await expect(page.getByRole('article', { name: 'locale' })).toBeVisible()
    await page.getByLabel('Nom').fill('aux')
    await page.getByLabel(/Démon Docker/).fill(HOTE_DIND)
    await page.getByLabel(/Capacité/).fill('4096')
    await page.getByRole('button', { name: 'Ajouter le serveur' }).click()

    const aux = page.getByRole('article', { name: 'aux' })
    await expect(aux).toBeVisible()
    // La sonde tourne sur le moteur : la version du démon apparaît sans recharger.
    await expect(aux.getByText(/Docker \d+\.\d+/)).toBeVisible({ timeout: 60_000 })
    expect(resumer(await auditAxe(page))).toBe('')
  })

  test('une copie part sur la machine la plus libre et y répond', async ({ page, browser }) => {
    test.setTimeout(600_000)
    expect(locale).toBeGreaterThan(0)
    await connexionDemo(page, 'soukdemo-admin')
    // La locale ne garde que 512 Mo, aux en déclare 4 Go : la copie part sur aux, la plus libre.
    expect((await api(page, 'patch', `/admin/machines/${locale}`, { capacite_mo: 512 })).status()).toBe(200)

    const pharmacie = await browser.newPage()
    await connexionDemo(pharmacie, 'soukdemo-pharmacie')

    const copie = await lancerUneCopie()
    copies.push(copie)
    await expect.poll(async () => (await lireLaCopie(pharmacie, copie)).etat, { timeout: 300_000, intervals: [5_000] }).toBe('en_ligne')
    expect((await lireLaCopie(pharmacie, copie)).machine_nom).toBe('aux')

    // Servie depuis le second démon, sur son sous-domaine.
    await expect
      .poll(
        async () => {
          const reponse = await page.request.get(`http://d${copie}.localhost/`, { failOnStatusCode: false })
          return reponse.ok() ? await reponse.text() : ''
        },
        { timeout: 60_000, intervals: [2_000] },
      )
      .toContain('PharmaStock')
    await expect((await lireMachine(page, 'aux')).copies).toBe(1)
    await pharmacie.close()
  })

  test('en maintenance, un serveur ne reçoit plus rien et ne se retire pas', async ({ page, browser }) => {
    test.setTimeout(600_000)
    expect(locale).toBeGreaterThan(0)
    await connexionDemo(page, 'soukdemo-admin')
    await page.goto('/admin/serveurs')
    const aux = page.getByRole('article', { name: 'aux' })
    await aux.getByRole('button', { name: 'Mettre en maintenance' }).click()
    await page.getByRole('dialog', { name: 'Mettre aux en maintenance ?' }).getByRole('button', { name: 'Mettre en maintenance' }).click()
    await expect(aux.getByText('En maintenance', { exact: true })).toBeVisible()

    // La locale a de nouveau de la place : la copie suivante l'y rejoint, pas sur aux.
    expect((await api(page, 'patch', `/admin/machines/${locale}`, { capacite_mo: 8192 })).status()).toBe(200)
    const pharmacie = await browser.newPage()
    await connexionDemo(pharmacie, 'soukdemo-pharmacie')
    const suivante = await lancerUneCopie()
    copies.push(suivante)
    await expect.poll(async () => (await lireLaCopie(pharmacie, suivante)).etat, { timeout: 300_000, intervals: [5_000] }).toBe('en_ligne')
    expect((await lireLaCopie(pharmacie, suivante)).machine_nom).toBe('locale')
    await pharmacie.close()

    // Retrait refusé : une copie tourne encore sur aux.
    await aux.getByRole('button', { name: 'Retirer' }).click()
    await page.getByRole('dialog', { name: /Retirer aux/ }).getByRole('button', { name: 'Retirer' }).click()
    await expect(page.getByText(/tournent encore/)).toBeVisible()
    expect(resumer(await auditAxe(page))).toBe('')
  })
})
