import { useEffect, useRef, useState } from 'react'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import type { Discussion } from './api'
import { correspond, type FiltresFil } from './filtres'

function arreter() {
  return undefined
}

export function ecouterNouvellesDiscussions(recevoir: (discussion: Discussion) => void): () => void {
  const hote = import.meta.env.VITE_REVERB_HOST
  const port = import.meta.env.VITE_REVERB_PORT
  const cle = import.meta.env.VITE_REVERB_KEY
  const schema = import.meta.env.VITE_REVERB_SCHEME
  const portNombre = Number(port)

  if (!hote || !cle || (schema !== 'http' && schema !== 'https') || !Number.isInteger(portNombre)) {
    return arreter
  }

  try {
    const echo = new Echo({
      broadcaster: 'reverb',
      key: cle,
      wsHost: hote,
      wsPort: portNombre,
      wssPort: portNombre,
      forceTLS: schema === 'https',
      enabledTransports: ['ws', 'wss'],
      disableStats: true,
      Pusher,
    })
    echo.connector.pusher.connection.bind('error', arreter)
    echo.channel('discussions').listen('.discussion.creee', recevoir)
    return () => {
      echo.leave('discussions')
      echo.disconnect()
    }
  } catch {
    return arreter
  }
}

export function useBandeau(filtres: FiltresFil) {
  const [nouvelles, setNouvelles] = useState<Discussion[]>([])
  const filtresCourants = useRef(filtres)

  useEffect(() => {
    filtresCourants.current = filtres
  }, [filtres])

  useEffect(() => {
    return ecouterNouvellesDiscussions((discussion) => {
      if (!correspond(discussion, filtresCourants.current)) return
      setNouvelles((liste) => (liste.some((item) => item.id === discussion.id) ? liste : [...liste, discussion]))
    })
  }, [])

  const visibles = nouvelles.filter((discussion) => correspond(discussion, filtres))

  function prendre(): Discussion[] {
    const recues = visibles
    const ids = new Set(recues.map((discussion) => discussion.id))
    setNouvelles((liste) => liste.filter((discussion) => !ids.has(discussion.id)))
    return recues
  }

  return { nombre: visibles.length, prendre }
}
