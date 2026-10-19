import type { RouteObject } from 'react-router'
import { Connexion } from './Connexion'
import { Profil } from './Profil'
import { Tableau } from './Tableau'

let fait = false

function remplacer(routes: RouteObject[], chemin: string, element: RouteObject['element']) {
  for (const route of routes) {
    if (route.path === chemin) route.element = element
    if (route.children) remplacer(route.children, chemin, element)
  }
}

export function brancherComptes(routes: RouteObject[]) {
  if (fait) return
  fait = true
  remplacer(routes, '/connexion', <Connexion />)
  remplacer(routes, '/devs/:username', <Profil />)
  remplacer(routes, '/tableau', <Tableau />)
}
