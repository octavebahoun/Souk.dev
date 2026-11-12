import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Alerte, Bouton, Champ, Modale, ZoneTexte, useToast } from '../../ui'
import { messageErreur } from '../paiements/api'
import { inviterDev } from './api'

type InviterDevProps = {
  ouverte: boolean
  onFermer: () => void
  username: string
  nom: string
  /** Bug sur lequel on demande de l'aide : il est joint à l'invitation. */
  discussion?: { id: number; titre: string }
}

/**
 * Invitation à échanger en privé : un mot d'introduction, que l'autre dev lit avant d'accepter
 * ou de décliner. Personne ne reçoit de message non sollicité.
 */
export function InviterDev({ ouverte, onFermer, username, nom, discussion }: InviterDevProps) {
  const cache = useQueryClient()
  const toast = useToast()
  const naviguer = useNavigate()
  const [message, setMessage] = useState(discussion ? `Bonjour ${nom}, je bloque sur « ${discussion.titre} ». Votre profil correspond : auriez-vous un moment pour y jeter un œil ?` : '')
  const [erreurChamp, setErreurChamp] = useState<string | null>(null)

  const inviter = useMutation({
    mutationFn: () => inviterDev(username, message.trim(), discussion?.id),
    onSuccess: async (conversation) => {
      await cache.invalidateQueries({ queryKey: ['conversations'] })
      toast.succes(`Invitation envoyée à ${nom}. La conversation s’ouvre dès qu’elle est acceptée.`, {
        libelle: 'Voir',
        onClick: () => void naviguer(`/messages?conversation=${conversation.id}`),
      })
      onFermer()
    },
  })

  function envoyer(evenement: FormEvent) {
    evenement.preventDefault()
    if (message.trim().length < 10) {
      setErreurChamp('Présentez-vous en quelques mots (10 caractères au moins) : qui vous êtes, pourquoi vous écrivez.')
      return
    }
    setErreurChamp(null)
    inviter.mutate()
  }

  return (
    <Modale
      ouverte={ouverte}
      onFermer={onFermer}
      titre={`Inviter ${nom} à échanger`}
      description="Sur Souk.dev, personne n’écrit à un dev sans son accord : il lit votre mot, puis accepte ou décline."
      pied={
        <>
          <Bouton variante="contour" onClick={onFermer}>
            Annuler
          </Bouton>
          <Bouton type="submit" form="inviter-dev" chargement={inviter.isPending}>
            Envoyer l’invitation
          </Bouton>
        </>
      }
    >
      <form id="inviter-dev" onSubmit={envoyer} className="flex flex-col gap-4" noValidate>
        {discussion ? (
          <p className="m-0 rounded-moyen bg-surface-douce p-3 text-petit text-texte">
            Jointe à l’invitation : <strong className="font-medium text-titre">{discussion.titre}</strong>
          </p>
        ) : null}
        <Champ libelle="Votre mot d’introduction" erreur={erreurChamp} aide={`${message.trim().length} / 500 caractères`}>
          {(liaison) => (
            <ZoneTexte
              {...liaison}
              rows={5}
              maxLength={500}
              value={message}
              onChange={(evenement) => setMessage(evenement.target.value)}
              placeholder="Ex. : Bonjour, je travaille aussi sur une intégration MTN MoMo en Laravel et j’ai une question sur votre appli…"
              data-autofocus
            />
          )}
        </Champ>
        {inviter.isError ? <Alerte ton="danger">{messageErreur(inviter.error, 'L’invitation n’a pas pu être envoyée.')}</Alerte> : null}
      </form>
    </Modale>
  )
}
