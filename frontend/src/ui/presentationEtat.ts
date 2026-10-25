import { t } from '../i18n'
import type { components } from '../shared/types/api'
import type { TonBadge } from './Badge'

type EtatCopie = components['schemas']['Deploiement']['etat']

export type PresentationEtat = { ton: TonBadge; libelle: string; enCours: boolean }

const tonsCopie: Record<string, { ton: TonBadge; enCours: boolean }> = {
  attente_paiement: { ton: 'alerte', enCours: false },
  en_file: { ton: 'info', enCours: true },
  construction: { ton: 'info', enCours: true },
  demarrage: { ton: 'info', enCours: true },
  en_ligne: { ton: 'succes', enCours: false },
  echec: { ton: 'danger', enCours: false },
  arret_en_cours: { ton: 'neutre', enCours: true },
  arrete: { ton: 'neutre', enCours: false },
  suspendu: { ton: 'danger', enCours: false },
}

/** Couleur et libellé d'un état de copie (docs/design.md, § 8). */
export function presentationEtatCopie(etat: EtatCopie | 'attente_paiement' | 'suspendu'): PresentationEtat {
  const ton = tonsCopie[etat] ?? { ton: 'neutre' as const, enCours: false }
  const libelles = t.etatsCopie as Record<string, string>
  return { ...ton, libelle: libelles[etat] ?? etat }
}
