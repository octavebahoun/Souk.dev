import type { components } from '../../shared/types/api'
import { cheminLaravel, clientLocal } from '../../shared/client'
import { preparerCsrf } from '../../app/preparerCsrf'

export type Notification = components['schemas']['Notification']
export type TypeNotification = components['schemas']['TypeNotification']
export type PreferencesNotifications = components['schemas']['PreferencesNotifications']
export type ReglageNotification = PreferencesNotifications['types'][number]
export type PageNotifications = components['schemas']['Pagination'] & { data: Notification[]; non_lues: number }

export async function listerNotifications(params: { filtre?: 'toutes' | 'non_lues'; page?: number; per_page?: number }): Promise<PageNotifications> {
  const { data } = await clientLocal.get<PageNotifications>(cheminLaravel('/me/notifications'), { params })
  if (!data || !Array.isArray(data.data)) throw new Error('Réponse vide')
  return data
}

export async function lireNotification(id: string): Promise<Notification> {
  await preparerCsrf()
  const { data } = await clientLocal.post<Notification>(cheminLaravel(`/me/notifications/${id}/lecture`))
  return data
}

export async function toutLire(): Promise<void> {
  await preparerCsrf()
  await clientLocal.post(cheminLaravel('/me/notifications/lecture'))
}

export async function lirePreferences(): Promise<PreferencesNotifications> {
  const { data } = await clientLocal.get<PreferencesNotifications>(cheminLaravel('/me/preferences-notifications'))
  if (!data || !Array.isArray(data.types)) throw new Error('Réponse vide')
  return data
}

export async function modifierPreference(reglage: Pick<ReglageNotification, 'type' | 'application' | 'email'>): Promise<PreferencesNotifications> {
  await preparerCsrf()
  const { data } = await clientLocal.put<PreferencesNotifications>(cheminLaravel('/me/preferences-notifications'), { preferences: [reglage] })
  return data
}
