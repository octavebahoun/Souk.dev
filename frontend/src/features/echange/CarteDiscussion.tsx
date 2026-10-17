import { Link } from 'react-router'
import { Avatar } from '../../shared/ui/Avatar'
import { Badge } from '../../shared/ui/Badge'
import type { Discussion, Etiquette } from './api'
import { ilYA } from './texte'

const libelles: Record<Etiquette, string> = {
  bug: 'bug',
  question: 'question',
  tuto: 'tuto',
  projet: 'projet',
  entraide: 'entraide',
}

type CarteDiscussionProps = {
  discussion: Discussion
  nouvelle?: boolean
  onPartager: (discussion: Discussion) => void
}

function nomAuteur(discussion: Discussion): string {
  if (discussion.auteur.username) return `@${discussion.auteur.username}`
  return discussion.auteur.nom || 'Quelqu’un'
}

function lieu(discussion: Discussion): string {
  if (discussion.canal) return `#${discussion.canal}`
  if (discussion.app_id) return `appli ${discussion.app_id}`
  return 'sans canal'
}

function couleurPoint(discussion: Discussion): string {
  if (discussion.resolue) return 'bg-online'
  if (discussion.etiquettes.includes('bug')) return 'bg-danger'
  if (discussion.etiquettes.includes('question')) return 'bg-accent'
  return 'bg-warning'
}

export function CarteDiscussion({ discussion, nouvelle = false, onPartager }: CarteDiscussionProps) {
  const auteur = discussion.auteur
  const messages = discussion.nb_messages
  const difficulte = discussion.bug?.difficulte

  return (
    <li
      className={`flex flex-col gap-2 rounded-carte border px-4 py-3.5 ${
        nouvelle ? 'border-[#BFD0FA] bg-nav/40' : 'border-white/60 bg-[rgba(246,243,237,0.78)]'
      }`}
    >
      <div className="flex flex-wrap items-center gap-x-2.5 gap-y-1.5 text-[13px] text-muted">
        <span className={`size-2 rounded-full ${couleurPoint(discussion)}`} aria-hidden="true" />
        {auteur.username ? (
          <Link
            to={`/devs/${auteur.username}`}
            className="inline-flex min-h-cible items-center gap-1.5 font-semibold text-ink no-underline"
          >
            <Avatar nom={auteur.nom} username={auteur.username} avatarUrl={auteur.avatar_url} />
            {nomAuteur(discussion)}
          </Link>
        ) : (
          <span className="inline-flex min-h-cible items-center gap-1.5 font-semibold text-ink">
            <Avatar nom={auteur.nom} username={auteur.username} avatarUrl={auteur.avatar_url} />
            {nomAuteur(discussion)}
          </span>
        )}
        <span aria-hidden="true">·</span>
        <span className="font-mono text-[12.5px] text-ink">{lieu(discussion)}</span>
        <span aria-hidden="true">·</span>
        <time dateTime={discussion.cree_le}>{ilYA(discussion.cree_le)}</time>
        <span className="ml-auto flex flex-wrap gap-1.5">
          {nouvelle ? <Badge ton="nouveau">Nouveau</Badge> : null}
          {discussion.analyse_ia ? <Badge ton="ia">Analyse IA</Badge> : null}
          {discussion.resolue ? <Badge ton="resolue">Résolue</Badge> : null}
        </span>
      </div>
      <h3 className="m-0 text-lg font-bold leading-snug">
        <Link to={`/echange/${discussion.id}`} className="text-ink no-underline hover:text-accent">
          {discussion.titre}
        </Link>
      </h3>
      <p className="m-0 line-clamp-3 text-subtle">{discussion.texte}</p>
      <div className="flex flex-wrap gap-1.5">
        {discussion.etiquettes.map((etiquette) => (
          <Badge key={etiquette} ton={etiquette}>
            {libelles[etiquette]}
          </Badge>
        ))}
        {difficulte ? (
          <Badge ton={difficulte}>
            <span className="sr-only">Difficulté : </span>
            {difficulte}
          </Badge>
        ) : null}
      </div>
      <div className="flex flex-wrap items-center gap-2">
        <Link
          to={`/echange/${discussion.id}`}
          className="inline-flex min-h-cible items-center gap-1.5 rounded-full bg-[#EEF2F9] px-3 text-sm font-semibold text-ink no-underline"
          aria-label={messages > 1 ? `${messages} messages` : `${messages} message`}
        >
          {messages}
        </Link>
        {discussion.demo_url ? (
          <a
            href={discussion.demo_url}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex min-h-cible items-center rounded-full bg-[#EEF2F9] px-3 text-sm font-semibold text-accent no-underline"
          >
            Démo
          </a>
        ) : null}
        <button
          type="button"
          onClick={() => onPartager(discussion)}
          className="inline-flex min-h-cible items-center rounded-full bg-[#EEF2F9] px-3 text-sm font-semibold text-ink"
        >
          Partager
        </button>
      </div>
    </li>
  )
}
