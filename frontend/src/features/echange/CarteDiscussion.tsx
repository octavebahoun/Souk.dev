import { Link } from 'react-router'
import { Avatar } from '../../shared/ui/Avatar'
import { Badge } from '../../shared/ui/Badge'
import type { Discussion, Etiquette } from './api'
import { ilYA, nomAuteur } from './texte'

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

function lieu(discussion: Discussion): string {
  if (discussion.canal) return `#${discussion.canal}`
  if (discussion.app_id) return `appli ${discussion.app_id}`
  return 'sans canal'
}

function couleurPoint(discussion: Discussion): string {
  if (discussion.resolue) return 'text-online'
  if (discussion.etiquettes.includes('bug')) return 'text-dot-bug'
  if (discussion.etiquettes.includes('question')) return 'text-accent'
  return 'text-dot-autre'
}

const zone = 'action relative z-[1] inline-flex min-h-cible items-center bg-transparent p-0'
const pilule =
  'inline-flex h-9 items-center gap-1.5 rounded-full bg-chip px-3 text-[13.5px] font-semibold text-nav-texte group-hover:bg-chip-hover group-hover:text-ink'

export function CarteDiscussion({ discussion, nouvelle = false, onPartager }: CarteDiscussionProps) {
  const auteur = discussion.auteur
  const messages = discussion.nb_messages
  const difficulte = discussion.bug?.difficulte

  return (
    <li
      data-nouvelle={nouvelle ? 'true' : 'false'}
      className={`carte-disc flex flex-col gap-2 rounded-carte border px-[18px] py-3.5 ${
        nouvelle ? 'border-banner-line bg-nav/40' : 'border-white/60 bg-[rgba(246,243,237,0.78)]'
      }`}
    >
      <span className={`etat ${couleurPoint(discussion)}`} aria-hidden="true" />
      <div className="flex flex-wrap items-center gap-x-2.5 gap-y-1.5 text-[13px] text-muted">
        {auteur.username ? (
          <Link to={`/devs/${auteur.username}`} className="relative z-[1] inline-flex min-h-cible items-center gap-1.5 font-semibold text-ink no-underline hover:text-accent hover:underline">
            <Avatar nom={auteur.nom} username={auteur.username} avatarUrl={auteur.avatar_url} />
            <span className="break-all">{nomAuteur(auteur.nom, auteur.username)}</span>
          </Link>
        ) : (
          <span className="inline-flex min-h-cible items-center gap-1.5 font-semibold text-ink">
            <Avatar nom={auteur.nom} username={auteur.username} avatarUrl={auteur.avatar_url} />
            <span className="break-all">{nomAuteur(auteur.nom, auteur.username)}</span>
          </span>
        )}
        <span aria-hidden="true">·</span>
        <span className="max-w-full font-mono text-[12.5px] break-all text-nav-texte">{lieu(discussion)}</span>
        <span aria-hidden="true">·</span>
        <time dateTime={discussion.cree_le}>{ilYA(discussion.cree_le)}</time>
        <span className="ml-auto flex flex-wrap gap-1.5">
          {nouvelle ? <Badge ton="nouveau">Nouveau</Badge> : null}
          {discussion.analyse_ia ? <Badge ton="ia">Analyse IA</Badge> : null}
          {discussion.resolue ? <Badge ton="resolue">Résolue</Badge> : null}
        </span>
      </div>
      <h3 className="m-0 text-lg leading-snug font-bold break-words">
        <Link to={`/echange/${discussion.id}`} className="lien-carte text-ink no-underline hover:text-accent">
          {discussion.titre}
        </Link>
      </h3>
      <p className="m-0 line-clamp-3 text-[14.5px] leading-normal break-words text-subtle">{discussion.texte}</p>
      <div className="flex flex-wrap gap-1.5">
        {discussion.etiquettes.map((etiquette) => (
          <Badge key={etiquette} ton={etiquette}>
            {libelles[etiquette]}
          </Badge>
        ))}
        {difficulte ? (
          <Badge ton={difficulte}>
            <span className="sr-only">Difficulté : </span>
            {difficulte === 'facile' ? 'Facile' : difficulte === 'moyen' ? 'Moyen' : 'Difficile'}
          </Badge>
        ) : null}
      </div>
      <div className="mt-0.5 flex flex-wrap items-center gap-2">
        <Link
          to={`/echange/${discussion.id}`}
          className={`${zone} group`}
          aria-label={messages > 1 ? `${messages} messages` : `${messages} message`}
        >
          <span className={pilule}>{messages}</span>
        </Link>
        {discussion.demo_url ? (
          <a href={discussion.demo_url} target="_blank" rel="noopener noreferrer" className={`${zone} group`}>
            <span className={`${pilule} text-accent`}>Démo</span>
          </a>
        ) : null}
        <button type="button" onClick={() => onPartager(discussion)} className={`${zone} group`}>
          <span className={pilule}>Partager</span>
        </button>
      </div>
    </li>
  )
}
