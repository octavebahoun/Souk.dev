import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router'
import { lireMoi, type Compte } from '../features/echange/api'
import { clientLocal } from '../shared/client'
import { preparerCsrf } from './preparerCsrf'

/** Compte connecté (null pour un visiteur). Même clé que les écrans existants : ['moi']. */
export function useCompte() {
  return useQuery({ queryKey: ['moi'], queryFn: lireMoi, staleTime: 60_000 })
}

/**
 * Action réservée aux comptes : attend le compte s'il se charge encore, puis lance l'action, ou
 * la fenêtre « Connectez-vous » pour un visiteur. Un clic juste après l'arrivée sur la page ne
 * prend plus un membre pour un visiteur.
 */
export function useSelonCompte() {
  const cache = useQueryClient()
  return async function selonCompte(siConnecte: () => void, siVisiteur: () => void): Promise<void> {
    let compte = cache.getQueryData<Compte | null>(['moi'])
    if (compte === undefined) {
      try {
        compte = await cache.ensureQueryData({ queryKey: ['moi'], queryFn: lireMoi })
      } catch {
        compte = null
      }
    }
    if (compte) siConnecte()
    else siVisiteur()
  }
}

/** Ferme la session puis revient à l'accueil. */
export function useDeconnexion() {
  const cache = useQueryClient()
  const naviguer = useNavigate()
  return async function deconnecter() {
    await preparerCsrf()
    await clientLocal.post('/logout').catch(() => undefined)
    cache.clear()
    await cache.invalidateQueries({ queryKey: ['moi'] })
    void naviguer('/')
  }
}
