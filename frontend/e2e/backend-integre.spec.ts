import { execFileSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'
import { expect, test, type Browser } from '@playwright/test'
import { auditAxe, connexionDemo, resumer } from './outils'

/**
 * Phase 5 — backend intégré complet : les utilisateurs d'une copie de Carnet se connectent avec un
 * code reçu par SMS (simulé, lu sur /simulateur/sms) et chacun ne voit que ses notes (politiques
 * RLS de la base fournie) ; le client sauvegarde sa copie puis télécharge l'export depuis « Mes
 * copies ». Copie réelle (moteur, miroir local), base PostgreSQL et PostgREST réels.
 */

const racine = fileURLToPath(new URL('../../', import.meta.url))

function artisan(...arguments_: string[]): string {
  return execFileSync('docker', ['compose', 'exec', '-T', '-u', '1000', 'api', 'php', 'artisan', ...arguments_], { cwd: racine, encoding: 'utf8' })
}

/** Le dernier code envoyé à ce numéro par le SMS simulé (le plus récent en tête). */
async function codeRecu(browser: Browser, numero: string): Promise<string> {
  const contexte = await browser.newContext()
  const reponse = await contexte.request.get(`http://localhost:8000/simulateur/sms?numero=${encodeURIComponent(numero)}`)
  const { data } = (await reponse.json()) as { data: Array<{ message: string }> }
  await contexte.close()
  const code = /^\d{6}/.exec(data[0]?.message ?? '')?.[0]
  expect(code, `aucun SMS pour ${numero}`).toBeTruthy()
  return code ?? ''
}

/** Un utilisateur de la copie, dans son propre navigateur : numéro, code reçu, connexion. */
async function connecter(browser: Browser, adresse: string, numero: string) {
  const page = await browser.newPage()
  await page.goto(adresse)
  await page.getByLabel('Votre numéro (format international)').fill(numero)
  await page.getByRole('button', { name: 'Recevoir un code par SMS' }).click()
  await expect(page.getByRole('status')).toHaveText('Code envoyé par SMS.')
  await page.getByLabel('Code reçu par SMS').fill(await codeRecu(browser, numero))
  await page.getByRole('button', { name: 'Se connecter' }).click()
  await expect(page.getByRole('button', { name: 'Se déconnecter' })).toBeVisible()
  return page
}

test.describe.serial('backend intégré', () => {
  let copie = 0

  test.beforeAll(() => {
    artisan('db:seed', '--class=DemoSeeder', '--force')
    // Une copie de Carnet déjà payée et en ligne pour la pharmacie (le paiement a son propre parcours).
    const sortie = artisan('tinker', '--execute', `
      $appli = App\\Models\\Appli::query()->where("depot_url", "https://github.com/soukdemo/carnet")->firstOrFail();
      $client = App\\Models\\User::query()->where("username", "soukdemo-pharmacie")->firstOrFail();
      $copie = App\\Models\\Deploiement::query()->create(["user_id" => $client->id, "appli_id" => $appli->id, "type" => "client", "taille" => "petite", "etat" => "en_file", "variables" => []]);
      $copie->forceFill(["version_id" => $appli->versionPubliee->id])->save();
      App\\Jobs\\LancerDeploiement::dispatch($copie->id);
      echo "COPIE=".$copie->id;`)
    copie = Number(/COPIE=(\d+)/.exec(sortie)?.[1])
  })

  test('chaque utilisateur se connecte par un code SMS et ne voit que ses notes', async ({ page, browser }) => {
    test.setTimeout(300_000)
    expect(copie).toBeGreaterThan(0)
    await connexionDemo(page, 'soukdemo-pharmacie')
    await expect.poll(async () => ((await (await page.request.get(`/api/deployments/${copie}`, { headers: { Accept: 'application/json', Referer: 'http://localhost:5173/' } })).json()) as { etat: string }).etat, { timeout: 240_000, intervals: [3_000] }).toBe('en_ligne')
    const adresse = `http://d${copie}.localhost/`
    // Le proxy prend la nouvelle copie en quelques secondes.
    await expect.poll(async () => (await page.request.get(adresse, { failOnStatusCode: false })).status(), { timeout: 60_000, intervals: [2_000] }).toBe(200)

    const vendeuse = await connecter(browser, adresse, '+22901970000031')
    await expect(vendeuse.getByText('Aucune commande pour le moment.')).toBeVisible()
    await vendeuse.getByLabel('Nouvelle commande').fill('Mme Hounkpè : 3 sacs de riz, livraison samedi')
    await vendeuse.getByRole('button', { name: 'Ajouter' }).click()
    await expect(vendeuse.getByRole('status')).toHaveText('Commande ajoutée.')
    await expect(vendeuse.getByRole('listitem').filter({ hasText: '3 sacs de riz' })).toBeVisible()
    expect(resumer(await auditAxe(vendeuse))).toBe('')

    // Un autre utilisateur de la même copie : sa propre session, aucune note de la première.
    const voisin = await connecter(browser, adresse, '+22901970000032')
    await expect(voisin.getByText('Aucune commande pour le moment.')).toBeVisible()
    await expect(voisin.getByText('3 sacs de riz')).toBeHidden()

    // Sans jeton, l'API de la copie ne rend rien.
    expect(await (await page.request.get(`${adresse}donnees/notes`)).json()).toEqual([])
    await vendeuse.close()
    await voisin.close()
  })

  test('le client sauvegarde sa copie puis télécharge l’export avec la base', async ({ page }) => {
    test.setTimeout(240_000)
    await connexionDemo(page, 'soukdemo-pharmacie')
    await page.goto('/copies')
    const carte = page.getByRole('article', { name: 'Carnet de commandes' }).filter({ hasText: `d${copie}.localhost` })
    await expect(carte.getByText('Sauvegardée chaque nuit, gardée 7 jours.')).toBeVisible()
    await carte.getByRole('button', { name: 'Sauvegarder maintenant' }).click()
    await expect(carte.getByText(/Sauvegarde demandée/)).toBeVisible()
    // La fin de la sauvegarde arrive en direct (Reverb), sans recharger la page.
    await expect(carte.getByText(/Sauvegarde du .* \(à votre demande\)/)).toBeVisible({ timeout: 180_000 })
    await expect(carte.getByRole('button', { name: 'Sauvegarder maintenant' })).toBeVisible()
    expect(resumer(await auditAxe(page))).toBe('')

    const telechargement = page.waitForEvent('download')
    await carte.getByRole('link', { name: 'Exporter' }).click()
    const fichier = await telechargement
    expect(fichier.suggestedFilename()).toMatch(/^sauvegarde-carnet-de-commandes-\d{8}-\d{6}\.tar$/)
    const contenu = execFileSync('tar', ['tf', await fichier.path()], { encoding: 'utf8' }).split('\n')
    expect(contenu).toEqual(expect.arrayContaining(['sauvegarde.json', 'base.dump']))
  })
})
