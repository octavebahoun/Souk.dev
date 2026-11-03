import type { components } from '../../shared/types/api'
import { cheminLaravel, clientLocal } from '../../shared/client'
import { preparerCsrf } from '../../app/preparerCsrf'

type Schemas = components['schemas']
export type Machine = Schemas['Machine']
export type EtatMachine = Schemas['EtatMachine']
export type MachineCreation = Schemas['MachineCreation']
export type MachineModification = Schemas['MachineModification']
export type Signalement = Schemas['Signalement']
export type SignalementCreation = Schemas['SignalementCreation']
export type StatutSignalement = Schemas['StatutSignalement']
export type CibleSignalement = Schemas['CibleSignalement']
export type MotifSignalement = Schemas['MotifSignalement']
export type CompteAdmin = Schemas['CompteAdmin']
export type SuspensionCreation = Schemas['SuspensionCreation']
export type Canal = Schemas['Canal']
export type CanalModification = Schemas['CanalModification']
export type Deploiement = Schemas['Deploiement']
export type Page<T> = Schemas['Pagination'] & { data: T[] }

async function objet<T>(promesse: Promise<{ data: T }>): Promise<T> {
  const { data } = await promesse
  if (data === undefined || data === null || typeof data !== 'object') throw new Error('Réponse vide')
  return data
}

async function page<T>(promesse: Promise<{ data: Page<T> }>): Promise<Page<T>> {
  const { data } = await promesse
  if (!data || !Array.isArray(data.data) || !data.meta) throw new Error('Réponse vide')
  return data
}

export function listerMachines(): Promise<Machine[]> {
  return objet<{ data: Machine[] }>(clientLocal.get(cheminLaravel('/admin/machines'))).then((corps) => {
    if (!Array.isArray(corps.data)) throw new Error('Réponse vide')
    return corps.data
  })
}

export function lireMachine(id: number): Promise<Machine> {
  return objet(clientLocal.get<Machine>(cheminLaravel(`/admin/machines/${id}`)))
}

export async function creerMachine(corps: MachineCreation): Promise<Machine> {
  await preparerCsrf()
  return objet(clientLocal.post<Machine>(cheminLaravel('/admin/machines'), corps))
}

export async function modifierMachine(id: number, corps: MachineModification): Promise<Machine> {
  await preparerCsrf()
  return objet(clientLocal.patch<Machine>(cheminLaravel(`/admin/machines/${id}`), corps))
}

export async function supprimerMachine(id: number): Promise<void> {
  await preparerCsrf()
  await clientLocal.delete(cheminLaravel(`/admin/machines/${id}`))
}

/** La sonde tourne sur la file « sondes » (à part du moteur) : 202 aussitôt, l'état se relit ensuite. */
export async function sonderMachine(id: number): Promise<Machine> {
  await preparerCsrf()
  return objet(clientLocal.post<Machine>(cheminLaravel(`/admin/machines/${id}/sonde`)))
}

/** Signaler un contenu ou un comportement à l'équipe. */
export async function signaler(corps: SignalementCreation): Promise<Signalement> {
  await preparerCsrf()
  return objet(clientLocal.post<Signalement>(cheminLaravel('/signalements'), corps))
}

export function listerSignalements(parametres: { statut?: StatutSignalement; page?: number }): Promise<Page<Signalement>> {
  return page(clientLocal.get<Page<Signalement>>(cheminLaravel('/admin/signalements'), { params: parametres }))
}

export async function traiterSignalement(id: number, note?: string): Promise<Signalement> {
  await preparerCsrf()
  return objet(clientLocal.post<Signalement>(cheminLaravel(`/admin/signalements/${id}/traiter`), { note }))
}

export async function rejeterSignalement(id: number, note?: string): Promise<Signalement> {
  await preparerCsrf()
  return objet(clientLocal.post<Signalement>(cheminLaravel(`/admin/signalements/${id}/rejeter`), { note }))
}

export function listerComptes(parametres: { recherche?: string; suspendus?: boolean; page?: number }): Promise<Page<CompteAdmin>> {
  return page(clientLocal.get<Page<CompteAdmin>>(cheminLaravel('/admin/comptes'), { params: parametres }))
}

export async function suspendreCompte(id: number, corps: SuspensionCreation): Promise<CompteAdmin> {
  await preparerCsrf()
  return objet(clientLocal.post<CompteAdmin>(cheminLaravel(`/admin/comptes/${id}/suspension`), corps))
}

export async function leverSuspensionCompte(id: number): Promise<CompteAdmin> {
  await preparerCsrf()
  return objet(clientLocal.delete<CompteAdmin>(cheminLaravel(`/admin/comptes/${id}/suspension`)))
}

export function listerCopiesAdmin(parametres: { suspension_admin?: boolean; page?: number }): Promise<Page<Deploiement>> {
  return page(clientLocal.get<Page<Deploiement>>(cheminLaravel('/admin/deployments'), { params: parametres }))
}

export async function suspendreCopie(id: number, corps: SuspensionCreation): Promise<Deploiement> {
  await preparerCsrf()
  return objet(clientLocal.post<Deploiement>(cheminLaravel(`/admin/deployments/${id}/suspension`), corps))
}

export async function reactiverCopie(id: number): Promise<Deploiement> {
  await preparerCsrf()
  return objet(clientLocal.delete<Deploiement>(cheminLaravel(`/admin/deployments/${id}/suspension`)))
}

export function listerCanaux(): Promise<Canal[]> {
  return objet<Canal[]>(clientLocal.get(cheminLaravel('/canaux')))
}

export async function modifierCanal(slug: string, corps: CanalModification): Promise<Canal> {
  await preparerCsrf()
  return objet(clientLocal.patch<Canal>(cheminLaravel(`/admin/canaux/${slug}`), corps))
}

export async function supprimerCanal(slug: string): Promise<void> {
  await preparerCsrf()
  await clientLocal.delete(cheminLaravel(`/admin/canaux/${slug}`))
}
