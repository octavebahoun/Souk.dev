import type { components } from '../../shared/types/api'
import { cheminLaravel, clientLocal } from '../../shared/client'
import { preparerCsrf } from '../../app/preparerCsrf'

type Schemas = components['schemas']
export type Machine = Schemas['Machine']
export type EtatMachine = Schemas['EtatMachine']
export type MachineCreation = Schemas['MachineCreation']
export type MachineModification = Schemas['MachineModification']

async function objet<T>(promesse: Promise<{ data: T }>): Promise<T> {
  const { data } = await promesse
  if (data === undefined || data === null || typeof data !== 'object') throw new Error('Réponse vide')
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

/** La sonde tourne sur le moteur : 202 aussitôt, l'état se relit ensuite. */
export async function sonderMachine(id: number): Promise<Machine> {
  await preparerCsrf()
  return objet(clientLocal.post<Machine>(cheminLaravel(`/admin/machines/${id}/sonde`)))
}
