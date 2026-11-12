import { useEffect, useRef, useState, type FormEvent, type KeyboardEvent } from 'react'
import { Link, useSearchParams } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, Check, Clock, Send, X } from 'lucide-react'
import { useCompte } from '../../app/compte'
import { useCanalPrive } from '../../app/tempsReelPrive'
import { useTitrePage } from '../../app/titre'
import { Alerte, Avatar, Badge, Bouton, EnTetePage, EtatErreur, EtatVide, LienBouton, Squelette, SqueletteListe, ZoneTexte, cx, useToast } from '../../ui'
import { TexteMarkdown } from '../echange/TexteMarkdown'
import { ilYA } from '../echange/texte'
import { messageErreur } from '../paiements/api'
import { ecrireMessagePrive, listerConversations, listerMessagesPrives, repondreInvitation, type Conversation, type MessagePrive } from './api'

function nomDe(conversation: Conversation): string {
  return conversation.avec?.nom || conversation.avec?.username || 'Compte supprimé'
}

/**
 * Messages privés entre devs. À gauche, les conversations (invitations à traiter d'abord) ; à
 * droite, l'échange. Rien ne s'écrit avant que l'invité ait accepté.
 */
export function Messages() {
  useTitrePage('Messages', { sansIndex: true })
  const compte = useCompte()
  const cache = useQueryClient()
  const [params, setParams] = useSearchParams()
  const choisie = Number(params.get('conversation')) || null
  const conversations = useQuery({
    queryKey: ['conversations'],
    queryFn: listerConversations,
    enabled: Boolean(compte.data),
    // Filet si le temps réel manque : la liste se relit toutes les 20 secondes.
    refetchInterval: 20_000,
  })

  const canal = compte.data ? `utilisateur.${compte.data.id}` : null
  useCanalPrive<Conversation>(canal, 'conversation.modifiee', () => void cache.invalidateQueries({ queryKey: ['conversations'] }))
  useCanalPrive<MessagePrive & { conversation_id: number }>(canal, 'message-prive.cree', (message) => {
    cache.setQueryData<MessagePrive[]>(['messages-prives', message.conversation_id], (liste) =>
      liste && !liste.some((item) => item.id === message.id) ? [...liste, message] : liste,
    )
    void cache.invalidateQueries({ queryKey: ['conversations'] })
  })

  function ouvrir(id: number | null) {
    setParams(id === null ? {} : { conversation: String(id) })
  }

  const liste = conversations.data ?? []
  const courante = liste.find((conversation) => conversation.id === choisie) ?? null
  const aTraiter = liste.filter((conversation) => conversation.statut === 'en_attente' && conversation.sens === 'recue').length

  return (
    <div className="flex flex-col gap-6">
      <EnTetePage
        titre="Messages"
        description="Vos échanges privés avec d’autres devs. Personne ne vous écrit sans que vous ayez accepté son invitation."
        actions={
          <LienBouton to="/devs" variante="contour">
            Trouver un dev
          </LienBouton>
        }
      />

      {conversations.isPending ? <SqueletteListe nombre={3} /> : null}
      {conversations.isError ? <EtatErreur description={messageErreur(conversations.error, 'Vos conversations n’ont pas pu être chargées.')} onReessayer={() => void conversations.refetch()} /> : null}

      {conversations.isSuccess && liste.length === 0 ? (
        <EtatVide
          illustration="vide-discussions"
          titre="Aucune conversation pour l’instant"
          description="Invitez un dev depuis son profil, ou depuis un bug où son aide compterait. Il lit votre mot d’introduction, puis accepte ou décline."
          action={<LienBouton to="/devs">Trouver un dev</LienBouton>}
        />
      ) : null}

      {liste.length > 0 ? (
        <div className="grid min-h-[28rem] gap-4 bureau:grid-cols-[20rem_minmax(0,1fr)]">
          <nav aria-label="Conversations" className={cx('flex-col gap-2', courante ? 'hidden bureau:flex' : 'flex')}>
            {aTraiter > 0 ? (
              <p className="m-0 text-petit font-medium text-titre">
                {aTraiter} invitation{aTraiter > 1 ? 's' : ''} à traiter
              </p>
            ) : null}
            <ul className="m-0 flex list-none flex-col gap-1.5 p-0">
              {liste.map((conversation) => (
                <li key={conversation.id}>
                  <LigneConversation conversation={conversation} active={conversation.id === choisie} onOuvrir={() => ouvrir(conversation.id)} />
                </li>
              ))}
            </ul>
          </nav>

          <section aria-label="Conversation" className={cx('min-w-0 flex-col rounded-moyen border border-bordure bg-surface shadow-1', courante ? 'flex' : 'hidden bureau:flex')}>
            {courante ? (
              <Echange key={courante.id} conversation={courante} moiId={compte.data?.id ?? 0} onRetour={() => ouvrir(null)} />
            ) : (
              <p className="m-auto max-w-xs p-6 text-center text-corps text-texte-doux">Choisissez une conversation à gauche pour la lire.</p>
            )}
          </section>
        </div>
      ) : null}
    </div>
  )
}

