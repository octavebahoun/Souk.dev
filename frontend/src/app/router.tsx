import { createBrowserRouter } from 'react-router'
import type { RouteObject } from 'react-router'
import { Fournisseurs } from './Fournisseurs'
import { Layout } from './Layout'
import { LayoutEspace } from './LayoutEspace'
import { PageIntrouvable } from './PageIntrouvable'
import { PageEnAttente } from './PageEnAttente'
import { ThemeClairForce } from './ThemeClairForce'
import { brancherComptes } from '../features/comptes/brancher'

export function creerRoutes(): RouteObject[] {
  const routes: RouteObject[] = [
  {
    element: <Fournisseurs />,
    hydrateFallbackElement: (
      <div role="status" aria-label="Chargement de Souk.dev" className="grid min-h-svh place-items-center bg-fond">
        <span aria-hidden="true" className="squelette block h-2 w-32 rounded-pastille" />
      </div>
    ),
    children: [
      {
        element: <Layout />,
        children: [
          {
            // Écrans d'avant la refonte : thème clair forcé jusqu'à leur redessin.
            element: <ThemeClairForce />,
            children: [
          { path: '/devs/:username', element: <PageEnAttente titre="Profil" /> },
            ],
          },
          // Écrans refaits avec le système de design (thème clair ou sombre).
          { index: true, lazy: async () => ({ Component: (await import('../features/accueil/Accueil')).Accueil }) },
          { path: '/connexion', element: <PageEnAttente titre="Connexion" /> },
          { path: '/store', lazy: async () => ({ Component: (await import('../features/catalogue/Store')).Store }) },
          { path: '/echange', lazy: async () => ({ Component: (await import('../features/echange/Fil')).Fil }) },
          { path: '/echange/:id', lazy: async () => ({ Component: (await import('../features/echange/PageDiscussion')).PageDiscussion }) },
          { path: '/devs', lazy: async () => ({ Component: (await import('../features/catalogue/Recherche')).Recherche }) },
          { path: '/evenements', lazy: async () => ({ Component: (await import('../features/catalogue/Evenements')).Evenements }) },
          { path: '/store/:id', lazy: async () => ({ Component: (await import('../features/catalogue/Fiche')).Fiche }) },
          { path: '/store/:id/deployer', lazy: async () => ({ Component: (await import('../features/paiements/Commande')).Commande }) },
          { path: '/paiement/retour', lazy: async () => ({ Component: (await import('../features/paiements/RetourPaiement')).RetourPaiement }) },
          { path: '/legal/cgu', lazy: async () => ({ Component: (await import('../features/legal/Legal')).Cgu }) },
          { path: '/legal/cgv', lazy: async () => ({ Component: (await import('../features/legal/Legal')).Cgv }) },
          { path: '/legal/confidentialite', lazy: async () => ({ Component: (await import('../features/legal/Legal')).Confidentialite }) },
          { path: '/legal/abus', lazy: async () => ({ Component: (await import('../features/legal/Legal')).PolitiqueAbus }) },
          { path: '*', element: <PageIntrouvable /> },
        ],
      },
      {
        // Espace connecté : navigation latérale, pas de pied de page.
        element: <Layout avecPied={false} />,
        children: [
          {
            element: <LayoutEspace />,
            children: [
              {
                element: <ThemeClairForce />,
                children: [
              { path: '/tableau', element: <PageEnAttente titre="Tableau de bord" /> },
              { path: '/publier', lazy: async () => ({ Component: (await import('../features/catalogue/Publier')).Publier }) },
              { path: '/missions', lazy: async () => ({ Component: (await import('../features/catalogue/Missions')).Missions }) },
                ],
              },
              { path: '/copies', lazy: async () => ({ Component: (await import('../features/deploiements/MesCopies')).MesCopies }) },
              { path: '/paiements', lazy: async () => ({ Component: (await import('../features/paiements/MesPaiements')).MesPaiements }) },
              { path: '/paiements/:id', lazy: async () => ({ Component: (await import('../features/paiements/FacturePage')).FacturePage }) },
              { path: '/revenus', lazy: async () => ({ Component: (await import('../features/revenus/MesRevenus')).MesRevenus }) },
              { path: '/notifications', lazy: async () => ({ Component: (await import('../features/notifications/PageNotifications')).PageNotifications }) },
              { path: '/notifications/reglages', lazy: async () => ({ Component: (await import('../features/notifications/ReglagesNotifications')).ReglagesNotifications }) },
              { path: '/admin/finances', lazy: async () => ({ Component: (await import('../features/admin/Finances')).Finances }) },
              { path: '/admin/serveurs', lazy: async () => ({ Component: (await import('../features/admin/Serveurs')).Serveurs }) },
              { path: '/admin/moderation', lazy: async () => ({ Component: (await import('../features/admin/Moderation')).Moderation }) },
            ],
          },
        ],
      },
    ],
  },
  ]
  brancherComptes(routes)
  return routes
}

export function creerRouter() {
  return createBrowserRouter(creerRoutes())
}
