import type { components } from '../../shared/types/api'
import { cheminApi, cheminLaravel, client, clientLocal } from '../../shared/client'
import { preparerCsrf } from '../../app/preparerCsrf'

type Page<T> = components['schemas']['Pagination'] & { data: T[] }

export type Appli = components['schemas']['App']
export type DevResume = components['schemas']['DevResume']
export type Evenement = components['schemas']['Evenement']
export type EvenementCreation = components['schemas']['EvenementCreation']
export type Mission = components['schemas']['Mission']
export type Deploiement = components['schemas']['Deploiement']
export type Categorie = components['schemas']['Categorie']
export type VerificationDepot = components['schemas']['VerificationDepot']

export const categories: { valeur: Categorie; libelle: string }[] = [
  { valeur: 'commerce', libelle: 'Commerce' },
  { valeur: 'gestion', libelle: 'Gestion' },
  { valeur: 'education', libelle: 'Éducation' },
  { valeur: 'sante', libelle: 'Santé' },
  { valeur: 'finance', libelle: 'Finance' },
  { valeur: 'association', libelle: 'Association' },
  { valeur: 'restauration', libelle: 'Restauration' },
  { valeur: 'autre', libelle: 'Autre' },
]

export function libelleCategorie(valeur: Categorie): string {
  return categories.find((item) => item.valeur === valeur)?.libelle ?? valeur
}

export function fcfa(valeur: number): string {
  return `${new Intl.NumberFormat('fr-FR').format(valeur)} F`
}

async function page<T>(promesse: Promise<{ data: Page<T> }>): Promise<Page<T>> {
  const { data } = await promesse
  if (!data || !Array.isArray(data.data)) throw new Error('Réponse vide')
  return data
}

export function listerApplis(params: Record<string, string | number | boolean | undefined>) {
  return page<Appli>(clientLocal.get(cheminLaravel('/apps'), { params }))
}

export async function lireAppli(id: number): Promise<Appli> {
  const { data } = await clientLocal.get<Appli>(cheminLaravel(`/apps/${id}`))
  if (!data || typeof data.id !== 'number') throw new Error('Réponse vide')
  return data
}

export function listerEvenements(pageNum = 1) {
  return page<Evenement>(clientLocal.get(cheminLaravel('/events'), { params: pageNum > 1 ? { page: pageNum } : undefined }))
}

export async function creerEvenement(corps: EvenementCreation): Promise<Evenement> {
  await preparerCsrf()
  const { data } = await clientLocal.post<Evenement>(cheminLaravel('/events'), corps)
  if (!data || typeof data.id !== 'number') throw new Error('Réponse vide')
  return data
}

export async function inscrire(id: number): Promise<void> {
  await preparerCsrf()
  await clientLocal.post(cheminLaravel(`/events/${id}/inscription`))
}

export async function desinscrire(id: number): Promise<void> {
  await preparerCsrf()
  await clientLocal.delete(cheminLaravel(`/events/${id}/inscription`))
}

export function listerMissions() {
  return page<Mission>(clientLocal.get(cheminLaravel('/missions')))
}

export function listerCopies() {
  return page<Deploiement>(clientLocal.get(cheminLaravel('/deployments'), { params: { type: 'client' } }))
}

export async function arreterCopie(id: number): Promise<void> {
  await preparerCsrf()
  await clientLocal.delete(cheminLaravel(`/deployments/${id}`))
}

export function listerDevs(params: Record<string, string | boolean | undefined>) {
  return page<DevResume>(client.get(cheminApi('/devs'), { params }))
}

export async function verifierDepot(depotUrl: string): Promise<VerificationDepot> {
  await preparerCsrf()
  const { data } = await clientLocal.post<VerificationDepot>(cheminLaravel('/apps/verification'), { depot_url: depotUrl })
  if (!data) throw new Error('Réponse vide')
  return data
}

export async function publierAppli(corps: Record<string, unknown>): Promise<Appli> {
  await preparerCsrf()
  const { data } = await clientLocal.post<Appli>(cheminLaravel('/apps'), corps)
  if (!data || typeof data.id !== 'number') throw new Error('Réponse vide')
  return data
}

export async function deployerAppli(id: number, variables: Record<string, string>): Promise<Deploiement> {
  await preparerCsrf()
  const { data } = await clientLocal.post<Deploiement>(cheminLaravel(`/apps/${id}/deployments`), { variables })
  if (!data || typeof data.id !== 'number') throw new Error('Réponse vide')
  return data
}
