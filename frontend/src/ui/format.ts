/** Mise en forme partagée par les composants (initiales, montants FCFA). */

export function initiales(nom: string | null | undefined, secours?: string | null): string {
  const source = (nom || secours || '?').trim()
  const mots = source.split(/[\s._-]+/).filter(Boolean)
  const lettres = mots.length >= 2 ? `${mots[0][0]}${mots[1][0]}` : source.slice(0, 2)
  return lettres.toUpperCase()
}

const format = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 })

/** 15000 → « 15 000 F » (entiers FCFA, jamais de flottant). */
export function formaterFcfa(valeur: number): string {
  return `${format.format(Math.round(valeur))} F`
}
