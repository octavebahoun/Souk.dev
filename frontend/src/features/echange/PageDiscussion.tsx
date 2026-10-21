import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useParams, useSearchParams } from 'react-router'
import { Avatar } from '../../shared/ui/Avatar'
import { Badge } from '../../shared/ui/Badge'
import { modifierDiscussion, estIntrouvable, lireDiscussion, lireMoi, messageAction, type Discussion } from './api'
import { CadreDemo } from './CadreDemo'
import { ColonneCorrectifs } from './ColonneCorrectifs'
import { FilMessages } from './FilMessages'
import { ilYA, nomAuteur } from './texte'
import { useDiscussionDirect } from './tempsReel'

const boutonAccent =
  'rebond inline-flex min-h-cible items-center justify-center rounded-controle bg-accent px-4 font-semibold text-canvas hover:bg-accent-hover disabled:opacity-60'
const boutonSecond =
  'inline-flex min-h-cible items-center justify-center rounded-controle border border-line bg-canvas px-4 font-semibold text-ink'

function lirePage(params: URLSearchParams, cle: string): number {
  const valeur = Number(params.get(cle))
  return Number.isInteger(valeur) && valeur > 1 ? valeur : 1
}

function suiviActif(discussion: Discussion | undefined): number | false {
  if (!discussion) return false
  const etat = discussion.copie_test?.etat
  const copie = etat === 'en_file' || etat === 'construction' || etat === 'demarrage'
  return copie || discussion.analyse_ia?.statut === 'en_cours' ? 4000 : false
}

export function PageDiscussion() {
  const brut = useParams().id
  const id = Number(brut)
  const valide = Number.isInteger(id) && id > 0
  const [params, setParams] = useSearchParams()
  const client = useQueryClient()
  const discussion = useQuery({
    queryKey: ['discussion', id],
    queryFn: () => lireDiscussion(id),
    enabled: valide,
    refetchInterval: (requete) => suiviActif(requete.state.data),
  })
  const moi = useQuery({ queryKey: ['moi'], queryFn: lireMoi })
  const supprimee = useDiscussionDirect(id, valide)
  const marquer = useMutation({
    mutationFn: (resolue: boolean) => modifierDiscussion(id, { resolue }),
    onSuccess: (recue) => client.setQueryData(['discussion', id], recue),
  })

  function changerPage(cle: 'messages' | 'correctifs', page: number) {
    const suivant = new URLSearchParams(params)
    if (page <= 1) suivant.delete(cle)
    else suivant.set(cle, String(page))
    setParams(suivant)
  }

  if (!valide) {
    return (
      <main className="mx-auto max-w-[1200px] px-4 py-7">
        <p>Cette discussion n’existe pas.</p>
        <Link to="/echange" className={boutonSecond}>
          Retour à l’échange
        </Link>
      </main>
    )
  }

  if (supprimee) {
    return (
      <main className="mx-auto max-w-[1200px] px-4 py-7">
        <p role="status">Cette discussion a été supprimée.</p>
        <Link to="/echange">Retour à l’échange</Link>
      </main>
    )
  }

  return (
    <main className="mx-auto flex max-w-[1200px] flex-col gap-6 overflow-x-clip px-4 py-7 min-[761px]:px-6">
      {discussion.isPending ? (
        <div role="status" aria-label="Chargement de la discussion" className="flex flex-col gap-4">
          <div className="squelette h-10 w-2/3 max-w-xl rounded-controle" />
          <div className="squelette h-64 rounded-carte" />
          <div className="squelette h-40 rounded-carte" />
        </div>
      ) : null}
      {discussion.isError ? (
        estIntrouvable(discussion.error) ? (
          <div className="flex flex-col gap-3">
            <p className="m-0">Cette discussion n’existe pas.</p>
            <Link to="/echange" className={boutonSecond}>
              Retour à l’échange
            </Link>
          </div>
        ) : (
          <div className="flex flex-col items-start gap-3">
            <p role="alert" className="m-0">
              La discussion n’a pas pu être chargée.
            </p>
            <button type="button" onClick={() => discussion.refetch()} className={boutonAccent}>
              Réessayer
            </button>
          </div>
        )
      ) : null}
      {discussion.isSuccess ? (
        <DiscussionChargee
          discussion={discussion.data}
          auteur={moi.data?.id === discussion.data.auteur.id}
          pageMessages={lirePage(params, 'messages')}
          pageCorrectifs={lirePage(params, 'correctifs')}
          onPage={changerPage}
          enCours={marquer.isPending}
          erreur={marquer.error ? messageAction(marquer.error, 'L’action a échoué.') : null}
          onResolue={(resolue) => marquer.mutate(resolue)}
        />
      ) : null}
    </main>
  )
}

