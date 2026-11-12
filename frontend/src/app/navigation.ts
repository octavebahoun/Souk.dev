import { Bell, Briefcase, Mail, CalendarDays, CreditCard, Flag, Gauge, HardDrive, LayoutGrid, MessagesSquare, Package, Server, Shield, UserRound, Users, Wallet, type LucideIcon } from 'lucide-react'
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
/** Publier et encaisser concernent les devs ; un client ne voit pas ces entrées vides. */
const dev = (compte: Compte | null) => compte !== null && compte.profil !== 'client'

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
  { vers: '/profil', libelle: t.navigation.monProfil, icone: UserRound, visible: connecte },
  { vers: '/apps', libelle: t.navigation.mesApplis, icone: Package, visible: dev },
  { vers: '/copies', libelle: t.navigation.mesCopies, icone: Server, visible: connecte },
  { vers: '/paiements', libelle: t.navigation.paiements, icone: CreditCard, visible: connecte },
  { vers: '/revenus', libelle: t.navigation.revenus, icone: Wallet, visible: dev },
  { vers: '/messages', libelle: t.navigation.messages, icone: Mail, visible: connecte },
  { vers: '/missions', libelle: t.navigation.missions, icone: Briefcase, visible: connecte },
  { vers: '/notifications', libelle: t.navigation.notifications, icone: Bell, visible: connecte },
  { vers: '/admin/finances', libelle: t.navigation.finances, icone: Shield, visible: admin },
  { vers: '/admin/serveurs', libelle: t.navigation.serveurs, icone: HardDrive, visible: admin },
  { vers: '/admin/moderation', libelle: t.navigation.moderation, icone: Flag, visible: admin },
]

export function liensVisibles(liens: LienNavigation[], compte: Compte | null): LienNavigation[] {
  return liens.filter((lien) => !lien.visible || lien.visible(compte))
}
