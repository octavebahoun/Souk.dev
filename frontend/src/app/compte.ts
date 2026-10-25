import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router'
import { lireMoi } from '../features/echange/api'
import { clientLocal } from '../shared/client'
import { preparerCsrf } from './preparerCsrf'

/** Compte connecté (null pour un visiteur). Même clé que les écrans existants : ['moi']. */
export function useCompte() {
  return useQuery({ queryKey: ['moi'], queryFn: lireMoi, staleTime: 60_000 })
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
