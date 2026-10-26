import { test, type Page } from '@playwright/test'
import { connexionDemo } from './outils'

/**
 * Captures de chaque page aux trois tailles (mobile, tablette, bureau) et dans les deux thèmes,
 * à regarder et critiquer (BRIEF.md, section 10). Sortie : e2e/captures/ (non commité).
 * PAGES=/store,/paiements npm run e2e:captures pour n'en refaire que quelques-unes
 * (les identifiants sont remplacés par {id} : /paiements/{id}).
 */
const tailles = { mobile: { width: 390, height: 844 }, tablette: { width: 834, height: 1112 }, bureau: { width: 1440, height: 900 } }

const filtre = process.env.PAGES?.split(',').filter(Boolean)

type Lot = { compte: string | null; pages: (page: Page) => Promise<string[]> }

async function idDe(page: Page, chemin: string, choisir: (donnees: Array<Record<string, unknown>>) => unknown): Promise<string> {
  // Sanctum ne lit la session que pour une requête venue du front (Referer).
  const reponse = await page.request.get(`/api${chemin}`, { headers: { Accept: 'application/json', Referer: `${process.env.E2E_URL || 'http://localhost:5173'}/` } })
  const corps = (await reponse.json()) as { data?: Array<Record<string, unknown>> }
  if (!Array.isArray(corps.data)) throw new Error(`${chemin} : ${reponse.status()} ${JSON.stringify(corps).slice(0, 200)}`)
  return String(choisir(corps.data) ?? '0')
}

const lots: Lot[] = [
  {
    compte: null,
    pages: async (page) => {
      const appli = await idDe(page, '/apps?q=PharmaStock', (applis) => applis[0]?.id)
      return ['/', '/store', '/store?tri=note', `/store/${appli}`, '/echange', '/devs', '/evenements', '/connexion', '/page-inconnue']
    },
  },
  { compte: 'soukdemo-awa', pages: async () => ['/tableau', '/copies', '/publier', '/missions', '/revenus', '/notifications', '/notifications/reglages'] },
  {
    compte: 'soukdemo-pharmacie',
    pages: async (page) => {
      const appli = await idDe(page, '/apps?q=PharmaStock', (applis) => applis[0]?.id)
      const payee = await idDe(page, '/factures?statut=payee', (factures) => factures[0]?.id)
      return ['/copies', '/paiements', `/paiements/${payee}`, `/store/${appli}`, `/store/${appli}/deployer`, `/paiement/retour?facture=${payee}`]
    },
  },
  { compte: 'soukdemo-admin', pages: async () => ['/admin/finances'] },
]

for (const theme of ['light', 'dark'] as const) {
  for (const [taille, viewport] of Object.entries(tailles)) {
    test(`captures ${taille} ${theme}`, async ({ browser }) => {
      test.setTimeout(300_000)
      for (const lot of lots) {
        const contexte = await browser.newContext({ viewport, colorScheme: theme, locale: 'fr-FR', baseURL: process.env.E2E_URL || 'http://localhost:5173' })
        const page = await contexte.newPage()
        if (lot.compte) await connexionDemo(page, lot.compte)
        for (const chemin of await lot.pages(page)) {
          const generique = chemin.replace(/\d+/g, '{id}')
          if (filtre && !filtre.includes(generique) && !filtre.includes(chemin)) continue
          await page.goto(chemin, { waitUntil: 'networkidle' })
          await page.waitForTimeout(500)
          const nom = (generique.replace(/^\//, '').replace(/[/?=&{}]/g, '_') || 'accueil').replace(/_+/g, '_').replace(/_$/, '')
          await page.screenshot({ path: `e2e/captures/${nom}-${lot.compte?.replace('soukdemo-', '') ?? 'visiteur'}-${taille}-${theme}.png`, fullPage: true })
        }
        await contexte.close()
      }
    })
  }
}
