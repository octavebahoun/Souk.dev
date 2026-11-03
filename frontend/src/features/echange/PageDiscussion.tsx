import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router'
import { CircleCheck, RotateCcw, Trash2 } from 'lucide-react'
import { useTitrePage } from '../../app/titre'
import { Alerte, Avatar, Badge, Bouton, Carte, EnTeteCarte, EtatErreur, EtatVide, FilAriane, LienBouton, Modale, Squelette, useToast } from '../../ui'
import { modifierDiscussion, estIntrouvable, lireDiscussion, lireMoi, messageAction, supprimerDiscussion, type Discussion } from './api'
import { CadreDemo } from './CadreDemo'
import { ColonneCorrectifs } from './ColonneCorrectifs'
import { FilMessages } from './FilMessages'
import { ilYA, nomAuteur } from './texte'
import { useDiscussionDirect } from './tempsReel'
import { TexteMarkdown } from './TexteMarkdown'
import { Signaler } from '../admin/Signaler'
import { libelleDifficulte, tonDifficulte, tonEtiquette } from './presentation'

const conteneur = 'mx-auto flex w-full max-w-site flex-col gap-6 px-4 py-8 tablette:px-6 bureau:px-8'

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
  const navigate = useNavigate()
  const toast = useToast()
  const [aSupprimer, setASupprimer] = useState(false)
  const suppression = useMutation({
    mutationFn: () => supprimerDiscussion(id),
    onSuccess: () => {
      setASupprimer(false)
      client.removeQueries({ queryKey: ['discussion', id] })
      void client.invalidateQueries({ queryKey: ['discussions'] })
      toast.succes('Discussion supprimée.')
      navigate('/echange')
    },
    onError: (erreur) => toast.erreur(messageAction(erreur, 'La discussion n’a pas pu être supprimée.')),
  })

  function changerPage(cle: 'messages' | 'correctifs', page: number) {
    const suivant = new URLSearchParams(params)
    if (page <= 1) suivant.delete(cle)
    else suivant.set(cle, String(page))
    setParams(suivant)
  }

  useTitrePage(discussion.data?.titre ?? 'Discussion')

  if (!valide || (discussion.isError && estIntrouvable(discussion.error))) {
    return (
      <div className={conteneur}>
        <EtatVide
          illustration="erreur-404"
          niveau={1}
          titre="Cette discussion n’existe pas"
          description="Elle a peut-être été supprimée, ou le lien est incomplet."
          action={<LienBouton to="/echange">Retour à l’Échange</LienBouton>}
        />
      </div>
    )
  }

  if (supprimee) {
    return (
      <div className={conteneur}>
        <EtatVide
          illustration="vide-discussions"
          niveau={1}
          titre="Cette discussion a été supprimée"
          description="Son auteur ou l’équipe de modération l’a retirée."
          action={<LienBouton to="/echange">Retour à l’Échange</LienBouton>}
        />
      </div>
    )
  }

  return (
    <div className={conteneur}>
      {discussion.isPending ? (
        <div role="status" aria-label="Chargement de la discussion" className="flex flex-col gap-4">
          <Squelette className="h-4 w-40" />
          <Squelette className="h-10 w-2/3 max-w-xl" />
          <Squelette className="h-64 rounded-moyen" />
          <Squelette className="h-40 rounded-moyen" />
        </div>
      ) : null}
      {discussion.isError ? <EtatErreur description="La discussion n’a pas pu être chargée." onReessayer={() => void discussion.refetch()} /> : null}
      {discussion.isSuccess ? (
        <DiscussionChargee
          discussion={discussion.data}
          auteur={moi.data?.id === discussion.data.auteur.id}
          moderateur={moi.data?.est_admin === true}
          onSupprimer={() => setASupprimer(true)}
          connecte={moi.data != null}
          connecteId={moi.data?.id ?? null}
          pageMessages={lirePage(params, 'messages')}
          pageCorrectifs={lirePage(params, 'correctifs')}
          onPage={changerPage}
          enCours={marquer.isPending}
          erreur={marquer.error ? messageAction(marquer.error, 'L’action a échoué.') : null}
          onResolue={(resolue) => marquer.mutate(resolue)}
        />
      ) : null}
      <Modale
        ouverte={aSupprimer}
        onFermer={() => setASupprimer(false)}
        taille="petite"
        titre="Supprimer cette discussion ?"
        description="Elle disparaît de l’Échange avec ses messages et ses correctifs. Cette action est définitive."
        pied={
          <>
            <Bouton variante="contour" onClick={() => setASupprimer(false)}>
              Garder
            </Bouton>
            <Bouton variante="danger" chargement={suppression.isPending} onClick={() => suppression.mutate()}>
              Supprimer
            </Bouton>
          </>
        }
      />
    </div>
  )
}

