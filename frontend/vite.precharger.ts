import type { HtmlTagDescriptor, Plugin, Rolldown } from 'vite'

type Regle = { motif: string; module: string; images?: string[] }

/**
 * Pages mesurées par Lighthouse : le morceau de JavaScript de leur route et leur image principale.
 * Sans préchargement, le navigateur ne les découvre qu'après avoir exécuté l'application, ce qui
 * retarde le premier affichage utile sur mobile.
 */
const regles: Regle[] = [
  { motif: '^/$', module: 'src/features/accueil/Accueil.tsx', images: ['src/assets/accueil/hero.webp'] },
  { motif: '^/store/?$', module: 'src/features/catalogue/Store.tsx' },
  { motif: '^/store/[^/]+/deployer/?$', module: 'src/features/paiements/Commande.tsx' },
  { motif: '^/store/[^/]+/?$', module: 'src/features/catalogue/Fiche.tsx' },
  { motif: '^/copies/?$', module: 'src/features/deploiements/MesCopies.tsx' },
  { motif: '^/paiements/?$', module: 'src/features/paiements/MesPaiements.tsx' },
]

/** Police des titres, utilisée sur toutes les pages : demandée avec le HTML plutôt qu'après la CSS. */
const polices = ['outfit-latin-wght-normal']

/**
 * Ajoute au HTML un petit script qui précharge, selon l'adresse demandée, la route et son image
 * principale (le HTML est le même pour toutes les pages), plus la police des titres.
 */
export function prechargerRoutes(): Plugin {
  let base = '/'

  return {
    name: 'souk-precharger-routes',
    apply: 'build',
    configResolved(config) {
      base = config.base
    },
    transformIndexHtml: {
      order: 'post',
      handler(html, ctx) {
        if (!ctx.bundle) return html
        const fichiers = Object.values(ctx.bundle)
        const morceaux = new Map(fichiers.filter((f): f is Rolldown.OutputChunk => f.type === 'chunk').map((f) => [f.fileName, f]))
        const entree = [...morceaux.values()].find((f) => f.isEntry)
        const dejaCharges = new Set<string>(entree ? [entree.fileName, ...dependances(entree, morceaux)] : [])

        const table = regles.flatMap((regle) => {
          const morceau = [...morceaux.values()].find((f) => f.facadeModuleId?.endsWith(regle.module))
          if (!morceau) return []
          const modules = [morceau.fileName, ...dependances(morceau, morceaux)].filter((nom) => !dejaCharges.has(nom))
          const images = (regle.images ?? []).flatMap((source) => {
            const image = fichiers.find((f) => f.type === 'asset' && f.originalFileNames.some((nom) => nom.endsWith(source)))
            return image ? [image.fileName] : []
          })
          return [[regle.motif, modules, images]]
        })

        const script = `(function(){var p=location.pathname,t=${JSON.stringify(table)};function l(r,h,a){var e=document.createElement('link');e.rel=r;e.href=${JSON.stringify(base)}+h;if(a){e.as=a;e.setAttribute('fetchpriority','high')}document.head.appendChild(e)}for(var i=0;i<t.length;i++){if(new RegExp(t[i][0]).test(p)){t[i][1].forEach(function(h){l('modulepreload',h)});t[i][2].forEach(function(h){l('preload',h,'image')});break}}})()`

        const tags: HtmlTagDescriptor[] = [{ tag: 'script', children: script, injectTo: 'head' }]
        for (const police of polices) {
          const fichier = fichiers.find((f) => f.type === 'asset' && f.fileName.includes(police) && f.fileName.endsWith('.woff2'))
          if (fichier) {
            tags.push({ tag: 'link', attrs: { rel: 'preload', as: 'font', type: 'font/woff2', href: base + fichier.fileName, crossorigin: '' }, injectTo: 'head' })
          }
        }

        return { html, tags }
      },
    },
  }
}

/** Dépendances statiques d'un morceau, de proche en proche. */
function dependances(morceau: Rolldown.OutputChunk, morceaux: Map<string, Rolldown.OutputChunk>, vus = new Set<string>()): string[] {
  for (const nom of morceau.imports) {
    if (vus.has(nom)) continue
    vus.add(nom)
    const suivant = morceaux.get(nom)
    if (suivant) dependances(suivant, morceaux, vus)
  }
  return [...vus]
}