function LigneConversation({ conversation, active, onOuvrir }: { conversation: Conversation; active: boolean; onOuvrir: () => void }) {
  const nom = nomDe(conversation)
  const invitation = conversation.statut === 'en_attente'
  const extrait =
    conversation.statut === 'refusee'
      ? 'Invitation déclinée'
      : conversation.dernier_message
        ? `${conversation.dernier_message.de_moi ? 'Vous : ' : ''}${conversation.dernier_message.texte}`
        : conversation.invitation

  return (
    <button
      type="button"
      aria-current={active ? 'true' : undefined}
      onClick={onOuvrir}
      className={cx(
        'flex w-full items-start gap-3 rounded-moyen border p-3 text-left transition-colors duration-150',
        active ? 'border-primaire bg-surface-accent' : 'border-bordure bg-surface hover:border-bordure-champ hover:bg-surface-douce',
      )}
    >
      <Avatar nom={nom} url={conversation.avec?.avatar_url ?? null} taille="sm" />
      <span className="flex min-w-0 flex-1 flex-col gap-0.5">
        <span className="flex items-center justify-between gap-2">
          <span className="truncate font-medium text-titre">{nom}</span>
          <time dateTime={conversation.maj_le} className="shrink-0 text-petit text-texte-doux">
            {ilYA(conversation.maj_le)}
          </time>
        </span>
        <span className="line-clamp-2 text-petit text-texte">{extrait}</span>
        <span className="mt-1 flex flex-wrap gap-1.5">
          {invitation && conversation.sens === 'recue' ? <Badge ton="marque">Invitation à traiter</Badge> : null}
          {invitation && conversation.sens === 'envoyee' ? <Badge>En attente de réponse</Badge> : null}
          {conversation.non_lus > 0 ? (
            <Badge ton="alerte">
              {conversation.non_lus} non lu{conversation.non_lus > 1 ? 's' : ''}
            </Badge>
          ) : null}
        </span>
      </span>
    </button>
  )
}

