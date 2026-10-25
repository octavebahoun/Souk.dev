import { useEffect, useSyncExternalStore } from 'react'

export type ChoixTheme = 'clair' | 'sombre' | 'systeme'

const CLE = 'souk-theme'
const EVENEMENT = 'souk-theme'
let memoire: ChoixTheme = 'systeme'

function lire(): ChoixTheme {
  try {
    const valeur = localStorage.getItem(CLE)
    return valeur === 'clair' || valeur === 'sombre' ? valeur : 'systeme'
  } catch {
    return memoire
  }
}

/**
 * Applique un choix sur <html data-theme>. Un écran pas encore refait force le thème clair
 * (data-theme-force) : ses anciennes couleurs ne tiennent pas en sombre.
 */
function appliquer(choix: ChoixTheme): void {
  const racine = document.documentElement
  if (racine.dataset.themeForce === 'clair') {
    racine.dataset.theme = 'light'
    return
  }
  const sombre = choix === 'sombre' || (choix === 'systeme' && window.matchMedia?.('(prefers-color-scheme: dark)').matches)
  racine.dataset.theme = sombre ? 'dark' : 'light'
}

/** Force le thème clair tant que l'écran est affiché ; renvoie de quoi rendre le thème choisi. */
export function forcerThemeClair(): () => void {
  const racine = document.documentElement
  racine.dataset.themeForce = 'clair'
  appliquer(lire())
  return () => {
    delete racine.dataset.themeForce
    appliquer(lire())
  }
}

function abonner(rappel: () => void): () => void {
  window.addEventListener(EVENEMENT, rappel)
  window.addEventListener('storage', rappel)
  return () => {
    window.removeEventListener(EVENEMENT, rappel)
    window.removeEventListener('storage', rappel)
  }
}

/** Thème choisi (gardé dans le navigateur, partagé entre tous les sélecteurs de la page). */
export function useTheme(): [ChoixTheme, (choix: ChoixTheme) => void] {
  const choix = useSyncExternalStore(abonner, lire, () => 'systeme' as ChoixTheme)

  useEffect(() => {
    appliquer(choix)
    if (choix !== 'systeme' || !window.matchMedia) return
    const requete = window.matchMedia('(prefers-color-scheme: dark)')
    const suivre = () => appliquer('systeme')
    requete.addEventListener('change', suivre)
    return () => requete.removeEventListener('change', suivre)
  }, [choix])

  function changer(nouveau: ChoixTheme) {
    memoire = nouveau
    try {
      localStorage.setItem(CLE, nouveau)
    } catch {
      // Navigation privée : le choix vaut pour cette visite seulement.
    }
    window.dispatchEvent(new Event(EVENEMENT))
  }

  return [choix, changer]
}
