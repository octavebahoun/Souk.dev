import { createBrowserRouter } from 'react-router'
import { Layout } from './Layout'
import { PageEnAttente } from './PageEnAttente'

export const routes = [
  { path: '/', element: <PageEnAttente titre="Accueil" /> },
  { path: '/connexion', element: <PageEnAttente titre="Connexion" /> },
  {
    element: <Layout />,
    children: [
      { path: '/echange', element: <PageEnAttente titre="Échange" /> },
      { path: '/store', element: <PageEnAttente titre="Store" /> },
      { path: '/devs', element: <PageEnAttente titre="Trouver un dev" /> },
      { path: '/evenements', element: <PageEnAttente titre="Événements" /> },
      { path: '/missions', element: <PageEnAttente titre="Missions" /> },
    ],
  },
  { path: '*', element: <PageEnAttente titre="Page introuvable" /> },
]

export function creerRouter() {
  return createBrowserRouter(routes)
}
