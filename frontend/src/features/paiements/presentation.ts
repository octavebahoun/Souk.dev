import type { TonBadge } from '../../ui'
import { formaterDate, joursAvant } from '../../ui'
import type { Facture, Mouvement, Paiement, Reversement } from './api'

export type Presentation = { ton: TonBadge; libelle: string; enCours?: boolean }

/**
 * État d'une facture vu par le client : une facture à payer devient « en retard » le lendemain
 * de l'échéance (même table de couleurs que tout le produit, docs/design.md § 8).
 */
export function presentationFacture(facture: Pick<Facture, 'statut' | 'echeance_le'>): Presentation {
  if (facture.statut === 'payee') return { ton: 'succes', libelle: 'Payée' }
  if (facture.statut === 'annulee') return { ton: 'neutre', libelle: 'Annulée' }
  if (facture.statut === 'remboursee') return { ton: 'neutre', libelle: 'Remboursée' }
  const jours = joursAvant(facture.echeance_le)
  if (jours < 0) return { ton: 'danger', libelle: 'En retard' }
  return { ton: 'alerte', libelle: jours === 0 ? 'À payer aujourd’hui' : 'À payer' }
}

/** Phrase d'échéance : « Échéance le 5 novembre », « En retard depuis 3 jours ». */
export function phraseEcheance(facture: Pick<Facture, 'statut' | 'echeance_le' | 'payee_le'>): string {
  if (facture.statut === 'payee' && facture.payee_le) return `Payée le ${formaterDate(facture.payee_le)}`
  if (facture.statut !== 'a_payer') return `Échéance du ${formaterDate(facture.echeance_le)}`
  const jours = joursAvant(facture.echeance_le)
  if (jours < 0) return `En retard depuis ${-jours} jour${-jours > 1 ? 's' : ''}`
  if (jours === 0) return 'À payer aujourd’hui'
  return `Échéance le ${formaterDate(facture.echeance_le)}`
}

export function presentationPaiement(paiement: Pick<Paiement, 'statut'>): Presentation {
  switch (paiement.statut) {
    case 'reussi':
      return { ton: 'succes', libelle: 'Réussi' }
    case 'echoue':
      return { ton: 'danger', libelle: 'Refusé' }
    case 'annule':
      return { ton: 'neutre', libelle: 'Abandonné' }
    default:
      return { ton: 'info', libelle: 'En attente', enCours: true }
  }
}

export function presentationReversement(reversement: Pick<Reversement, 'statut'>): Presentation {
  switch (reversement.statut) {
    case 'reussi':
      return { ton: 'succes', libelle: 'Versé' }
    case 'echoue':
      return { ton: 'danger', libelle: 'Refusé par le réseau' }
    default:
      return { ton: 'info', libelle: 'En cours', enCours: true }
  }
}

export const libellesMouvement: Record<Mouvement['type'], string> = {
  encaissement: 'Vente',
  reversement: 'Reversement',
  reversement_annule: 'Reversement annulé',
  avoir: 'Remboursement client',
}
