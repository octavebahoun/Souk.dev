import { useState, type KeyboardEvent } from 'react'
import { Link } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Send } from 'lucide-react'
import { Alerte, Avatar, Badge, Bouton, Carte, Champ, EnTeteCarte, EtatErreur, Pagination, SqueletteLignes, ZoneTexte } from '../../ui'
import { creerMessage, listerMessages, lireErreurHttp, messageAction } from './api'
import { Signaler } from '../admin/Signaler'
import { ilYA, nomAuteur } from './texte'
import { TexteMarkdown } from './TexteMarkdown'

type FilMessagesProps = {
  discussionId: number
  auteurId: number
  page: number
  onPage: (page: number) => void
  connecteId: number | null
}

export function FilMessages({ discussionId, auteurId, page, onPage, connecteId }: FilMessagesProps) {
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
    <Carte as="section" aria-labelledby="titre-messages" className="flex flex-col gap-4">
      <EnTeteCarte
        titre={
          <span id="titre-messages">
            Messages{messages.isSuccess ? <span className="ml-2 text-corps font-normal text-texte-doux">{total}</span> : null}
          </span>
        }
      />

      {messages.isPending ? (
        <div role="status" aria-label="Chargement des messages">
          <SqueletteLignes lignes={3} />
        </div>
      ) : null}
      {messages.isError ? <EtatErreur description="Les messages n’ont pas pu être chargés." onReessayer={() => void messages.refetch()} /> : null}
      {messages.isSuccess && messages.data.data.length === 0 ? <p className="m-0 text-corps text-texte-doux">Aucun message pour l’instant.</p> : null}
      {messages.isSuccess && messages.data.data.length > 0 ? (
        <ol className="m-0 flex list-none flex-col p-0">
          {messages.data.data.map((message) => {
            const nom = nomAuteur(message.auteur.nom, message.auteur.username)
            return (
              <li key={message.id} className="grid grid-cols-[2.5rem_minmax(0,1fr)] gap-3 border-t border-bordure py-4 first:border-t-0 first:pt-0">
                <Avatar nom={nom} url={message.auteur.avatar_url} />
                <div className="flex min-w-0 flex-col gap-1.5">
                  <div className="flex flex-wrap items-center gap-2 text-petit text-texte-doux">
                    {message.auteur.username ? (
                      <Link to={`/devs/${message.auteur.username}`} className="font-medium text-titre no-underline hover:underline">
                        {nom}
                      </Link>
                    ) : (
                      <span className="font-medium text-titre">{nom}</span>
                    )}
                    {message.auteur.id === auteurId ? <Badge ton="marque">auteur</Badge> : null}
                    <time dateTime={message.cree_le}>{ilYA(message.cree_le)}</time>
                    {connecteId != null && connecteId !== message.auteur.id ? (
                      <span className="ml-auto">
                        <Signaler cible="message" cibleId={message.id} etiquette="ce message" />
                      </span>
                    ) : null}
                  </div>
                  <TexteMarkdown texte={message.texte} />
                </div>
              </li>
            )
          })}
        </ol>
      ) : null}

      {messages.data ? <Pagination page={page} dernierePage={messages.data.meta.last_page} onChange={onPage} /> : null}

      <form onSubmit={soumettre} className="flex flex-col gap-3 border-t border-bordure pt-4">
        <Champ libelle="Votre réponse" erreur={erreurLocale} aide="Markdown accepté. Entrée envoie, Maj+Entrée passe une ligne.">
          {(liaison) => (
            <ZoneTexte
              {...liaison}
              rows={4}
              value={texte}
              onChange={(evenement) => setTexte(evenement.target.value)}
              onKeyDown={entree}
              placeholder="Expliquez, collez du code entre ``` ou proposez une piste…"
            />
          )}
        </Champ>
        {erreurServeur ? <Alerte ton="danger">{erreurServeur}</Alerte> : null}
        <Bouton type="submit" className="self-end" chargement={envoyer.isPending} icone={<Send aria-hidden="true" className="size-4" />}>
          Envoyer
        </Bouton>
      </form>
    </Carte>
  )
}