function DiscussionChargee({
  discussion,
  auteur,
  moderateur,
  onSupprimer,
  connecte,
  connecteId,
  pageMessages,
  pageCorrectifs,
  onPage,
  enCours,
  erreur,
  onResolue,
}: {
  discussion: Discussion
  auteur: boolean
  /** Compte de l'équipe : il supprime une discussion ou un message signalé. */
  moderateur: boolean
  onSupprimer: () => void
  connecte: boolean
  connecteId: number | null
  pageMessages: number
  pageCorrectifs: number
  onPage: (cle: 'messages' | 'correctifs', page: number) => void
  enCours: boolean
  erreur: string | null
  onResolue: (resolue: boolean) => void
}) {
  const nom = nomAuteur(discussion.auteur.nom, discussion.auteur.username)
  const bug = discussion.bug

  return (
    <>
      <FilAriane
        miettes={[
          { libelle: 'Échange', vers: '/echange' },
          discussion.canal
            ? { libelle: `#${discussion.canal}`, vers: `/echange?canal=${discussion.canal}` }
            : discussion.app_id
              ? { libelle: 'Fiche de l’appli', vers: `/store/${discussion.app_id}` }
              : { libelle: 'Sans canal' },
          { libelle: discussion.titre },
        ]}
      />
      <header className="flex flex-col gap-4">
        <div className="flex flex-col gap-4 tablette:flex-row tablette:items-start tablette:justify-between">
          <h1 className="m-0 max-w-3xl text-titre-1 break-words text-titre">{discussion.titre}</h1>
          {auteur || moderateur ? (
            <div className="flex shrink-0 flex-wrap gap-2">
              {auteur ? (
                discussion.resolue ? (
                  <Bouton variante="contour" icone={<RotateCcw aria-hidden="true" className="size-4" />} chargement={enCours} onClick={() => onResolue(false)}>
                    Rouvrir
                  </Bouton>
                ) : (
                  <Bouton variante="contour" icone={<CircleCheck aria-hidden="true" className="size-4" />} chargement={enCours} onClick={() => onResolue(true)}>
                    Marquer comme résolue
                  </Bouton>
                )
              ) : null}
              <Bouton variante="retrait" className="tablette:ml-3" icone={<Trash2 aria-hidden="true" className="size-4" />} onClick={onSupprimer}>
                Supprimer
              </Bouton>
            </div>
          ) : null}
        </div>
        <div className="flex flex-wrap items-center gap-x-3 gap-y-2 text-petit text-texte-doux">
          <span className="flex items-center gap-2">
            <Avatar nom={nom} url={discussion.auteur.avatar_url} taille="sm" />
            {discussion.auteur.username ? (
              <Link to={`/devs/${discussion.auteur.username}`} className="font-medium text-titre no-underline hover:underline">
                {nom}
              </Link>
            ) : (
              <span className="font-medium text-titre">{nom}</span>
            )}
          </span>
          <time dateTime={discussion.cree_le}>{ilYA(discussion.cree_le)}</time>
          <span>
            {discussion.nb_messages} message{discussion.nb_messages > 1 ? 's' : ''}
          </span>
          <span className="flex flex-wrap gap-1.5">
            {discussion.etiquettes.map((etiquette) => (
              <Badge key={etiquette} ton={tonEtiquette[etiquette]}>
                {etiquette}
              </Badge>
            ))}
            {discussion.resolue ? <Badge ton="succes">Résolue</Badge> : null}
          </span>
          {connecte && !auteur ? (
            <span className="ml-auto">
              <Signaler cible="discussion" cibleId={discussion.id} etiquette="cette discussion" />
            </span>
          ) : null}
        </div>
        {erreur ? <Alerte ton="danger">{erreur}</Alerte> : null}
      </header>

      <div className="flex flex-col gap-6 bureau:grid bureau:grid-cols-[minmax(0,1fr)_22rem] bureau:items-start bureau:gap-8">
        <div className="flex min-w-0 flex-col gap-6">
          <Carte as="section" aria-labelledby="titre-probleme" className="flex flex-col gap-4">
            <EnTeteCarte titre={<span id="titre-probleme">Le problème</span>} />
            <TexteMarkdown texte={discussion.texte} />
            {bug ? (
              <div className="flex flex-col gap-3 border-t border-bordure pt-4">
                <div className="flex flex-wrap items-center gap-2">
                  <h3 className="m-0 mr-auto text-corps font-semibold text-titre">Fiche du bug</h3>
                  {bug.techno ? <Badge>{bug.techno}</Badge> : null}
                  {bug.difficulte ? <Badge ton={tonDifficulte[bug.difficulte]}>Difficulté : {libelleDifficulte[bug.difficulte].toLowerCase()}</Badge> : null}
                </div>
                <dl className="m-0 grid gap-3 tablette:grid-cols-2">
                  <div className="flex flex-col gap-1 rounded-moyen border border-bordure-danger bg-fond-danger p-3">
                    <dt className="text-etiquette text-danger uppercase">Erreur obtenue</dt>
                    <dd className="m-0 font-mono text-petit break-words text-titre">{bug.erreur_obtenue}</dd>
                  </div>
                  <div className="flex flex-col gap-1 rounded-moyen border border-bordure-succes bg-fond-succes p-3">
                    <dt className="text-etiquette text-succes uppercase">Comportement attendu</dt>
                    <dd className="m-0 text-petit break-words text-titre">{bug.comportement_attendu}</dd>
                  </div>
                </dl>
                {bug.code ? (
                  <pre tabIndex={0} className="m-0 overflow-x-auto rounded-moyen bg-nuit-profonde p-3 font-mono text-petit leading-relaxed text-sur-couleur">
                    {bug.code}
                  </pre>
                ) : null}
              </div>
            ) : null}
          </Carte>
          <CadreDemo discussion={discussion} auteur={auteur} />
          <FilMessages
            discussionId={discussion.id}
            auteurId={discussion.auteur.id}
            page={pageMessages}
            onPage={(page) => onPage('messages', page)}
            connecteId={connecteId}
            moderateur={moderateur}
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
