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

const jours = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
const joursCourts = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short' })
const heures = new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })

/**
 * « 2026-11-05 » (date seule) se lit comme un jour du calendrier, sans décalage de fuseau ;
 * une date et heure ISO garde son instant.
 */
function lireDate(valeur: string): Date {
  const jour = /^(\d{4})-(\d{2})-(\d{2})$/.exec(valeur)
  return jour ? new Date(Number(jour[1]), Number(jour[2]) - 1, Number(jour[3])) : new Date(valeur)
}

/** « 5 novembre 2026 » ; court : « 5 nov. » ; avecHeure : « 5 novembre 2026 à 14:30 ». */
export function formaterDate(valeur: string, style: 'long' | 'court' | 'avecHeure' = 'long'): string {
  const date = lireDate(valeur)
  if (Number.isNaN(date.getTime())) return valeur
  const texte = style === 'court' ? joursCourts.format(date) : style === 'avecHeure' ? heures.format(date) : jours.format(date)
  // Le premier du mois s'écrit « 1er » en français.
  return date.getDate() === 1 ? texte.replace(/^1 /, '1er ') : texte
}

/** « du 5 novembre au 4 décembre 2026 ». */
export function formaterPeriode(debut: string, fin: string): string {
  const a = lireDate(debut)
  const b = lireDate(fin)
  const memeAnnee = a.getFullYear() === b.getFullYear()
  const premier = new Intl.DateTimeFormat('fr-FR', memeAnnee ? { day: 'numeric', month: 'long' } : { day: 'numeric', month: 'long', year: 'numeric' }).format(a)
  const dernier = jours.format(b)
  return `du ${a.getDate() === 1 ? premier.replace(/^1 /, '1er ') : premier} au ${b.getDate() === 1 ? dernier.replace(/^1 /, '1er ') : dernier}`
}

/** Jours entiers entre aujourd'hui et une date (négatif : dans le passé). */
export function joursAvant(valeur: string): number {
  const cible = lireDate(valeur)
  const aujourdhui = new Date()
  const debut = new Date(aujourdhui.getFullYear(), aujourdhui.getMonth(), aujourdhui.getDate())
  return Math.round((new Date(cible.getFullYear(), cible.getMonth(), cible.getDate()).getTime() - debut.getTime()) / 86_400_000)
}

/** « 4,5 » : une note sur 5, une décimale au plus, virgule française. */
export function formaterNote(note: number): string {
  return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(note)
}

const relatif = new Intl.RelativeTimeFormat('fr', { numeric: 'auto' })

/** « à l’instant », « il y a 5 minutes », « hier », puis la date courte au-delà d'une semaine. */
export function formaterIlYa(valeur: string, maintenant: number = Date.now()): string {
  const secondes = Math.max(0, Math.round((maintenant - new Date(valeur).getTime()) / 1000))
  if (Number.isNaN(secondes)) return valeur
  if (secondes < 60) return 'à l’instant'
  if (secondes < 3600) return relatif.format(-Math.floor(secondes / 60), 'minute')
  if (secondes < 86400) return relatif.format(-Math.floor(secondes / 3600), 'hour')
  if (secondes < 7 * 86400) return relatif.format(-Math.floor(secondes / 86400), 'day')
  return formaterDate(valeur, 'court')
}
