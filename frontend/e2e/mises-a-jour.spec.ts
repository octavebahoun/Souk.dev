import { execFileSync } from 'node:child_process'
import { fileURLToPath } from 'node:url'
import { expect, test, type Page } from '@playwright/test'
import { auditAxe, connexionDemo, resumer } from './outils'

/**
 * Phase 5 — mises à jour poussées : l'autrice publie une version depuis la fiche, le client
 * l'applique depuis « Mes copies » et sa copie la sert ; une version qui ne démarre pas revient
 * d'elle-même à la précédente, avec ses données. Copies et versions réelles (moteur, miroir local).
 */

const racine = fileURLToPath(new URL('../../', import.meta.url))

function artisan(...arguments_: string[]): string {
  return execFileSync('docker', ['compose', 'exec', '-T', '-u', '1000', 'api', 'php', 'artisan', ...arguments_], { cwd: racine, encoding: 'utf8' })
}

/** Ouvre l'adresse de la copie jusqu'à lire le texte attendu (le proxy recharge ses routes). */
async function lireLaCopie(page: Page, adresse: string): Promise<string> {
  const reponse = await page.request.get(adresse, { failOnStatusCode: false })
  return reponse.ok() ? await reponse.text() : ''
}

async function publierUneVersion(page: Page, notes: string, ref: string) {
  const versions = page.getByRole('region', { name: 'Versions' })
  await versions.getByLabel('Ce qui change pour vos clients').fill(notes)
  await versions.getByLabel(/Branche, tag ou commit/).fill(ref)
  await versions.getByRole('button', { name: 'Publier la version' }).click()
  // La version se vérifie comme une publication (soukdev.json, docker-compose.yml, secrets).
  await expect(versions.getByText('En vérification')).toBeHidden({ timeout: 120_000 })
  await expect(versions.getByText(notes)).toBeVisible()
}

async function appliquer(page: Page, copie: number, numero: number) {
  await page.goto('/copies')
  // La pharmacie a plusieurs copies de PharmaStock : celle-ci se reconnaît à son adresse.
  const carte = page.getByRole('article', { name: 'PharmaStock' }).filter({ hasText: `d${copie}.localhost` })
  await expect(carte.getByText(`Version ${numero} disponible`)).toBeVisible({ timeout: 30_000 })
  await carte.getByRole('button', { name: 'Mettre à jour' }).click()
  await page.getByRole('dialog', { name: new RegExp(`version ${numero}`) }).getByRole('button', { name: 'Mettre à jour' }).click()
  await expect(carte.getByText(`Passage à la version ${numero} en cours`)).toBeVisible()
  return carte
}

test.describe.serial('mises à jour poussées', () => {
  let copie = 0
  let appli = 0

  test.beforeAll(() => {
    artisan('db:seed', '--class=DemoSeeder', '--force')
    // Une copie de PharmaStock déjà payée et en ligne pour la pharmacie (le paiement a son propre parcours).
    const sortie = artisan('tinker', '--execute', `
      $appli = App\\Models\\Appli::query()->where("nom", "PharmaStock")->firstOrFail();
      $client = App\\Models\\User::query()->where("username", "soukdemo-pharmacie")->firstOrFail();
      $copie = App\\Models\\Deploiement::query()->create(["user_id" => $client->id, "appli_id" => $appli->id, "type" => "client", "taille" => "petite", "etat" => "en_file", "variables" => []]);
      $copie->forceFill(["version_id" => $appli->versionPubliee->id])->save();
      App\\Jobs\\LancerDeploiement::dispatch($copie->id);
      echo "COPIE=".$copie->id." APPLI=".$appli->id;`)
    copie = Number(/COPIE=(\d+)/.exec(sortie)?.[1])
    appli = Number(/APPLI=(\d+)/.exec(sortie)?.[1])
  })

  test('la version 2 publiée par l’autrice est appliquée par le client, données sauvegardées avant', async ({ page, browser }) => {
    test.setTimeout(420_000)
    expect(copie).toBeGreaterThan(0)
    await connexionDemo(page, 'soukdemo-pharmacie')
    await expect.poll(async () => ((await (await page.request.get(`/api/deployments/${copie}`, { headers: { Accept: 'application/json', Referer: 'http://localhost:5173/' } })).json()) as { etat: string }).etat, { timeout: 240_000, intervals: [3_000] }).toBe('en_ligne')

    const autrice = await browser.newPage()
    await connexionDemo(autrice, 'soukdemo-awa')
    await autrice.goto(`/store/${appli}`)
    await publierUneVersion(autrice, 'Export PDF des ventes du jour.', 'v2')
    expect(resumer(await auditAxe(autrice))).toBe('')

    const carte = await appliquer(page, copie, 2)
    await expect(carte.getByText(/En ligne · commandée le .* Version 2\./)).toBeVisible({ timeout: 240_000 })
    expect(resumer(await auditAxe(page))).toBe('')
    await expect.poll(async () => lireLaCopie(page, `http://d${copie}.localhost/`), { timeout: 60_000, intervals: [2_000] }).toContain('Nouveau dans la version 2')
    await autrice.close()
  })

  test('une version qui ne démarre pas revient d’elle-même à la précédente', async ({ page, browser }) => {
    test.setTimeout(420_000)
    const autrice = await browser.newPage()
    await connexionDemo(autrice, 'soukdemo-awa')
    await autrice.goto(`/store/${appli}`)
    await publierUneVersion(autrice, 'Nouveau module de paiement.', 'casse')
    await autrice.close()

    await connexionDemo(page, 'soukdemo-pharmacie')
    const carte = await appliquer(page, copie, 3)
    // Le retour arrière attend la sonde de la version cassée (environ une minute).
    await expect(carte.getByText('La version 3 n’a pas été appliquée')).toBeVisible({ timeout: 300_000 })
    await expect(carte.getByText(/Votre copie tourne sur la version 2, avec ses données/)).toBeVisible()
    expect(resumer(await auditAxe(page))).toBe('')
    expect(await lireLaCopie(page, `http://d${copie}.localhost/`)).toContain('Nouveau dans la version 2')
  })
})
