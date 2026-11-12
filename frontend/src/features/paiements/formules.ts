import type { components } from '../../shared/types/api'

type Taille = components['schemas']['Taille']

/**
 * Formules d'hébergement (prix décidés dans le brief, section 6 ; le serveur les tient dans
 * config/facturation.php et en tire chaque facture : le montant payé vient toujours de lui).
 */
export const formules: Record<Taille, { nom: string; prix: number; memoire: string; coeurs: string }> = {
  petite: { nom: 'Petite', prix: 3500, memoire: '512 Mo', coeurs: '0,5 cœur' },
  moyenne: { nom: 'Moyenne', prix: 7000, memoire: '1 Go', coeurs: '1 cœur' },
  grande: { nom: 'Grande', prix: 13500, memoire: '2 Go', coeurs: '2 cœurs' },
}

/**
 * Frais du moyen de paiement, ajoutés par SasPay au moment de payer (environ 3,5 %, arrondis au
 * franc supérieur : 123 F pour 3 500 F). Estimation affichée avant le clic ; le montant exact est
 * celui que SasPay montre avant de valider.
 */
export const TAUX_FRAIS_PAIEMENT = 0.035

export function fraisPaiementEstimes(montant: number): number {
  return Math.ceil(montant * TAUX_FRAIS_PAIEMENT)
}
