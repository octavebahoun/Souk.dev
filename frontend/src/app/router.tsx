import { createBrowserRouter } from 'react-router'
import type { RouteObject } from 'react-router'
import { Fournisseurs } from './Fournisseurs'
import { Layout } from './Layout'
import { PageEnAttente } from './PageEnAttente'
import { brancherComptes } from '../features/comptes/brancher'

export function creerRoutes(): RouteObject[] {
  const routes: RouteObject[] = [
  {
    element: <Fournisseurs />,
    hydrateFallbackElement: (
      <main role="status" aria-label="Chargement de Souk.dev" className="grid min-h-svh place-items-center bg-canvas">
        <span aria-hidden="true" className="squelette h-2 w-32 rounded-full" />
      </main>
    ),
    children: [
      { index: true, lazy: async () => ({ Component: (await import('../features/accueil/Accueil')).Accueil }) },
      { path: '/connexion', element: <PageEnAttente titre="Connexion" /> },
      {
        element: <Layout />,
        children: [
          { path: '/echange', lazy: async () => ({ Component: (await import('../features/echange/Fil')).Fil }) },
          { path: '/echange/:id', lazy: async () => ({ Component: (await import('../features/echange/PageDiscussion')).PageDiscussion }) },
          { path: '/store', lazy: async () => ({ Component: (await import('../features/catalogue/Store')).Store }) },
          { path: '/store/:id', lazy: async () => ({ Component: (await import('../features/catalogue/Fiche')).Fiche }) },
          { path: '/store/:id/deployer', lazy: async () => ({ Component: (await import('../features/catalogue/Deployer')).Deployer }) },
          { path: '/devs', lazy: async () => ({ Component: (await import('../features/catalogue/Recherche')).Recherche }) },
          { path: '/evenements', lazy: async () => ({ Component: (await import('../features/catalogue/Evenements')).Evenements }) },
          { path: '/missions', lazy: async () => ({ Component: (await import('../features/catalogue/Missions')).Missions }) },
          { path: '/devs/:username', element: <PageEnAttente titre="Profil" /> },
          { path: '/tableau', element: <PageEnAttente titre="Tableau de bord" /> },
          { path: '/copies', lazy: async () => ({ Component: (await import('../features/catalogue/Copies')).Copies }) },
          { path: '/publier', lazy: async () => ({ Component: (await import('../features/catalogue/Publier')).Publier }) },
        ],
      },
      { path: '*', element: <PageEnAttente titre="Page introuvable" /> },
    ],
  },
  ]
  brancherComptes(routes)
  return routes
}

export function creerRouter() {
  return createBrowserRouter(creerRoutes())
}
