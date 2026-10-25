/** Illustrations des états (Nano Banana Pro, voir scripts/visuels/ et AI_USAGE.md), en deux thèmes. */
const claires = import.meta.glob<string>('../assets/illustrations/*.webp', { eager: true, import: 'default', query: '?url' })
const sombres = import.meta.glob<string>('../assets/illustrations/sombre/*.webp', { eager: true, import: 'default', query: '?url' })

export type NomIllustration =
  | 'vide-discussions'
  | 'vide-store'
  | 'vide-copies'
  | 'vide-paiements'
  | 'vide-revenus'
  | 'vide-notifications'
  | 'vide-missions'
  | 'vide-evenements'
  | 'vide-recherche'
  | 'erreur-404'
  | 'hors-ligne'
  | 'acces-refuse'

export function urlIllustration(nom: NomIllustration, theme: 'clair' | 'sombre' = 'clair'): string | undefined {
  return theme === 'sombre'
    ? sombres[`../assets/illustrations/sombre/${nom}-sombre.webp`]
    : claires[`../assets/illustrations/${nom}.webp`]
}
