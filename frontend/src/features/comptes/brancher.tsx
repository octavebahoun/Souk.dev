import type { RouteObject } from 'react-router'

function remplacer(routes: RouteObject[], chemin: string, charger: NonNullable<RouteObject['lazy']>) {
  for (const route of routes) {
    if (route.path === chemin) {
      delete route.element
      route.lazy = charger
    }
    if (route.children) remplacer(route.children, chemin, charger)
  }
}

export function brancherComptes(routes: RouteObject[]) {
  remplacer(routes, '/connexion', async () => ({ Component: (await import('./Connexion')).Connexion }))
  remplacer(routes, '/devs/:username', async () => ({ Component: (await import('./Profil')).Profil }))
  remplacer(routes, '/tableau', async () => ({ Component: (await import('./Tableau')).Tableau }))
}
