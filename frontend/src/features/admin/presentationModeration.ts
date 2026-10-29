import type { CibleSignalement, MotifSignalement, StatutSignalement } from './api'
import type { TonBadge } from '../../ui'

export const libellesMotifs: Record<MotifSignalement, string> = {
  spam: 'Spam',
  abus: 'Abus',
  hameconnage: 'Hameçonnage',
  minage: 'Minage',
  contenu_illegal: 'Contenu illégal',
  autre: 'Autre',
}

export const libellesCibles: Record<CibleSignalement, string> = {
  discussion: 'Discussion',
  message: 'Message',
  appli: 'Appli',
  copie: 'Copie',
  compte: 'Compte',
  evenement: 'Événement',
}

export const libellesStatuts: Record<StatutSignalement, string> = {
  ouvert: 'Ouvert',
  traite: 'Traité',
  rejete: 'Rejeté',
}

export const tonsStatuts: Record<StatutSignalement, TonBadge> = {
  ouvert: 'alerte',
  traite: 'succes',
  rejete: 'neutre',
}

/** Les motifs graves préviennent l'équipe aussitôt : ils sautent aux yeux dans la liste. */
export function estGrave(motif: MotifSignalement): boolean {
  return motif === 'hameconnage' || motif === 'minage' || motif === 'contenu_illegal'
}

/** Chemin du front vers la cible d'un signalement, pour l'examiner avant de décider. */
export function cheminCible(cible: CibleSignalement, cibleId: number): string | null {
  switch (cible) {
    case 'discussion':
      return `/echange/${cibleId}`
    case 'appli':
      return `/store/${cibleId}`
    case 'evenement':
      return `/evenements#${cibleId}`
    default:
      return null
  }
}
