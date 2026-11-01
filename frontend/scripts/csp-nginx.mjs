// Écrit la politique de sécurité du contenu (CSP) du front pour nginx, après `vite build`.
// Les deux petits scripts en ligne de index.html (thème sans flash, préchargement de la route)
// sont autorisés par leur empreinte : aucun autre script en ligne ne peut s'exécuter.
//
// Usage : node scripts/csp-nginx.mjs dist/index.html sortie.conf
// Origines lues dans l'environnement du build : VITE_API_BASE (API) et VITE_REVERB_* (WebSocket).
import { createHash } from 'node:crypto'
import { readFileSync, writeFileSync } from 'node:fs'

const [html, sortie] = process.argv.slice(2)
if (!html || !sortie) {
  console.error('Usage : node scripts/csp-nginx.mjs dist/index.html sortie.conf')
  process.exit(1)
}

const contenu = readFileSync(html, 'utf8')
const empreintes = [...contenu.matchAll(/<script>([\s\S]*?)<\/script>/g)].map(
  ([, code]) => `'sha256-${createHash('sha256').update(code, 'utf8').digest('base64')}'`,
)

const origine = (adresse) => {
  try {
    return adresse ? new URL(adresse).origin : ''
  } catch {
    console.error(`Adresse invalide : ${adresse}`)
    process.exit(1)
  }
}

const api = origine(process.env.VITE_API_BASE)
const { VITE_REVERB_HOST: hote, VITE_REVERB_PORT: port, VITE_REVERB_SCHEME: schema } = process.env
const ws = hote ? `${schema === 'https' ? 'wss' : 'ws'}://${hote}${port && !['80', '443'].includes(port) ? `:${port}` : ''}` : ''

const politique = [
  "default-src 'self'",
  `script-src 'self' ${empreintes.join(' ')}`,
  // Les styles en ligne (coquille de index.html, positions calculées) restent permis : sans risque d'exécution.
  "style-src 'self' 'unsafe-inline'",
  // Captures servies par l'API, avatars GitHub.
  "img-src 'self' data: blob: https:",
  "font-src 'self'",
  `connect-src 'self' ${api} ${ws}`,
  // Copies de test et démos des discussions, chacune dans un cadre isolé (sandbox).
  'frame-src https:',
  "frame-ancestors 'none'",
  "base-uri 'self'",
  `form-action 'self' ${api}`,
  "object-src 'none'",
]
  .map((directive) => directive.replace(/\s+/g, ' ').trim())
  .join('; ')

writeFileSync(sortie, `add_header Content-Security-Policy "${politique}" always;\n`)
console.log(`CSP écrite dans ${sortie} (${empreintes.length} scripts en ligne autorisés).`)
