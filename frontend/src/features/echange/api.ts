import axios from 'axios'
import type { components, operations } from '../../shared/types/api'
import { cheminApi, client } from '../../shared/client'
import type { FiltresFil } from './filtres'

export type Discussion = components['schemas']['Discussion']
export type Canal = components['schemas']['Canal']
export type Etiquette = components['schemas']['Etiquette']
export type Difficulte = components['schemas']['Difficulte']
export type Techno = components['schemas']['Techno']
export type DiscussionCreation = components['schemas']['DiscussionCreation']
export type ErreurValidation = components['schemas']['ErreurValidation']
export type Erreur = components['schemas']['Erreur']

type ListeDiscussions = operations['listDiscussions']['responses'][200]['content']['application/json']

export type ErreurHttp = {
  statut: number | null
  message: string
  champs: Record<string, string>
}

export function paramsDiscussions(filtres: FiltresFil): operations['listDiscussions']['parameters']['query'] {
  return {
    canal: filtres.canal,
    etiquette: filtres.etiquette,
    statut: filtres.statut,
    difficulte: filtres.difficulte,
    page: filtres.page > 1 ? filtres.page : undefined,
  }
}

export async function listerDiscussions(filtres: FiltresFil): Promise<ListeDiscussions> {
  const { data } = await client.get<ListeDiscussions>(cheminApi('/discussions'), {
    params: paramsDiscussions(filtres),
  })
  if (!data || !Array.isArray(data.data)) {
    throw new Error('Réponse vide')
  }
  return data
}

export async function listerCanaux(): Promise<Canal[]> {
  const { data } = await client.get<Canal[]>(cheminApi('/canaux'))
  if (!Array.isArray(data)) throw new Error('Réponse vide')
  return data
}

export async function listerEtiquettes(): Promise<Etiquette[]> {
  const { data } = await client.get<Etiquette[]>(cheminApi('/etiquettes'))
  if (!Array.isArray(data)) throw new Error('Réponse vide')
  return data
}

export async function creerDiscussion(corps: DiscussionCreation): Promise<Discussion> {
  const { data } = await client.post<Discussion>(cheminApi('/discussions'), corps)
  return data
}

export function lireErreurHttp(erreur: unknown): ErreurHttp {
  if (!axios.isAxiosError(erreur)) {
    return { statut: null, message: 'La publication a échoué.', champs: {} }
  }

  const statut = erreur.response?.status ?? null
  const corps = erreur.response?.data as Erreur | ErreurValidation | undefined
  const message =
    corps && typeof corps.message === 'string' ? corps.message : 'La publication a échoué.'
  const champs: Record<string, string> = {}

  if (corps && 'errors' in corps && corps.errors) {
    for (const [cle, liste] of Object.entries(corps.errors)) {
      const premier = liste[0]
      if (premier) champs[cle] = premier
    }
  }

  return { statut, message, champs }
}
