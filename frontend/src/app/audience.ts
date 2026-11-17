import { useEffect } from 'react'
import { useLocation } from 'react-router'
import { cheminLaravel, urlApi } from '../shared/client'

const CLE_ARRIVEE = 'souk-audience-arrivee'

/** Dernière page signalée : un second rendu (mode strict de React, retour rapide) ne la recompte pas. */
let derniere: { chemin: string; instant: number } | null = null

/** Vrai pour la première page d'une visite (onglet) : c'est elle qui porte le site d'origine. */
function premierePageDeLaVisite(): boolean {
  try {
    const premiere = sessionStorage.getItem(CLE_ARRIVEE) === null
    sessionStorage.setItem(CLE_ARRIVEE, '1')
    return premiere
  } catch {
    return false
  }
}

/**
 * Mesure d'audience sans cookie : chaque page vue part en une petite requête (chemin, site
 * d'arrivée à la première page, fuseau horaire et largeur d'écran). Rien qui identifie la
 * personne ; le serveur ne garde pas son adresse IP. Désactivée dans les tests.
 */
export function useMesureAudience(): void {
  const { pathname } = useLocation()

  useEffect(() => {
    if (import.meta.env.MODE === 'test' || typeof fetch !== 'function') return
    if (derniere && derniere.chemin === pathname && Date.now() - derniere.instant < 2000) return
    derniere = { chemin: pathname, instant: Date.now() }

    const premiere = premierePageDeLaVisite()

    const parametres = new URLSearchParams(window.location.search)
    const corps = {
      chemin: pathname,
      referent: premiere ? document.referrer || null : null,
      source: premiere ? parametres.get('utm_source') : null,
      fuseau: Intl.DateTimeFormat().resolvedOptions().timeZone ?? null,
      langue: navigator.language ?? null,
      largeur: window.innerWidth,
    }

    // text/plain : requête simple, sans prévol CORS ; keepalive : elle part même si la page se ferme.
    void fetch(urlApi(cheminLaravel('/visites')), {
      method: 'POST',
      body: JSON.stringify(corps),
      headers: { 'Content-Type': 'text/plain;charset=UTF-8' },
      credentials: 'include',
      keepalive: true,
    }).catch(() => undefined)
  }, [pathname])
}
