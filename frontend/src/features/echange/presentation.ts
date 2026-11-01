import type { TonBadge } from '../../ui'
import type { Difficulte, Etiquette } from './api'

/** Teinte de chaque étiquette d'une discussion (badges du fil et de la page). */
export const tonEtiquette: Record<Etiquette, TonBadge> = {
  bug: 'danger',
  question: 'info',
  tuto: 'succes',
  projet: 'ia',
  entraide: 'neutre',
}

export const libelleDifficulte: Record<Difficulte, string> = { facile: 'Facile', moyen: 'Moyen', difficile: 'Difficile' }

export const tonDifficulte: Record<Difficulte, TonBadge> = { facile: 'succes', moyen: 'alerte', difficile: 'danger' }
