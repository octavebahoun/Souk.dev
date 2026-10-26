import { Bell, Briefcase, CalendarDays, CreditCard, Gauge, LayoutGrid, MessagesSquare, Package, Server, Settings, Shield, Users, Wallet, type LucideIcon } from 'lucide-react'
import type { components } from '../shared/types/api'
import { t } from '../i18n'

type Compte = components['schemas']['Compte']

export type LienNavigation = {
  vers: string
  libelle: string
  icone: LucideIcon
  /** Seulement pour un compte qui remplit cette condition. */
  visible?: (compte: Compte | null) => boolean
  fin?: boolean
}

const connecte = (compte: Compte | null) => compte !== null
const admin = (compte: Compte | null) => compte?.est_admin === true

/** Destinations publiques, dans l'en-tête (bureau) et le menu (mobile). */
export const liensPrincipaux: LienNavigation[] = [
  { vers: '/store', libelle: t.navigation.store, icone: LayoutGrid },
  { vers: '/echange', libelle: t.navigation.echange, icone: MessagesSquare },
  { vers: '/devs', libelle: t.navigation.devs, icone: Users, fin: true },
  { vers: '/evenements', libelle: t.navigation.evenements, icone: CalendarDays },
]

/** Navigation de l'espace connecté (barre latérale). Une ligne s'ajoute quand l'écran existe. */
export const liensEspace: LienNavigation[] = [
  { vers: '/tableau', libelle: t.navigation.tableau, icone: Gauge, visible: connecte },
  { vers: '/publier', libelle: t.navigation.mesApplis, icone: Package, visible: connecte },
  { vers: '/copies', libelle: t.navigation.mesCopies, icone: Server, visible: connecte },
  { vers: '/paiements', libelle: t.navigation.paiements, icone: CreditCard, visible: connecte },
  { vers: '/revenus', libelle: t.navigation.revenus, icone: Wallet, visible: connecte },
  { vers: '/missions', libelle: t.navigation.missions, icone: Briefcase, visible: connecte },
  { vers: '/notifications', libelle: t.navigation.notifications, icone: Bell, visible: connecte },
  { vers: '/admin/finances', libelle: t.navigation.finances, icone: Shield, visible: admin },
]

/** Écrans à venir dans l'espace, gardés ici pour l'ordre de la navigation. */
export const liensEspaceAVenir = {
  parametres: { vers: '/parametres', libelle: t.navigation.parametres, icone: Settings },
  administration: { vers: '/admin', libelle: t.navigation.administration, icone: Shield },
}

export function liensVisibles(liens: LienNavigation[], compte: Compte | null): LienNavigation[] {
  return liens.filter((lien) => !lien.visible || lien.visible(compte))
}
