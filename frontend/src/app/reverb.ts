import type Echo from 'laravel-echo'
import type { EchoOptions } from 'laravel-echo'

export type ConnexionReverb = Echo<'reverb'>

type Autorisation = EchoOptions<'reverb'>['authorizer']

/** Reverb est-il configuré pour ce build ? (sinon les écrans relisent l'API) */
export function reverbConfigure(): boolean {
  return reglages() !== null
}

function reglages() {
  const hote = import.meta.env.VITE_REVERB_HOST
  const port = Number(import.meta.env.VITE_REVERB_PORT)
  const cle = import.meta.env.VITE_REVERB_KEY
  const schema = import.meta.env.VITE_REVERB_SCHEME

  if (!hote || !cle || (schema !== 'http' && schema !== 'https') || !Number.isInteger(port)) {
    return null
  }

  return { key: cle, wsHost: hote, wsPort: port, wssPort: port, forceTLS: schema === 'https' }
}

/**
 * Ouvre une connexion Reverb. laravel-echo et pusher-js ne sont chargés qu'ici, au premier écran
 * temps réel : un visiteur de l'accueil ou du Store n'en paie pas le poids. Sans Reverb configuré
 * ou si le chargement échoue, rien ne casse : null, et les écrans relisent l'API.
 */
export async function ouvrirReverb(authorizer?: Autorisation): Promise<ConnexionReverb | null> {
  const config = reglages()
  if (!config) return null

  try {
    const [{ default: EchoClasse }, { default: Pusher }] = await Promise.all([import('laravel-echo'), import('pusher-js')])
    const echo = new EchoClasse({
      broadcaster: 'reverb',
      ...config,
      enabledTransports: ['ws', 'wss'],
      disableStats: true,
      Pusher,
      ...(authorizer ? { authorizer } : {}),
    })
    echo.connector.pusher.connection.bind('error', () => undefined)
    return echo
  } catch {
    return null
  }
}
