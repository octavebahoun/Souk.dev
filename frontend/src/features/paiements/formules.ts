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
