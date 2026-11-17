import { useQuery } from '@tanstack/react-query'
import axios from 'axios'
import type { components } from '../../shared/types/api'
import { cheminLaravel, clientLocal } from '../../shared/client'

export type CompteDemo = components['schemas']['CompteDemo']
export type ComptesDemoDisponibles = { comptes: CompteDemo[]; paiementSimule: boolean }

/**
 * Comptes de démonstration ouverts depuis le site (mode jury, ou développement). null quand la
 * plateforme ne les propose pas (404) : rien ne s'affiche alors.
 */
export async function lireComptesDemo(): Promise<ComptesDemoDisponibles | null> {
  try {
    const { data } = await clientLocal.get<{ data: CompteDemo[]; paiement_simule: boolean }>(cheminLaravel('/demo/comptes'))
    if (!data || !Array.isArray(data.data) || data.data.length === 0) return null
    return { comptes: data.data, paiementSimule: data.paiement_simule === true }
  } catch (erreur) {
    if (axios.isAxiosError(erreur) && erreur.response?.status === 404) return null
    throw erreur
  }
}

export function useComptesDemo() {
  return useQuery({ queryKey: ['comptes-demo'], queryFn: lireComptesDemo, staleTime: 5 * 60_000, retry: false })
}
