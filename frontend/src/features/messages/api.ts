import type { components } from '../../shared/types/api'
import { cheminLaravel, clientLocal } from '../../shared/client'
import { preparerCsrf } from '../../app/preparerCsrf'

export type Conversation = components['schemas']['Conversation']
export type MessagePrive = components['schemas']['Message']
type Page<T> = components['schemas']['Pagination'] & { data: T[] }

/** Mes conversations : invitations reçues d'abord, puis les plus récemment actives. */
export async function listerConversations(): Promise<Conversation[]> {
  const { data } = await clientLocal.get<Page<Conversation>>(cheminLaravel('/me/conversations'), { params: { per_page: 50 } })
  if (!data || !Array.isArray(data.data)) throw new Error('Réponse vide')
  return data.data
}

/** Inviter un dev à échanger : rien ne s'ouvre tant qu'il n'a pas accepté. */
export async function inviterDev(username: string, message: string, discussionId?: number): Promise<Conversation> {
  await preparerCsrf()
  const { data } = await clientLocal.post<Conversation>(cheminLaravel(`/devs/${encodeURIComponent(username)}/invitation`), {
    message,
    discussion_id: discussionId,
  })
  return data
}

export async function repondreInvitation(id: number, accepter: boolean): Promise<Conversation> {
  await preparerCsrf()
  const { data } = await clientLocal.post<Conversation>(cheminLaravel(`/conversations/${id}/reponse`), { accepter })
  return data
}

/** Les messages d'une conversation (la lecture la marque comme lue côté serveur). */
export async function listerMessagesPrives(id: number): Promise<MessagePrive[]> {
  const { data } = await clientLocal.get<Page<MessagePrive>>(cheminLaravel(`/conversations/${id}/messages`), { params: { per_page: 50 } })
  if (!data || !Array.isArray(data.data)) throw new Error('Réponse vide')
  return data.data
}

export async function ecrireMessagePrive(id: number, texte: string): Promise<MessagePrive> {
  await preparerCsrf()
  const { data } = await clientLocal.post<MessagePrive>(cheminLaravel(`/conversations/${id}/messages`), { texte })
  return data
}
