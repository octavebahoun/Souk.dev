import axios from 'axios'
import type { components, operations } from '../../shared/types/api'
import { cheminApi, cheminLaravel, client, clientLocal } from '../../shared/client'
import type { FiltresFil } from './filtres'

export type Discussion = components['schemas']['Discussion']
export type Canal = components['schemas']['Canal']
export type Etiquette = components['schemas']['Etiquette']
export type Difficulte = components['schemas']['Difficulte']
export type Techno = components['schemas']['Techno']
export type DiscussionCreation = components['schemas']['DiscussionCreation']
export type DiscussionModification = components['schemas']['DiscussionModification']
export type Message = components['schemas']['Message']
export type MessageCreation = components['schemas']['MessageCreation']
export type Correctif = components['schemas']['Correctif']
export type CorrectifCreation = components['schemas']['CorrectifCreation']
export type Deploiement = components['schemas']['Deploiement']
export type AnalyseIa = components['schemas']['AnalyseIa']
export type Compte = components['schemas']['Compte']
export type ErreurValidation = components['schemas']['ErreurValidation']
export type Erreur = components['schemas']['Erreur']

type ListeDiscussions = operations['listDiscussions']['responses'][200]['content']['application/json']
type ListeMessages = operations['listMessages']['responses'][200]['content']['application/json']
type ListeCorrectifs = operations['listCorrectifs']['responses'][200]['content']['application/json']

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
  const { data } = await clientLocal.get<ListeDiscussions>(cheminLaravel('/discussions'), {
    params: paramsDiscussions(filtres),
  })
  if (!data || !Array.isArray(data.data)) {
    throw new Error('Réponse vide')
  }
  return data
}

export async function listerCanaux(): Promise<Canal[]> {
  const { data } = await clientLocal.get<Canal[]>(cheminLaravel('/canaux'))
  if (!Array.isArray(data)) throw new Error('Réponse vide')
  return data
}

export async function listerEtiquettes(): Promise<Etiquette[]> {
  const { data } = await clientLocal.get<Etiquette[]>(cheminLaravel('/etiquettes'))
  if (!Array.isArray(data)) throw new Error('Réponse vide')
  return data
}

export async function creerDiscussion(corps: DiscussionCreation): Promise<Discussion> {
  const { data } = await clientLocal.post<Discussion>(cheminLaravel('/discussions'), corps)
  return data
}

export function estIntrouvable(erreur: unknown): boolean {
  return axios.isAxiosError(erreur) && erreur.response?.status === 404
}

export function messageAction(erreur: unknown, trop: string): string {
  const lu = lireErreurHttp(erreur, 'L’action a échoué.')
  if (lu.statut === 429) return trop
  if (lu.statut === 401) return 'Connectez-vous pour continuer.'
  if (lu.statut === 419) return 'La session a expiré. Rechargez la page.'
  if (lu.statut !== null && lu.statut >= 500) return 'Le serveur a rencontré un problème. Réessayez dans quelques instants.'
  if (axios.isAxiosError(erreur) && erreur.request && !erreur.response) {
    return 'Le serveur local ne répond pas. Démarrez Laravel sur le port 8000, puis réessayez.'
  }
  return lu.message
}

export async function lireDiscussion(id: number): Promise<Discussion> {
  const { data } = await clientLocal.get<Discussion>(cheminLaravel(`/discussions/${id}`))
  if (!data || typeof data.id !== 'number') throw new Error('Réponse vide')
  return data
}

export async function modifierDiscussion(id: number, corps: DiscussionModification): Promise<Discussion> {
  const { data } = await clientLocal.patch<Discussion>(cheminLaravel(`/discussions/${id}`), corps)
  if (!data || typeof data.id !== 'number') throw new Error('Réponse vide')
  return data
}

