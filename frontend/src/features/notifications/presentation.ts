import { BadgeCheck, CirclePause, MessageSquare, Receipt, RefreshCw, Wallet, Wrench, type LucideIcon } from 'lucide-react'
import type { TypeNotification } from './api'

/** Icône et couleur de chaque type, dans la cloche, la page et les réglages. */
export const presentationNotification: Record<TypeNotification, { icone: LucideIcon; classe: string }> = {
  correctif_propose: { icone: Wrench, classe: 'bg-fond-info text-info' },
  correctif_accepte: { icone: BadgeCheck, classe: 'bg-fond-succes text-succes' },
  message_mission: { icone: MessageSquare, classe: 'bg-fond-ia text-ia' },
  echeance: { icone: Receipt, classe: 'bg-fond-alerte text-alerte' },
  suspension: { icone: CirclePause, classe: 'bg-fond-danger text-danger' },
  mise_a_jour: { icone: RefreshCw, classe: 'bg-fond-info text-info' },
  reversement: { icone: Wallet, classe: 'bg-fond-succes text-succes' },
}
