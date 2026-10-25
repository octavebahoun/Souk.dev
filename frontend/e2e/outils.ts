import { expect, type Page } from '@playwright/test'

/** Ouvre la session d'un compte du jeu de démo (POST /auth/demo, DEMO_CONNEXION=true). */
export async function connexionDemo(page: Page, compte: string): Promise<void> {
  await page.request.get('/sanctum/csrf-cookie')
  const cookies = await page.context().cookies()
  const jeton = cookies.find((cookie) => cookie.name === 'XSRF-TOKEN')?.value
  expect(jeton, 'cookie XSRF-TOKEN absent').toBeTruthy()
  const reponse = await page.request.post('/auth/demo', {
    data: { compte },
    headers: { 'X-XSRF-TOKEN': decodeURIComponent(jeton ?? ''), Accept: 'application/json' },
  })
  expect(reponse.status(), `connexion de démo de ${compte}`).toBe(204)
}

// axe-core (MPL-2.0) n'est pas une dépendance du projet : chargé seulement pendant l'audit (LICENSES.md).
const AXE = 'https://cdnjs.cloudflare.com/ajax/libs/axe-core/4.10.2/axe.min.js'

type Violation = { id: string; impact: string | null; help: string; nodes: Array<{ target: string[] }> }

/** Audit axe (WCAG 2.2 A et AA) : renvoie les violations sérieuses ou critiques. */
export async function auditAxe(page: Page): Promise<Violation[]> {
  await page.addScriptTag({ url: AXE })
  const violations = await page.evaluate(async () => {
    const resultat = await (window as unknown as { axe: { run: (contexte: Document, options: object) => Promise<{ violations: Violation[] }> } }).axe.run(document, {
      runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] },
    })
    return resultat.violations
  })
  return violations.filter((violation) => violation.impact === 'serious' || violation.impact === 'critical')
}

export function resumer(violations: Violation[]): string {
  return violations.map((violation) => `${violation.id} (${violation.impact}) : ${violation.help} — ${violation.nodes.map((noeud) => noeud.target.join(' ')).slice(0, 3).join(' | ')}`).join('\n')
}