export async function listerMessages(id: number, page: number): Promise<ListeMessages> {
  const { data } = await clientLocal.get<ListeMessages>(cheminLaravel(`/discussions/${id}/messages`), {
    params: page > 1 ? { page } : undefined,
  })
  if (!data || !Array.isArray(data.data)) throw new Error('Réponse vide')
  return data
}

export async function creerMessage(id: number, corps: MessageCreation): Promise<Message> {
  const { data } = await clientLocal.post<Message>(cheminLaravel(`/discussions/${id}/messages`), corps)
  if (!data || typeof data.id !== 'number') throw new Error('Réponse vide')
  return data
}

export async function lancerCopieTest(id: number): Promise<Deploiement> {
  const { data } = await client.post<Deploiement>(cheminApi(`/discussions/${id}/copie-test`))
  if (!data || typeof data.id !== 'number') throw new Error('Réponse vide')
  return data
}

export async function arreterDeploiement(id: number): Promise<void> {
  await clientLocal.delete(cheminLaravel(`/deployments/${id}`))
}

export async function demanderAnalyse(id: number): Promise<AnalyseIa> {
  const { data } = await client.post<AnalyseIa>(cheminApi(`/discussions/${id}/analyse-ia`))
  if (!data || typeof data.statut !== 'string') throw new Error('Réponse vide')
  return data
}

export async function listerCorrectifs(id: number, page: number): Promise<ListeCorrectifs> {
  const { data } = await clientLocal.get<ListeCorrectifs>(cheminLaravel(`/discussions/${id}/correctifs`), {
    params: page > 1 ? { page } : undefined,
  })
  if (!data || !Array.isArray(data.data)) throw new Error('Réponse vide')
  return data
}

export async function creerCorrectif(id: number, corps: CorrectifCreation): Promise<Correctif> {
  const { data } = await clientLocal.post<Correctif>(cheminLaravel(`/discussions/${id}/correctifs`), corps)
  if (!data || typeof data.id !== 'number') throw new Error('Réponse vide')
  return data
}

export async function deployerCorrectif(id: number): Promise<Deploiement> {
  const { data } = await client.post<Deploiement>(cheminApi(`/correctifs/${id}/deploiement`))
  if (!data || typeof data.id !== 'number') throw new Error('Réponse vide')
  return data
}

export async function accepterCorrectif(id: number): Promise<Correctif> {
  const { data } = await clientLocal.post<Correctif>(cheminLaravel(`/correctifs/${id}/accepter`))
  if (!data || typeof data.id !== 'number') throw new Error('Réponse vide')
  return data
}

export async function lireMoi(): Promise<Compte | null> {
  try {
    const { data } = await clientLocal.get<Compte>(cheminLaravel('/me'))
    if (!data || typeof data.id !== 'number') return null
    return data
  } catch (erreur) {
    if (axios.isAxiosError(erreur) && erreur.response?.status === 401) return null
    throw erreur
  }
}

export function lireErreurHttp(erreur: unknown, secours = 'La publication a échoué.'): ErreurHttp {
  if (!axios.isAxiosError(erreur)) {
    return { statut: null, message: secours, champs: {} }
  }

  const statut = erreur.response?.status ?? null
  const donnees: unknown = erreur.response?.data
  const corps = typeof donnees === 'object' && donnees !== null && !Array.isArray(donnees)
    ? donnees as Erreur | ErreurValidation
    : undefined
  const message = statut !== null && statut >= 500
    ? 'Le serveur a rencontré un problème. Réessayez dans quelques instants.'
    : corps && typeof corps.message === 'string' && corps.message
      ? corps.message
      : secours
  const champs: Record<string, string> = {}

  if (corps && 'errors' in corps && typeof corps.errors === 'object' && corps.errors !== null) {
    for (const [cle, liste] of Object.entries(corps.errors)) {
      const premier = Array.isArray(liste) ? liste.find((item): item is string => typeof item === 'string') : undefined
      if (premier) champs[cle] = premier
    }
  }

  return { statut, message, champs }
}
