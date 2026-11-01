import { Link } from 'react-router'
import { ExternalLink, MessageSquare, Share2 } from 'lucide-react'
import { Avatar, Badge, cx } from '../../ui'
import type { Discussion } from './api'
import { libelleDifficulte, tonDifficulte, tonEtiquette } from './presentation'
import { ilYA, nomAuteur } from './texte'

type CarteDiscussionProps = {
  discussion: Discussion
  nouvelle?: boolean
  onPartager: (discussion: Discussion) => void
}

/** Aperçu du texte : le Markdown sans ses marques (blocs de code, accents graves, titres). */
function apercu(texte: string): string {
  return texte
    .replace(/```[\s\S]*?```/g, ' [code] ')
    .replace(/[`*_#>]/g, '')
    .replace(/\s+/g, ' ')
    .trim()
}

const action =
  'relative z-[1] inline-flex h-11 items-center gap-1.5 rounded-moyen px-2.5 text-petit font-medium text-texte-doux transition-colors duration-150 hover:bg-surface-douce hover:text-titre tablette:h-8'

/** Une discussion du fil : le titre couvre la carte ; auteur, messages, démo et partage restent cliquables. */
export function CarteDiscussion({ discussion, nouvelle = false, onPartager }: CarteDiscussionProps) {
  const auteur = discussion.auteur
  const messages = discussion.nb_messages
  const difficulte = discussion.bug?.difficulte
  const nom = nomAuteur(auteur.nom, auteur.username)

  return (
    <li
      data-nouvelle={nouvelle ? 'true' : 'false'}
      className={cx(
        'group relative flex flex-col gap-3 rounded-moyen border bg-surface p-4 shadow-1 transition-[box-shadow,border-color] duration-200 ease-souk hover:border-bordure-champ hover:shadow-2 has-[h2_a:focus-visible]:outline-3 has-[h2_a:focus-visible]:outline-offset-2 has-[h2_a:focus-visible]:outline-lien tablette:p-5',
        nouvelle ? 'border-primaire' : 'border-bordure',
      )}
    >
      <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-petit text-texte-doux">
        {auteur.username ? (
          <Link to={`/devs/${auteur.username}`} className="relative z-[1] inline-flex items-center gap-2 font-medium text-titre no-underline hover:underline">
            <Avatar nom={nom} url={auteur.avatar_url} taille="xs" />
            <span className="break-all">{nom}</span>
          </Link>
        ) : (
          <span className="inline-flex items-center gap-2 font-medium text-titre">
            <Avatar nom={nom} url={auteur.avatar_url} taille="xs" />
            <span className="break-all">{nom}</span>
          </span>
        )}
        <span aria-hidden="true">·</span>
        {discussion.canal ? (
          <span className="font-mono break-all">#{discussion.canal}</span>
        ) : discussion.app_id ? (
          <Link to={`/store/${discussion.app_id}`} className="relative z-[1]">
            Sur une appli
          </Link>
        ) : (
          <span>Sans canal</span>
        )}
        <span aria-hidden="true">·</span>
        <time dateTime={discussion.cree_le}>{ilYA(discussion.cree_le)}</time>
        <span className="ml-auto flex flex-wrap gap-1.5">
          {nouvelle ? <Badge ton="marque">Nouveau</Badge> : null}
          {discussion.analyse_ia ? <Badge ton="ia">Analyse IA</Badge> : null}
          {discussion.resolue ? <Badge ton="succes">Résolue</Badge> : null}
        </span>
      </div>
      <div className="flex flex-col gap-1">
        <h2 className="m-0 text-titre-3 break-words">
          <Link to={`/echange/${discussion.id}`} className="text-titre no-underline after:absolute after:inset-0 after:content-[''] focus-visible:outline-none">
            {discussion.titre}
          </Link>
        </h2>
        <p className="m-0 line-clamp-2 text-corps break-words text-texte">{apercu(discussion.texte)}</p>
      </div>
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="flex flex-wrap gap-1.5">
          {discussion.etiquettes.map((etiquette) => (
            <Badge key={etiquette} ton={tonEtiquette[etiquette]}>
              {etiquette}
            </Badge>
          ))}
          {difficulte ? (
            <Badge ton={tonDifficulte[difficulte]}>
              <span className="pour-lecteur">Difficulté : </span>
              {libelleDifficulte[difficulte]}
            </Badge>
          ) : null}
        </div>
        <div className="-mr-2 flex items-center">
          <span className={cx(action, 'pointer-events-none')}>
            <MessageSquare aria-hidden="true" className="size-4" />
            <span className="chiffres">{messages}</span>
            <span className="pour-lecteur">{messages > 1 ? ' messages' : ' message'}</span>
          </span>
          {discussion.demo_url ? (
            <a href={discussion.demo_url} target="_blank" rel="noopener noreferrer" className={cx(action, 'text-lien')}>
              Démo
              <ExternalLink aria-hidden="true" className="size-3.5" />
              <span className="pour-lecteur"> (nouvel onglet)</span>
            </a>
          ) : null}
          <button type="button" onClick={() => onPartager(discussion)} className={action}>
            <Share2 aria-hidden="true" className="size-4" />
            Partager
          </button>
        </div>
      </div>
    </li>
  )
}
