import type { Difficulte, Discussion, Etiquette } from './api'

export type FiltresFil = {
  canal?: string
  etiquette?: Etiquette
  statut?: 'ouvertes' | 'resolues'
  difficulte?: Difficulte
  page: number
}

const etiquettes: Etiquette[] = ['bug', 'question', 'tuto', 'projet', 'entraide']
const difficultes: Difficulte[] = ['facile', 'moyen', 'difficile']

function estEtiquette(valeur: string | null): valeur is Etiquette {
  return etiquettes.includes(valeur as Etiquette)
}

function estDifficulte(valeur: string | null): valeur is Difficulte {
  return difficultes.includes(valeur as Difficulte)
}

export function lireFiltres(params: URLSearchParams): FiltresFil {
  const etiquette = params.get('etiquette')
  const statut = params.get('statut')
  const difficulte = params.get('difficulte')
  const page = Number(params.get('page'))

  return {
    canal: params.get('canal') || undefined,
    etiquette: estEtiquette(etiquette) ? etiquette : undefined,
    statut: statut === 'ouvertes' || statut === 'resolues' ? statut : undefined,
    difficulte: estDifficulte(difficulte) ? difficulte : undefined,
    page: Number.isInteger(page) && page > 1 ? page : 1,
  }
}

export function ecrireFiltres(filtres: FiltresFil): Record<string, string> {
  const query: Record<string, string> = {}
  if (filtres.canal) query.canal = filtres.canal
  if (filtres.etiquette) query.etiquette = filtres.etiquette
  if (filtres.statut) query.statut = filtres.statut
  if (filtres.difficulte) query.difficulte = filtres.difficulte
  if (filtres.page > 1) query.page = String(filtres.page)
  return query
}

export function filtresActifs(filtres: FiltresFil): boolean {
  return Boolean(filtres.canal || filtres.etiquette || filtres.statut || filtres.difficulte)
}

export function correspond(discussion: Discussion, filtres: FiltresFil): boolean {
  if (filtres.canal && discussion.canal !== filtres.canal) return false
  if (filtres.etiquette && !discussion.etiquettes.includes(filtres.etiquette)) return false
  if (filtres.statut === 'ouvertes' && discussion.resolue) return false
  if (filtres.statut === 'resolues' && !discussion.resolue) return false
  if (filtres.difficulte && discussion.bug?.difficulte !== filtres.difficulte) return false
  return true
}