function Echange({ conversation, moiId, onRetour }: { conversation: Conversation; moiId: number; onRetour: () => void }) {
  const cache = useQueryClient()
  const toast = useToast()
  const nom = nomDe(conversation)
  const acceptee = conversation.statut === 'acceptee'
  const [texte, setTexte] = useState('')
  const fil = useRef<HTMLDivElement>(null)

  const messages = useQuery({
    queryKey: ['messages-prives', conversation.id],
    queryFn: () => listerMessagesPrives(conversation.id),
    enabled: acceptee,
    refetchInterval: 15_000,
  })
  const nombre = messages.data?.length ?? 0

  // Le dernier message reste visible ; la lecture efface le compteur de non-lus de la liste.
  useEffect(() => {
    // Seul le fil défile jusqu'au dernier message : la page, elle, ne bouge pas (en-tête et retour restent en place).
    if (fil.current) fil.current.scrollTop = fil.current.scrollHeight
    if (nombre > 0) void cache.invalidateQueries({ queryKey: ['conversations'] })
  }, [nombre, cache])

  const repondre = useMutation({
    mutationFn: (accepter: boolean) => repondreInvitation(conversation.id, accepter),
    onSuccess: async (reponse) => {
      await cache.invalidateQueries({ queryKey: ['conversations'] })
      if (reponse.statut === 'acceptee') {
        toast.succes(`Conversation ouverte avec ${nom}.`)
      } else {
        toast.info('Invitation déclinée. Ce dev n’en est pas informé.')
        onRetour()
      }
    },
    onError: (erreur) => toast.erreur(messageErreur(erreur, 'Votre réponse n’a pas pu être enregistrée.')),
  })

  const ecrire = useMutation({
    mutationFn: (contenu: string) => ecrireMessagePrive(conversation.id, contenu),
    onSuccess: (message) => {
      setTexte('')
      cache.setQueryData<MessagePrive[]>(['messages-prives', conversation.id], (liste) => [...(liste ?? []).filter((item) => item.id !== message.id), message])
      void cache.invalidateQueries({ queryKey: ['conversations'] })
    },
  })

  function envoyer(evenement?: FormEvent) {
    evenement?.preventDefault()
    const contenu = texte.trim()
    if (contenu === '' || ecrire.isPending) return
    ecrire.mutate(contenu)
  }

  function auClavier(evenement: KeyboardEvent<HTMLTextAreaElement>) {
    if (evenement.key === 'Enter' && !evenement.shiftKey) {
      evenement.preventDefault()
      envoyer()
    }
  }

  return (
    <>
      <header className="flex items-center gap-3 border-b border-bordure p-3 tablette:p-4">
        <Bouton variante="fantome" taille="sm" onClick={onRetour} className="bureau:hidden" icone={<ArrowLeft aria-hidden="true" className="size-4" />}>
          Retour
        </Bouton>
        <Avatar nom={nom} url={conversation.avec?.avatar_url ?? null} taille="sm" />
        <div className="flex min-w-0 flex-1 flex-col leading-tight">
          <h2 className="m-0 truncate text-corps font-semibold text-titre">{nom}</h2>
          {conversation.avec?.username ? (
            <Link to={`/devs/${conversation.avec.username}`} className="text-petit text-texte-doux">
              Voir le profil
            </Link>
          ) : null}
        </div>
      </header>

      <div ref={fil} className="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto p-3 tablette:p-4" style={{ maxHeight: '60vh' }}>
        <div className="flex flex-col gap-2 rounded-moyen border border-dashed border-bordure bg-surface-douce p-3">
          <p className="m-0 text-etiquette text-texte-doux uppercase">{conversation.sens === 'recue' ? `Invitation de ${nom}` : 'Votre invitation'}</p>
          <p className="m-0 text-corps wrap-break-word whitespace-pre-wrap text-texte">{conversation.invitation}</p>
          {conversation.discussion ? (
            <Link to={`/echange/${conversation.discussion.id}`} className="text-petit font-medium underline underline-offset-2">
              Bug concerné : {conversation.discussion.titre}
            </Link>
          ) : null}
        </div>

        {conversation.statut === 'en_attente' && conversation.sens === 'recue' ? (
          <div className="flex flex-col gap-3 rounded-moyen border border-primaire bg-surface-accent p-4">
            <p className="m-0 text-corps text-titre">
              Accepter ouvre la conversation. Décliner la ferme sans prévenir {nom}, qui ne pourra pas vous réinviter avant 30 jours.
            </p>
            <div className="flex flex-wrap gap-2">
              <Bouton icone={<Check aria-hidden="true" className="size-4" />} chargement={repondre.isPending && repondre.variables === true} onClick={() => repondre.mutate(true)}>
                Accepter
              </Bouton>
              <Bouton variante="contour" icone={<X aria-hidden="true" className="size-4" />} chargement={repondre.isPending && repondre.variables === false} onClick={() => repondre.mutate(false)}>
                Décliner
              </Bouton>
            </div>
          </div>
        ) : null}

        {conversation.statut === 'en_attente' && conversation.sens === 'envoyee' ? (
          <p className="m-0 flex items-center gap-2 text-petit text-texte-doux">
            <Clock aria-hidden="true" className="size-4 shrink-0" />
            En attente de la réponse de {nom}. Vous pourrez écrire dès que l’invitation est acceptée.
          </p>
        ) : null}

        {conversation.statut === 'refusee' ? <Alerte ton="info">Cette invitation a été déclinée. Vous pourrez réinviter ce dev dans 30 jours.</Alerte> : null}

        {acceptee && messages.isPending ? <Squelette className="h-16 rounded-moyen" /> : null}
        {acceptee && messages.isError ? <EtatErreur niveau={3} description="Les messages n’ont pas pu être chargés." onReessayer={() => void messages.refetch()} /> : null}
        {acceptee && messages.isSuccess && nombre === 0 ? <p className="m-0 text-center text-petit text-texte-doux">Invitation acceptée. Écrivez le premier message.</p> : null}

        {(messages.data ?? []).map((message) => {
          const deMoi = message.auteur.id === moiId
          return (
            <div key={message.id} className={cx('flex max-w-[85%] flex-col gap-1', deMoi ? 'self-end items-end' : 'self-start items-start')}>
              <div className={cx('rounded-moyen px-3 py-2 text-corps wrap-break-word', deMoi ? 'bg-primaire-doux text-sur-primaire-doux' : 'bg-surface-douce text-texte')}>
                <TexteMarkdown texte={message.texte} />
              </div>
              <time dateTime={message.cree_le} className="text-petit text-texte-doux">
                {ilYA(message.cree_le)}
              </time>
            </div>
          )
        })}
      </div>

      {acceptee ? (
        <form onSubmit={envoyer} className="flex flex-col gap-2 border-t border-bordure p-3 tablette:p-4">
          {ecrire.isError ? <Alerte ton="danger">{messageErreur(ecrire.error, 'Le message n’est pas parti. Réessayez.')}</Alerte> : null}
          <div className="flex items-end gap-2">
            <label htmlFor="message-prive" className="pour-lecteur">
              Votre message à {nom}
            </label>
            <ZoneTexte
              id="message-prive"
              rows={2}
              maxLength={5000}
              value={texte}
              onChange={(evenement) => setTexte(evenement.target.value)}
              onKeyDown={auClavier}
              placeholder="Votre message… (Markdown accepté, Maj+Entrée pour passer une ligne)"
              className="flex-1"
            />
            <Bouton type="submit" chargement={ecrire.isPending} disabled={texte.trim() === ''} icone={<Send aria-hidden="true" className="size-4" />}>
              Envoyer
            </Bouton>
          </div>
        </form>
      ) : null}
    </>
  )
}
