import { fr, type Textes } from './fr'

/** Textes de la langue courante (le français ; l'anglais reprendra la forme de Textes). */
export const t: Textes = fr

/** Remplace les {repères} d'un texte : formater(t.commun.page, { numero: 2 }). */
export function formater(texte: string, valeurs: Record<string, string | number>): string {
  return texte.replace(/\{(\w+)\}/g, (repere, cle: string) => (cle in valeurs ? String(valeurs[cle]) : repere))
}
