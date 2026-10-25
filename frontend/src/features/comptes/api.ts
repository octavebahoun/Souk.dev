import type { components } from '../../shared/types/api'
import { cheminLaravel, clientLocal } from '../../shared/client'
import { preparerCsrf } from '../../app/preparerCsrf'

export type ProfilDev = components['schemas']['ProfilDev']
export type TableauDeBord = components['schemas']['TableauDeBord']
export type Compte = components['schemas']['Compte']
export type MissionCreation = components['schemas']['MissionCreation']

export async function demanderLien(email: string): Promise<void> {
  await preparerCsrf()
  await clientLocal.post('/auth/lien-magique', { email })
}

export async function lireProfil(username: string): Promise<ProfilDev> {
  const { data } = await clientLocal.get<ProfilDev>(cheminLaravel(`/devs/${username}`))
  if (!data || typeof data.id !== 'number') throw new Error('Réponse vide')
  return data
}

export async function proposerMission(username: string, corps: MissionCreation): Promise<void> {
  await preparerCsrf()
  await clientLocal.post(cheminLaravel(`/devs/${username}/missions`), corps)
}

export async function lireTableau(): Promise<TableauDeBord> {
  const { data } = await clientLocal.get<TableauDeBord>(cheminLaravel('/me/tableau-de-bord'))
  if (!data || !data.totaux || !Array.isArray(data.applis)) throw new Error('Réponse vide')
  return data
}
