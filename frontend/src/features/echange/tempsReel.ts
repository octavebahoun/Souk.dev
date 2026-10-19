import { useEffect, useRef, useState } from 'react'
import { useQueryClient, type QueryClient } from '@tanstack/react-query'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { listerDiscussions, type Discussion } from './api'
import { correspond, type FiltresFil } from './filtres'

function echoOuNull(): Echo<'reverb'> | null {
  const hote = import.meta.env.VITE_REVERB_HOST
  const port = import.meta.env.VITE_REVERB_PORT
  const cle = import.meta.env.VITE_REVERB_KEY
  const schema = import.meta.env.VITE_REVERB_SCHEME
  const portNombre = Number(port)

  if (!hote || !cle || (schema !== 'http' && schema !== 'https') || !Number.isInteger(portNombre)) {
    return null
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
    echo.connector.pusher.connection.bind('error', () => undefined)
    return echo
  } catch {
    return null
  }
}

export function ecouterNouvellesDiscussions(recevoir: (discussion: Discussion) => void): () => void {
  const echo = echoOuNull()
  if (!echo) return () => undefined
  echo.channel('discussions').listen('.discussion.creee', recevoir)
  return () => {
    echo.leave('discussions')
    echo.disconnect()
  }
}

function ecouterUneDiscussion(id: number, client: QueryClient, onSupprimee: () => void): () => void {
  const echo = echoOuNull()
  if (!echo) return () => undefined
  const canal = echo.channel(`discussion.${id}`)
  const relireDiscussion = () => {
    void client.invalidateQueries({ queryKey: ['discussion', id] })
  }
  const relireMessages = () => {
    void client.invalidateQueries({ queryKey: ['messages', id] })
  }
  const relireCorrectifs = () => {
    void client.invalidateQueries({ queryKey: ['correctifs', id] })
  }

  canal.listen('.discussion.modifiee', relireDiscussion)
  canal.listen('.discussion.supprimee', (corps: { id?: number }) => {
    if (corps?.id === id) onSupprimee()
  })
  canal.listen('.message.cree', () => {
    relireMessages()
    relireDiscussion()
  })
  canal.listen('.message.modifie', relireMessages)
  canal.listen('.message.supprime', () => {
    relireMessages()
    relireDiscussion()
  })
  canal.listen('.copie_test.etat', relireDiscussion)
  canal.listen('.correctif.propose', relireCorrectifs)
  canal.listen('.correctif.etat', relireCorrectifs)
  canal.listen('.correctif.accepte', () => {
    relireCorrectifs()
    relireDiscussion()
  })

  return () => {
    echo.leave(`discussion.${id}`)
    echo.disconnect()
  }
}

export function useDiscussionDirect(id: number, actif: boolean): boolean {
  const client = useQueryClient()
  const [supprimeeId, setSupprimeeId] = useState<number | null>(null)

  useEffect(() => {
    if (!actif) return
    return ecouterUneDiscussion(id, client, () => setSupprimeeId(id))
  }, [actif, client, id])

  return supprimeeId === id
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

  useEffect(() => {
    if (echoOuNull()) return
    let ignore = false
    const connus = new Set<number>()
    let premier = true

    async function relever() {
      try {
        const page = await listerDiscussions(filtresCourants.current)
        if (ignore || !Array.isArray(page.data)) return
        if (premier) {
          for (const discussion of page.data) connus.add(discussion.id)
          premier = false
          return
        }
        const fraiches = page.data.filter((discussion) => !connus.has(discussion.id))
        for (const discussion of fraiches) connus.add(discussion.id)
        const retenues = fraiches.filter((discussion) => correspond(discussion, filtresCourants.current))
        if (retenues.length === 0) return
        setNouvelles((liste) => {
          const ids = new Set(liste.map((item) => item.id))
          return [...retenues.filter((discussion) => !ids.has(discussion.id)), ...liste]
        })
      } catch {
        // Le fil affiche déjà son erreur de chargement.
      }
    }

    const timer = window.setInterval(() => void relever(), 15000)
    return () => {
      ignore = true
      window.clearInterval(timer)
    }
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
