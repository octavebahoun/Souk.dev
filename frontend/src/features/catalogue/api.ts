import type { components } from '../../shared/types/api'
import { cheminApi, cheminLaravel, client, clientLocal } from '../../shared/client'
import { preparerCsrf } from '../../app/preparerCsrf'

type Page<T> = components['schemas']['Pagination'] & { data: T[] }

export type Appli = components['schemas']['App']
export type DevResume = components['schemas']['DevResume']
export type Evenement = components['schemas']['Evenement']
export type EvenementCreation = components['schemas']['EvenementCreation']
export type Mission = components['schemas']['Mission']
export type MissionCreation = components['schemas']['MissionCreation']
export type Deploiement = components['schemas']['Deploiement']
export type Categorie = components['schemas']['Categorie']
export type Techno = components['schemas']['Techno']
export type Specialite = components['schemas']['Specialite']
export type VerificationDepot = components['schemas']['VerificationDepot']
export type Discussion = components['schemas']['Discussion']
export type Message = components['schemas']['Message']
export type AvisAppli = components['schemas']['AvisAppli']
export type AvisAppliSaisie = components['schemas']['AvisAppliSaisie']
export type PageAvis = Page<AvisAppli> & { peut_noter: boolean; mon_avis: AvisAppli | null }
export type VersionAppli = components['schemas']['VersionAppli']

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

export function listerAvisAppli(id: number, pageNum = 1) {
  return page<AvisAppli>(clientLocal.get(cheminLaravel(`/apps/${id}/avis`), { params: pageNum > 1 ? { page: pageNum } : undefined })) as Promise<PageAvis>
}

export async function noterAppli(id: number, saisie: AvisAppliSaisie, modification: boolean): Promise<AvisAppli> {
  await preparerCsrf()
  const { data } = modification
    ? await clientLocal.patch<AvisAppli>(cheminLaravel(`/apps/${id}/avis`), saisie)
    : await clientLocal.post<AvisAppli>(cheminLaravel(`/apps/${id}/avis`), saisie)
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

export function listerVersions(appId: number) {
  return page<VersionAppli>(clientLocal.get(cheminLaravel(`/apps/${appId}/versions`), { params: { per_page: 50 } }))
}

export async function publierVersion(appId: number, corps: { ref?: string; notes: string }): Promise<VersionAppli> {
  await preparerCsrf()
  const { data } = await clientLocal.post<VersionAppli>(cheminLaravel(`/apps/${appId}/versions`), corps)
  return data
}

export async function mettreAJourCopie(id: number, corps: { variables?: Record<string, string> } = {}): Promise<Deploiement> {
  await preparerCsrf()
  const { data } = await clientLocal.post<Deploiement>(cheminLaravel(`/deployments/${id}/mise-a-jour`), corps)
  return data
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

export async function publierAppli(corps: {
  nom: string
  description: string
  depot_url: string
  demo_url: string
  categorie: Categorie
  prix: number
  type_prix: 'mensuel' | 'unique'
  taille: 'petite' | 'moyenne' | 'grande'
  stack: Techno[]
  captures: File[]
}): Promise<Appli> {
  await preparerCsrf()
  const formulaire = new FormData()
  formulaire.append('nom', corps.nom)
  formulaire.append('description', corps.description)
  formulaire.append('depot_url', corps.depot_url)
  formulaire.append('demo_url', corps.demo_url)
  formulaire.append('categorie', corps.categorie)
  formulaire.append('prix', String(corps.prix))
  formulaire.append('type_prix', corps.type_prix)
  formulaire.append('taille', corps.taille)
  for (const techno of corps.stack) formulaire.append('stack[]', techno)
  for (const image of corps.captures) formulaire.append('captures[]', image)
  const { data } = await clientLocal.post<Appli>(cheminLaravel('/apps'), formulaire)
  if (!data || typeof data.id !== 'number') throw new Error('Réponse vide')
  return data
}

export function listerDiscussionsAppli(appId: number) {
  return page<Discussion>(clientLocal.get(cheminLaravel('/discussions'), { params: { app: appId } }))
}

export async function proposerMissionAppli(id: number, corps: MissionCreation): Promise<Mission> {
  await preparerCsrf()
  const { data } = await clientLocal.post<Mission>(cheminLaravel(`/apps/${id}/missions`), corps)
  if (!data || typeof data.id !== 'number') throw new Error('Réponse vide')
  return data
}

export async function proposerMissionDev(username: string, corps: MissionCreation): Promise<Mission> {
  await preparerCsrf()
  const { data } = await clientLocal.post<Mission>(cheminLaravel(`/devs/${username}/missions`), corps)
  if (!data || typeof data.id !== 'number') throw new Error('Réponse vide')
  return data
}

export function listerMessagesMission(id: number) {
  return page<Message>(clientLocal.get(cheminLaravel(`/missions/${id}/messages`)))
}

export async function ecrireMessageMission(id: number, texte: string): Promise<Message> {
  await preparerCsrf()
  const { data } = await clientLocal.post<Message>(cheminLaravel(`/missions/${id}/messages`), { texte })
  if (!data || typeof data.id !== 'number') throw new Error('Réponse vide')
  return data
}

export async function noterMission(id: number, note: number, commentaire: string): Promise<void> {
  await preparerCsrf()
  await clientLocal.post(cheminLaravel(`/missions/${id}/note`), { note, commentaire: commentaire || undefined })
}

export async function posterAvis(id: number, note: number, commentaire: string): Promise<void> {
  await preparerCsrf()
  await clientLocal.post(cheminLaravel(`/events/${id}/avis`), { note, commentaire: commentaire || undefined })
}

export async function deployerAppli(id: number, variables: Record<string, string>): Promise<Deploiement> {
  await preparerCsrf()
  const { data } = await clientLocal.post<Deploiement>(cheminLaravel(`/apps/${id}/deployments`), { variables })
  if (!data || typeof data.id !== 'number') throw new Error('Réponse vide')
  return data
}
