import { createBrowserRouter } from 'react-router'
import { Accueil } from '../features/accueil/Accueil'
import { Copies } from '../features/catalogue/Copies'
import { Deployer } from '../features/catalogue/Deployer'
import { Evenements } from '../features/catalogue/Evenements'
import { Fiche } from '../features/catalogue/Fiche'
import { Missions } from '../features/catalogue/Missions'
import { Publier } from '../features/catalogue/Publier'
import { Recherche } from '../features/catalogue/Recherche'
import { Store } from '../features/catalogue/Store'
import { Fil } from '../features/echange/Fil'
import { PageDiscussion } from '../features/echange/PageDiscussion'
import { Fournisseurs } from './Fournisseurs'
import { Layout } from './Layout'
import { PageEnAttente } from './PageEnAttente'

export const routes = [
  {
    element: <Fournisseurs />,
    children: [
      { path: '/', element: <Accueil /> },
      { path: '/connexion', element: <PageEnAttente titre="Connexion" /> },
      {
        element: <Layout />,
        children: [
          { path: '/echange', element: <Fil /> },
          { path: '/echange/:id', element: <PageDiscussion /> },
          { path: '/store', element: <Store /> },
          { path: '/store/:id', element: <Fiche /> },
          { path: '/store/:id/deployer', element: <Deployer /> },
          { path: '/devs', element: <Recherche /> },
          { path: '/evenements', element: <Evenements /> },
          { path: '/missions', element: <Missions /> },
          { path: '/devs/:username', element: <PageEnAttente titre="Profil" /> },
          { path: '/tableau', element: <PageEnAttente titre="Tableau de bord" /> },
          { path: '/copies', element: <Copies /> },
          { path: '/publier', element: <Publier /> },
        ],
      },
      { path: '*', element: <PageEnAttente titre="Page introuvable" /> },
    ],
  },
]

export function creerRouter() {
  return createBrowserRouter(routes)
}