function DiscussionChargee({
  discussion,
  auteur,
  pageMessages,
  pageCorrectifs,
  onPage,
  enCours,
  erreur,
  onResolue,
}: {
  discussion: Discussion
  auteur: boolean
  pageMessages: number
  pageCorrectifs: number
  onPage: (cle: 'messages' | 'correctifs', page: number) => void
  enCours: boolean
  erreur: string | null
  onResolue: (resolue: boolean) => void
}) {
  const lieu = discussion.canal ? `#${discussion.canal}` : discussion.app_id ? `appli ${discussion.app_id}` : 'Échange'

  return (
    <>
      <p className="m-0 text-sm text-muted">
        <Link to="/echange" className="text-accent">
          Échange
        </Link>
        <span aria-hidden="true"> · </span>
        {lieu}
      </p>
      <div className="flex flex-wrap items-start justify-between gap-4">
        <h1 className="m-0 max-w-[40rem] text-[30px] leading-[1.2] font-bold tracking-tight break-words">{discussion.titre}</h1>
        {auteur && !discussion.resolue ? (
          <button type="button" onClick={() => onResolue(true)} disabled={enCours} className={`${boutonSecond} rebond`}>
            Marquer comme résolue
          </button>
        ) : null}
        {auteur && discussion.resolue ? (
          <button type="button" onClick={() => onResolue(false)} disabled={enCours} className={boutonSecond}>
            Rouvrir
          </button>
        ) : null}
      </div>
      {erreur ? (
        <p role="alert" className="m-0 text-sm text-danger">
          {erreur}
        </p>
      ) : null}
      <div className="flex flex-wrap items-center gap-2 text-sm">
        <Avatar nom={discussion.auteur.nom} username={discussion.auteur.username} avatarUrl={discussion.auteur.avatar_url} taille="moyen" />
        {discussion.auteur.username ? (
          <Link to={`/devs/${discussion.auteur.username}`} className="font-semibold text-ink no-underline">
            {nomAuteur(discussion.auteur.nom, discussion.auteur.username)}
          </Link>
        ) : (
          <span className="font-semibold">{nomAuteur(discussion.auteur.nom, discussion.auteur.username)}</span>
        )}
        <time dateTime={discussion.cree_le}>{ilYA(discussion.cree_le)}</time>
        <span className="text-muted">{discussion.nb_messages} messages</span>
        {discussion.etiquettes.map((etiquette) => (
          <Badge key={etiquette} ton={etiquette}>
            {etiquette}
          </Badge>
        ))}
        {discussion.resolue ? <Badge ton="resolue">Résolue</Badge> : null}
      </div>

      <div className="flex flex-col items-start gap-6 min-[761px]:flex-row">
        <div className="flex w-full min-w-0 flex-col gap-6 min-[761px]:w-auto min-[761px]:flex-[999] min-[761px]:basis-[560px]">
          <CadreDemo discussion={discussion} auteur={auteur} />
          <section aria-labelledby="titre-probleme" className="flex flex-col gap-3.5">
            <h2 id="titre-probleme" className="m-0 text-[17px] font-bold">
              Le problème
            </h2>
            <p className="m-0 wrap-break-word whitespace-pre-wrap">{discussion.texte}</p>
            {discussion.bug ? (
              <div className="verre flex flex-col gap-3.5 rounded-carte p-5">
                <div className="flex flex-wrap items-center gap-2">
                  <h3 className="m-0 mr-auto text-[15px] font-bold">Fiche du bug</h3>
                  {discussion.bug.techno ? <Badge ton="entraide">{discussion.bug.techno}</Badge> : null}
                  {discussion.bug.difficulte ? <Badge ton={discussion.bug.difficulte}>Difficulté : {discussion.bug.difficulte}</Badge> : null}
                </div>
                <dl className="m-0 grid gap-3 sm:grid-cols-2">
                  <div className="rounded-controle bg-danger-bg p-3">
                    <dt className="text-[12.5px] font-bold tracking-wide text-danger uppercase">Erreur obtenue</dt>
                    <dd className="m-0 text-sm">{discussion.bug.erreur_obtenue}</dd>
                  </div>
                  <div className="rounded-controle bg-success-bg p-3">
                    <dt className="text-[12.5px] font-bold tracking-wide text-success uppercase">Comportement attendu</dt>
                    <dd className="m-0 text-sm">{discussion.bug.comportement_attendu}</dd>
                  </div>
                </dl>
                {discussion.bug.code ? (
                  <pre className="m-0 overflow-x-auto rounded-controle bg-ink p-3.5 font-mono text-[13px] leading-relaxed text-[#E2E8F0]">
                    {discussion.bug.code}
                  </pre>
                ) : null}
              </div>
            ) : null}
          </section>
          <FilMessages
            discussionId={discussion.id}
            auteurId={discussion.auteur.id}
            page={pageMessages}
            onPage={(page) => onPage('messages', page)}
          />
        </div>
        <ColonneCorrectifs
          discussion={discussion}
          auteur={auteur}
          page={pageCorrectifs}
          onPage={(page) => onPage('correctifs', page)}
        />
      </div>
    </>
  )
}
