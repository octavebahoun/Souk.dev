import { test } from '@playwright/test'
import { connexionDemo } from './outils'

/**
 * Captures de chaque page aux trois tailles (mobile, tablette, bureau) et dans les deux thèmes,
 * à regarder et critiquer (BRIEF.md, section 10). Sortie : e2e/captures/ (non commité).
 * PAGES=/store,/echange npm run e2e:captures pour n'en refaire que quelques-unes.
 */
const tailles = { mobile: { width: 390, height: 844 }, tablette: { width: 834, height: 1112 }, bureau: { width: 1440, height: 900 } }

const pagesPubliques = ['/', '/store', '/store/1', '/echange', '/devs', '/evenements', '/connexion', '/page-inconnue']
const pagesConnectees = ['/tableau', '/copies', '/publier', '/missions']
const filtre = process.env.PAGES?.split(',').filter(Boolean)

for (const theme of ['light', 'dark'] as const) {
  for (const [taille, viewport] of Object.entries(tailles)) {
    test(`captures ${taille} ${theme}`, async ({ browser }) => {
      const contexte = await browser.newContext({ viewport, colorScheme: theme, locale: 'fr-FR', baseURL: process.env.E2E_URL || 'http://localhost:5173' })
      const page = await contexte.newPage()
      const prendre = async (chemin: string) => {
        if (filtre && !filtre.includes(chemin)) return
        await page.goto(chemin, { waitUntil: 'networkidle' })
        await page.waitForTimeout(400)
        const nom = chemin.replace(/^\//, '').replace(/\//g, '_') || 'accueil'
        await page.screenshot({ path: `e2e/captures/${nom}-${taille}-${theme}.png`, fullPage: true })
      }
      for (const chemin of pagesPubliques) await prendre(chemin)
      await connexionDemo(page, 'soukdemo-awa')
      for (const chemin of pagesConnectees) await prendre(chemin)
      await contexte.close()
    })
  }
}
