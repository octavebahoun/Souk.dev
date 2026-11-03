import { useEffect, useRef, useState } from 'react'
import { useQueryClient, type QueryClient } from '@tanstack/react-query'
import { ouvrirReverb, reverbConfigure, type ConnexionReverb } from '../../app/reverb'
import { listerDiscussions, type Discussion } from './api'
import { correspond, type FiltresFil } from './filtres'

/**
 * Ouvre une connexion le temps qu'un écran est affiché : brancher la reçoit une fois ouverte,
 * la fonction rendue la ferme (même si elle s'ouvre après le départ de l'écran). Sans connexion
 * (Reverb absent, bibliothèque introuvable), siIndisponible prévient l'écran, qui relit l'API.
 */
function pendantLAffichage(brancher: (echo: ConnexionReverb) => () => void, siIndisponible?: () => void): () => void {
  let actif = true
  let debrancher: (() => void) | undefined
  void ouvrirReverb().then((echo) => {
    if (!echo) {
      if (actif) siIndisponible?.()
      return
    }
    if (!actif) {
      echo.disconnect()
      return
    }
    const quitter = brancher(echo)
    debrancher = () => {
      quitter()
      echo.disconnect()
    }
  })
  return () => {
    actif = false
    debrancher?.()
  }
}

export function ecouterNouvellesDiscussions(recevoir: (discussion: Discussion) => void, siIndisponible?: () => void): () => void {
  return pendantLAffichage((echo) => {
    echo.channel('discussions').listen('.discussion.creee', recevoir)
    return () => echo.leave('discussions')
  }, siIndisponible)
}

function ecouterUneDiscussion(id: number, client: QueryClient, onSupprimee: () => void): () => void {
  return pendantLAffichage((echo) => brancherDiscussion(echo, id, client, onSupprimee))
}

function brancherDiscussion(echo: ConnexionReverb, id: number, client: QueryClient, onSupprimee: () => void): () => void {
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
  canal.listen('.analyse_ia.terminee', relireDiscussion)
  canal.listen('.correctif.accepte', () => {
    relireCorrectifs()
    relireDiscussion()
  })

  return () => echo.leave(`discussion.${id}`)
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
  // En direct tant que Reverb répond ; sinon le fil se relit toutes les 15 secondes.
  const [direct, setDirect] = useState(reverbConfigure)
  const filtresCourants = useRef(filtres)

  useEffect(() => {
    filtresCourants.current = filtres
  }, [filtres])

  useEffect(() => {
    return ecouterNouvellesDiscussions(
      (discussion) => {
        if (!correspond(discussion, filtresCourants.current)) return
        setNouvelles((liste) => (liste.some((item) => item.id === discussion.id) ? liste : [...liste, discussion]))
      },
      () => setDirect(false),
    )
  }, [])

  useEffect(() => {
    if (direct) return
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
  }, [direct])

  const visibles = nouvelles.filter((discussion) => correspond(discussion, filtres))

  function prendre(): Discussion[] {
    const recues = visibles
    const ids = new Set(recues.map((discussion) => discussion.id))
    setNouvelles((liste) => liste.filter((discussion) => !ids.has(discussion.id)))
    return recues
  }

  return { nombre: visibles.length, prendre }
}
