import { useEffect, useState } from 'react'

export type ChoixTheme = 'clair' | 'sombre' | 'systeme'

const CLE = 'souk-theme'

function lire(): ChoixTheme {
  try {
    const valeur = localStorage.getItem(CLE)
    return valeur === 'clair' || valeur === 'sombre' ? valeur : 'systeme'
  } catch {
    return 'systeme'
  }
}

function appliquer(choix: ChoixTheme): void {
  const sombre = choix === 'sombre' || (choix === 'systeme' && window.matchMedia?.('(prefers-color-scheme: dark)').matches)
  document.documentElement.dataset.theme = sombre ? 'dark' : 'light'
}

/** Thème choisi (gardé dans le navigateur) ; « système » suit le réglage de l'appareil. */
export function useTheme(): [ChoixTheme, (choix: ChoixTheme) => void] {
  const [choix, setChoix] = useState<ChoixTheme>(lire)

  useEffect(() => {
    appliquer(choix)
    if (choix !== 'systeme' || !window.matchMedia) return
    const requete = window.matchMedia('(prefers-color-scheme: dark)')
    const suivre = () => appliquer('systeme')
    requete.addEventListener('change', suivre)
    return () => requete.removeEventListener('change', suivre)
  }, [choix])

  function changer(nouveau: ChoixTheme) {
    try {
      localStorage.setItem(CLE, nouveau)
    } catch {
      // Navigation privée : le choix vaut pour cette visite seulement.
    }
    setChoix(nouveau)
  }

  return [choix, changer]
}
