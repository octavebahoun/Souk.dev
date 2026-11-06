import { createBrowserRouter } from 'react-router'
import type { RouteObject } from 'react-router'
import { Fournisseurs } from './Fournisseurs'
import { Layout } from './Layout'
import { LayoutEspace } from './LayoutEspace'
import { PageIntrouvable } from './PageIntrouvable'

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
          // Pages publiques, dans la coquille du site (thème clair ou sombre).
          { index: true, lazy: async () => ({ Component: (await import('../features/accueil/Accueil')).Accueil }) },
          { path: '/connexion', lazy: async () => ({ Component: (await import('../features/comptes/Connexion')).Connexion }) },
          { path: '/store', lazy: async () => ({ Component: (await import('../features/catalogue/Store')).Store }) },
          { path: '/echange', lazy: async () => ({ Component: (await import('../features/echange/Fil')).Fil }) },
          { path: '/echange/:id', lazy: async () => ({ Component: (await import('../features/echange/PageDiscussion')).PageDiscussion }) },
          { path: '/devs', lazy: async () => ({ Component: (await import('../features/catalogue/Recherche')).Recherche }) },
          { path: '/evenements', lazy: async () => ({ Component: (await import('../features/catalogue/Evenements')).Evenements }) },
          { path: '/devs/:username', lazy: async () => ({ Component: (await import('../features/comptes/Profil')).Profil }) },
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
              { path: '/tableau', lazy: async () => ({ Component: (await import('../features/comptes/Tableau')).Tableau }) },
              { path: '/profil', lazy: async () => ({ Component: (await import('../features/comptes/ModifierProfil')).ModifierProfil }) },
              { path: '/missions', lazy: async () => ({ Component: (await import('../features/catalogue/Missions')).Missions }) },
              { path: '/publier', lazy: async () => ({ Component: (await import('../features/catalogue/Publier')).Publier }) },
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
  return routes
}

export function creerRouter() {
  return createBrowserRouter(creerRoutes())
}
