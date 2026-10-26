import { useEffect, useRef } from 'react'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { clientLocal } from '../shared/client'
import { preparerCsrf } from './preparerCsrf'

let connexion: Echo<'reverb'> | null | undefined
/** Écrans abonnés à chaque canal : on ne quitte un canal que lorsque le dernier s'en va. */
const abonnes = new Map<string, number>()

type AbonnementPusher = {
  subscribed?: boolean
  bind: (evenement: string, rappel: () => void) => void
  unbind: (evenement: string, rappel: () => void) => void
}

/**
 * Connexion Reverb pour les canaux privés (factures, déploiements) : une seule par onglet,
 * autorisée par la session (POST /broadcasting/auth avec le jeton CSRF). Sans Reverb configuré,
 * rien ne casse : les écrans relisent l'API.
 */
function echoPrive(): Echo<'reverb'> | null {
  if (connexion !== undefined) return connexion

  const hote = import.meta.env.VITE_REVERB_HOST
  const port = Number(import.meta.env.VITE_REVERB_PORT)
  const cle = import.meta.env.VITE_REVERB_KEY
  const schema = import.meta.env.VITE_REVERB_SCHEME

  if (!hote || !cle || (schema !== 'http' && schema !== 'https') || !Number.isInteger(port)) {
    connexion = null
    return connexion
  }

  try {
    connexion = new Echo({
      broadcaster: 'reverb',
      key: cle,
      wsHost: hote,
      wsPort: port,
      wssPort: port,
      forceTLS: schema === 'https',
      enabledTransports: ['ws', 'wss'],
      disableStats: true,
      Pusher,
      authorizer: (canal: { name: string }) => ({
        authorize: (socketId: string, rappel: (erreur: Error | null, donnees: { auth: string } | null) => void) => {
          preparerCsrf()
            .then(() => clientLocal.post<{ auth: string }>('/broadcasting/auth', { socket_id: socketId, channel_name: canal.name }))
            .then((reponse) => rappel(null, reponse.data))
            .catch((erreur: Error) => rappel(erreur, null))
        },
      }),
    })
    connexion.connector.pusher.connection.bind('error', () => undefined)
  } catch {
    connexion = null
  }

  return connexion
}

/**
 * Écoute un événement d'un canal privé tant que l'écran est affiché (nom null : rien).
 * apresAbonnement sert à relire les données une fois abonné : un changement survenu entre le
 * chargement de l'écran et l'abonnement ne doit pas se perdre.
 */
export function useCanalPrive<T>(nom: string | null, evenement: string, recevoir: (donnees: T) => void, apresAbonnement?: () => void): void {
  const rappel = useRef(recevoir)
  const abonne = useRef(apresAbonnement)

  useEffect(() => {
    rappel.current = recevoir
    abonne.current = apresAbonnement
  })

  useEffect(() => {
    const echo = nom ? echoPrive() : null
    if (!echo || !nom) return
    const canal = echo.private(nom)
    const abonnement = (canal as unknown as { subscription?: AbonnementPusher }).subscription
    const ecouteur = (donnees: T) => rappel.current(donnees)
    const surAbonnement = () => abonne.current?.()
    // Autorisation refusée (session expirée) : le canal est oublié, le prochain écran le redemande.
    const surRefus = () => {
      abonnes.delete(nom)
      echo.leave(nom)
    }
    abonnes.set(nom, (abonnes.get(nom) ?? 0) + 1)
    canal.listen(`.${evenement}`, ecouteur)
    // Canal déjà ouvert par un autre écran (la cloche de l'en-tête) : l'abonnement est acquis.
    if (abonnement?.subscribed) surAbonnement()
    abonnement?.bind('pusher:subscription_succeeded', surAbonnement)
    abonnement?.bind('pusher:subscription_error', surRefus)
    return () => {
      canal.stopListening(`.${evenement}`, ecouteur)
      abonnement?.unbind('pusher:subscription_succeeded', surAbonnement)
      abonnement?.unbind('pusher:subscription_error', surRefus)
      const restants = (abonnes.get(nom) ?? 1) - 1
      if (restants > 0) {
        abonnes.set(nom, restants)
      } else {
        abonnes.delete(nom)
        echo.leave(nom)
      }
    }
  }, [nom, evenement])
}
