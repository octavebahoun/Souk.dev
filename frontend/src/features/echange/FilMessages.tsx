import { useState, type KeyboardEvent } from 'react'
import { Link } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Avatar } from '../../shared/ui/Avatar'
import { Badge } from '../../shared/ui/Badge'
import { EmptyState } from '../../shared/ui/EmptyState'
import { creerMessage, listerMessages, lireErreurHttp, messageAction } from './api'
import { ilYA, nomAuteur } from './texte'
import { TexteMarkdown } from './TexteMarkdown'

const boutonAccent =
  'inline-flex min-h-cible items-center justify-center rounded-controle bg-accent px-5 font-semibold text-canvas disabled:opacity-60'

type FilMessagesProps = {
  discussionId: number
  auteurId: number
  page: number
  onPage: (page: number) => void
}

export function FilMessages({ discussionId, auteurId, page, onPage }: FilMessagesProps) {
  const client = useQueryClient()
  const [texte, setTexte] = useState('')
  const [erreurLocale, setErreurLocale] = useState<string | null>(null)
  const [erreurServeur, setErreurServeur] = useState<string | null>(null)
  const messages = useQuery({
    queryKey: ['messages', discussionId, page],
    queryFn: () => listerMessages(discussionId, page),
  })

  const envoyer = useMutation({
    mutationFn: () => creerMessage(discussionId, { texte: texte.trim() }),
    onSuccess: async () => {
      setTexte('')
      setErreurLocale(null)
      setErreurServeur(null)
      await client.invalidateQueries({ queryKey: ['messages', discussionId] })
      await client.invalidateQueries({ queryKey: ['discussion', discussionId] })
    },
    onError: (recue) => {
      const lu = lireErreurHttp(recue, 'Le message n’a pas été envoyé.')
      setErreurServeur(lu.champs.texte || messageAction(recue, 'Le message n’a pas été envoyé.'))
    },
  })

  function soumettre(evenement: { preventDefault: () => void }) {
    evenement.preventDefault()
    if (!texte.trim()) {
      setErreurLocale('Écrivez un message avant d’envoyer.')
      return
    }
    setErreurLocale(null)
    envoyer.mutate()
  }

  function entree(evenement: KeyboardEvent<HTMLTextAreaElement>) {
    if (evenement.key !== 'Enter' || evenement.shiftKey) return
    evenement.preventDefault()
    evenement.currentTarget.form?.requestSubmit()
  }

  const total = messages.data ? Math.max(messages.data.meta.total, messages.data.data.length) : 0

  return (
    <section aria-labelledby="titre-messages" className="verre flex flex-col gap-3 rounded-carte px-5 pb-5">
      <h2 id="titre-messages" className="m-0 mt-4 text-[17px] font-bold">
        Messages <span className="text-[15px] font-medium text-muted">{messages.isSuccess ? total : ''}</span>
      </h2>

      {messages.isPending ? (
        <div role="status" aria-label="Chargement des messages" className="squelette h-24 rounded-carte" />
      ) : null}
      {messages.isError ? (
        <button type="button" onClick={() => messages.refetch()} className={boutonAccent}>
          Réessayer
        </button>
      ) : null}
      {messages.isSuccess && messages.data.data.length === 0 ? <EmptyState message="Aucun message pour l’instant." /> : null}
      {messages.isSuccess && messages.data.data.length > 0 ? (
        <ol className="m-0 flex list-none flex-col p-0">
          {messages.data.data.map((message) => (
            <li key={message.id} className="grid grid-cols-[36px_minmax(0,1fr)] gap-3 border-t border-[#EEF2F9] py-4">
              <Avatar nom={message.auteur.nom} username={message.auteur.username} avatarUrl={message.auteur.avatar_url} taille="moyen" />
              <div className="flex min-w-0 flex-col gap-1">
                <div className="flex flex-wrap items-center gap-2 text-sm">
                  {message.auteur.username ? (
                    <Link to={`/devs/${message.auteur.username}`} className="font-semibold text-ink no-underline">
                      {nomAuteur(message.auteur.nom, message.auteur.username)}
                    </Link>
                  ) : (
                    <span className="font-semibold">{nomAuteur(message.auteur.nom, message.auteur.username)}</span>
                  )}
                  {message.auteur.id === auteurId ? <Badge ton="question">auteur</Badge> : null}
                  <time dateTime={message.cree_le} className="text-muted">
                    {ilYA(message.cree_le)}
                  </time>
                </div>
                <TexteMarkdown texte={message.texte} className="text-ink" />
              </div>
            </li>
          ))}
        </ol>
      ) : null}

      {messages.data && messages.data.meta.last_page > 1 ? (
        <div className="flex flex-wrap gap-2">
          <button type="button" disabled={page <= 1} onClick={() => onPage(page - 1)} className={boutonAccent}>
            Messages précédents
          </button>
          <button type="button" disabled={page >= messages.data.meta.last_page} onClick={() => onPage(page + 1)} className={boutonAccent}>
            Messages suivants
          </button>
        </div>
      ) : null}

      <form onSubmit={soumettre} className="flex flex-col gap-2.5 border-t border-[#EEF2F9] pt-4">
        <label htmlFor="reponse" className="text-sm font-semibold">
          Votre réponse
        </label>
        <textarea
          id="reponse"
          rows={4}
          value={texte}
          onChange={(evenement) => setTexte(evenement.target.value)}
          onKeyDown={entree}
          aria-invalid={erreurLocale ? true : undefined}
          aria-describedby={erreurLocale ? 'reponse-erreur' : undefined}
          placeholder="Expliquez, collez du code entre ``` ou proposez une piste…"
          className="min-h-24 resize-y rounded-controle border border-line bg-page px-3 py-2.5 text-ink"
        />
        {erreurLocale ? (
          <p id="reponse-erreur" role="alert" className="m-0 text-[13.5px] text-danger">
            {erreurLocale}
          </p>
        ) : null}
        {erreurServeur ? (
          <p role="alert" className="m-0 text-[13.5px] text-danger">
            {erreurServeur}
          </p>
        ) : null}
        <div className="flex flex-wrap items-center justify-between gap-2.5">
          <span className="text-[13px] text-muted">Markdown accepté. Entrée envoie, Maj+Entrée passe une ligne.</span>
          <button type="submit" disabled={envoyer.isPending} className={boutonAccent}>
            Envoyer
          </button>
        </div>
      </form>
    </section>
  )
}
