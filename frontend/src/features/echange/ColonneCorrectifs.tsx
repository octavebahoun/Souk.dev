import { useCallback, useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ExternalLink, GitPullRequestArrow } from 'lucide-react'
import { useSelonCompte } from '../../app/compte'
import { ModaleConnexion } from '../../app/ModaleConnexion'
import { Alerte, Avatar, Badge, Bouton, Carte, Champ, EtatErreur, Modale, Pagination, Saisie, Squelette, ZoneTexte, classesBouton, cx } from '../../ui'
import {
  accepterCorrectif,
  creerCorrectif,
  deployerCorrectif,
  lireErreurHttp,
  lireMoi,
  listerCorrectifs,
  messageAction,
  type Correctif,
  type Discussion,
} from './api'
import { ilYA, nomAuteur } from './texte'
import { AnalyseSoukAi } from './AnalyseSoukAi'

function urlHttp(valeur: string): boolean {
  try {
    const lue = new URL(valeur)
    return lue.protocol === 'http:' || lue.protocol === 'https:'
  } catch {
    return false
  }
}

type ColonneCorrectifsProps = {
  discussion: Discussion
  auteur: boolean
  page: number
  onPage: (page: number) => void
}

export function ColonneCorrectifs({ discussion, auteur, page, onPage }: ColonneCorrectifsProps) {
  const client = useQueryClient()
  const [proposer, setProposer] = useState(false)
  const [connexion, setConnexion] = useState(false)
  const [aAccepter, setAAccepter] = useState<Correctif | null>(null)
  const [depot, setDepot] = useState('')
  const [description, setDescription] = useState('')
  const [demo, setDemo] = useState('')
  const [champs, setChamps] = useState<Record<string, string>>({})
  const [erreur, setErreur] = useState<string | null>(null)
  const fermerProposition = useCallback(() => setProposer(false), [])
  const fermerAcceptation = useCallback(() => setAAccepter(null), [])
  const moi = useQuery({ queryKey: ['moi'], queryFn: lireMoi })
  const selonCompte = useSelonCompte()
  const correctifs = useQuery({
    queryKey: ['correctifs', discussion.id, page],
    queryFn: () => listerCorrectifs(discussion.id, page),
    // Une version corrigée en construction se relit jusqu'à son état final, même si un
    // événement temps réel est parti avant l'abonnement de la page.
    refetchInterval: (requete) =>
      requete.state.data?.data.some((correctif) => ['en_file', 'construction', 'demarrage', 'arret_en_cours'].includes(correctif.deploiement?.etat ?? '')) ? 4000 : false,
  })
  const dejaAccepte = correctifs.data?.data.some((correctif) => correctif.accepte) ?? discussion.resolue
  const analyse = discussion.analyse_ia
  const bug = discussion.etiquettes.includes('bug')

  function ranger(correctif: Correctif) {
    client.setQueryData<typeof correctifs.data>(['correctifs', discussion.id, page], (liste) => {
      if (!liste) return liste
      const existe = liste.data.some((item) => item.id === correctif.id)
      const data = existe
        ? liste.data.map((item) => (item.id === correctif.id ? correctif : item))
        : [correctif, ...liste.data]
      return { ...liste, data, meta: { ...liste.meta, total: existe ? liste.meta.total : liste.meta.total + 1 } }
    })
  }

  const creer = useMutation({
    mutationFn: () =>
      creerCorrectif(discussion.id, {
        depot_url: depot.trim(),
        description: description.trim() || undefined,
        demo_url: demo.trim() || undefined,
      }),
    onSuccess: async (correctif) => {
      ranger(correctif)
      setProposer(false)
      setDepot('')
      setDescription('')
      setDemo('')
      setChamps({})
      setErreur(null)
      await client.invalidateQueries({ queryKey: ['correctifs', discussion.id] })
    },
    onError: (recue) => {
      const lu = lireErreurHttp(recue, 'Le correctif n’a pas été envoyé.')
      setChamps(lu.champs)
      setErreur(messageAction(recue, '10 correctifs par heure.'))
    },
  })

  const accepter = useMutation({
    mutationFn: (id: number) => accepterCorrectif(id),
    onSuccess: async (correctif) => {
      ranger({ ...correctif, accepte: true })
      client.setQueryData<Discussion>(['discussion', discussion.id], (actuelle) =>
        actuelle ? { ...actuelle, resolue: true } : actuelle,
      )
      setAAccepter(null)
      setErreur(null)
      await client.invalidateQueries({ queryKey: ['correctifs', discussion.id] })
    },
    onError: (recue) => setErreur(messageAction(recue, '10 correctifs par heure.')),
  })

  function soumettre(evenement: FormEvent) {
    evenement.preventDefault()
    const suivants: Record<string, string> = {}
    if (!urlHttp(depot.trim())) suivants.depot_url = 'Indiquez une URL http ou https.'
    if (demo.trim() && !urlHttp(demo.trim())) suivants.demo_url = 'Indiquez une URL http ou https.'
    setChamps(suivants)
    if (Object.keys(suivants).length > 0) return
    creer.mutate()
  }

  const total = correctifs.data ? Math.max(correctifs.data.meta.total, correctifs.data.data.length) : 0

  return (
    <aside aria-labelledby="titre-correctifs" className="flex w-full min-w-0 flex-col gap-4 bureau:sticky bureau:top-24">
      <div className="flex items-baseline justify-between gap-2.5">
        <h2 id="titre-correctifs" className="m-0 text-titre-3 text-titre">
          Correctifs
        </h2>
        <span className="text-petit text-texte-doux">{correctifs.isSuccess ? `${total} proposé${total > 1 ? 's' : ''}` : ''}</span>
      </div>

      {correctifs.isPending ? (
        <div role="status" aria-label="Chargement des correctifs">
          <Squelette className="h-28 rounded-moyen" />
        </div>
      ) : null}
      {correctifs.isError ? <EtatErreur niveau={3} description="Les correctifs n’ont pas pu être chargés." onReessayer={() => void correctifs.refetch()} /> : null}
      {correctifs.isSuccess && correctifs.data.data.length === 0 ? (
        <p className="m-0 rounded-moyen border border-dashed border-bordure bg-surface p-4 text-petit text-texte-doux">Aucun correctif pour l’instant. Le premier qui propose sa branche aide l’auteur.</p>
      ) : null}
      {correctifs.data?.data.map((correctif) => (
        <CorrectifCarte
          key={correctif.id}
          correctif={correctif}
          peutAccepter={auteur && !dejaAccepte && !correctif.accepte}
          peutFusionner={auteur && correctif.accepte}
          moiId={moi.data?.id ?? null}
          onAccepter={() => setAAccepter(correctif)}
        />
      ))}

      {correctifs.data ? <Pagination libelle="Pages des correctifs" page={page} dernierePage={correctifs.data.meta.last_page} onChange={onPage} /> : null}

      <div className="flex flex-col gap-2">
        <Bouton variante="secondaire" pleineLargeur icone={<GitPullRequestArrow aria-hidden="true" className="size-4" />} onClick={() => void selonCompte(() => setProposer(true), () => setConnexion(true))}>
          Proposer un correctif
        </Bouton>
        <p className="m-0 text-petit text-texte-doux">Un correctif, c’est l’URL de votre branche. Un seul correctif peut être accepté par discussion.</p>
      </div>

      {bug ? <AnalyseSoukAi discussionId={discussion.id} analyse={analyse} auteur={auteur} /> : null}
      {erreur && !proposer && !aAccepter ? <Alerte ton="danger">{erreur}</Alerte> : null}

      <ModaleConnexion ouverte={connexion} onFermer={() => setConnexion(false)} action="proposer un correctif" />

      <Modale
        ouverte={proposer}
        onFermer={fermerProposition}
        titre="Proposer un correctif"
        description="L’auteur l’essaie, puis l’accepte s’il règle son problème. Souk.dev ne fusionne rien sur GitHub."
        pied={
          <>
            <Bouton variante="contour" onClick={fermerProposition}>
              Annuler
            </Bouton>
            <Bouton type="submit" form="proposer-correctif" chargement={creer.isPending}>
              Envoyer le correctif
            </Bouton>
          </>
        }
      >
        <form id="proposer-correctif" onSubmit={soumettre} className="flex flex-col gap-4" noValidate>
          <Champ libelle="URL de la branche" erreur={champs.depot_url}>
            {(liaison) => <Saisie {...liaison} value={depot} onChange={(evenement) => setDepot(evenement.target.value)} className="font-mono text-petit" placeholder="https://github.com/vous/projet/tree/correctif" data-autofocus />}
          </Champ>
          <Champ libelle="Description" facultatif erreur={champs.description}>
            {(liaison) => <ZoneTexte {...liaison} rows={3} value={description} onChange={(evenement) => setDescription(evenement.target.value)} />}
          </Champ>
          <Champ libelle="Démo corrigée" facultatif erreur={champs.demo_url}>
            {(liaison) => <Saisie {...liaison} value={demo} onChange={(evenement) => setDemo(evenement.target.value)} className="font-mono text-petit" placeholder="https://demo.exemple.bj" />}
          </Champ>
          {erreur ? <Alerte ton="danger">{erreur}</Alerte> : null}
        </form>
      </Modale>

      <Modale
        ouverte={aAccepter !== null}
        onFermer={fermerAcceptation}
        taille="petite"
        titre="Accepter ce correctif ?"
        description={aAccepter ? `Vous allez accepter le correctif de ${nomAuteur(aAccepter.auteur.nom, aAccepter.auteur.username)}. La discussion passe en résolue ; Souk.dev ne fusionne rien sur GitHub.` : undefined}
        pied={
          <>
            <Bouton variante="contour" onClick={fermerAcceptation}>
              Annuler
            </Bouton>
            <Bouton chargement={accepter.isPending} onClick={() => aAccepter && accepter.mutate(aAccepter.id)}>
              Accepter le correctif
            </Bouton>
          </>
        }
      >
        {aAccepter ? <p className="m-0 rounded-moyen bg-surface-douce p-2.5 font-mono text-petit break-all text-titre">{aAccepter.depot_url}</p> : null}
        {erreur ? <Alerte ton="danger">{erreur}</Alerte> : null}
      </Modale>
    </aside>
  )
}

const etatCopie = {
  attente_paiement: 'En attente de paiement',
  en_file: 'En file',
  construction: 'Construction',
  demarrage: 'Démarrage',
  en_ligne: 'En ligne',
  echec: 'Échec',
  arret_en_cours: 'Arrêt en cours',
  suspendu: 'Suspendue',
  mise_a_jour: 'Mise à jour',
  arrete: 'Arrêtée',
} as const

function CorrectifCarte({
  correctif,
  peutAccepter,
  peutFusionner,
  moiId,
  onAccepter,
}: {
  correctif: Correctif
  peutAccepter: boolean
  peutFusionner: boolean
  moiId: number | null
  onAccepter: () => void
}) {
  const cache = useQueryClient()
  const [erreurLancement, setErreurLancement] = useState<string | null>(null)
  const lancer = useMutation({
    mutationFn: () => deployerCorrectif(correctif.id),
    onSuccess: () => {
      setErreurLancement(null)
      void cache.invalidateQueries({ queryKey: ['correctifs', correctif.discussion_id] })
    },
    onError: (erreur) => setErreurLancement(messageAction(erreur, '3 déploiements par heure. Réessayez plus tard.')),
  })
  // L'état vient des données relues (temps réel ou relecture) ; la réponse du lancement sert
  // seulement le temps que la liste se relise.
  const deploiement = correctif.deploiement ?? lancer.data ?? null
  const mien = moiId !== null && moiId === correctif.auteur.id

  const nom = nomAuteur(correctif.auteur.nom, correctif.auteur.username)

  return (
    <Carte as="article" className={cx('flex flex-col gap-3', correctif.accepte && 'border-bordure-succes')}>
      <div className="flex flex-wrap items-center gap-2 text-petit text-texte-doux">
        <Avatar nom={nom} url={correctif.auteur.avatar_url} taille="xs" />
        {correctif.auteur.username ? (
          <Link to={`/devs/${correctif.auteur.username}`} className="font-medium text-titre no-underline hover:underline">
            {nom}
          </Link>
        ) : (
          <span className="font-medium text-titre">{nom}</span>
        )}
        <time dateTime={correctif.cree_le}>{ilYA(correctif.cree_le)}</time>
        {correctif.accepte ? <Badge ton="succes">Accepté</Badge> : null}
      </div>
      <a href={correctif.depot_url} target="_blank" rel="noreferrer" className="font-mono text-petit break-all">
        {correctif.depot_url}
        <span className="pour-lecteur"> (nouvel onglet)</span>
      </a>
      {correctif.description ? <p className="m-0 text-petit wrap-break-word whitespace-pre-wrap text-texte">{correctif.description}</p> : null}
      <div className="flex flex-wrap items-center gap-2">
        {correctif.demo_url ? (
          <a href={correctif.demo_url} target="_blank" rel="noreferrer" className={classesBouton('contour', 'sm')}>
            Voir la démo corrigée
            <ExternalLink aria-hidden="true" className="size-3.5" />
            <span className="pour-lecteur"> (nouvel onglet)</span>
          </a>
        ) : (
          <span className="text-petit text-texte-doux">Pas de démo</span>
        )}
        {peutAccepter ? (
          <Bouton taille="sm" onClick={onAccepter}>
            Accepter
          </Bouton>
        ) : null}
        {mien && !deploiement ? (
          <Bouton taille="sm" variante="secondaire" chargement={lancer.isPending} onClick={() => lancer.mutate()}>
            Lancer ma version corrigée
          </Bouton>
        ) : null}
      </div>
      {erreurLancement ? <Alerte ton="danger">{erreurLancement}</Alerte> : null}
      {deploiement ? (
        <p className="m-0 text-petit text-texte">
          Version corrigée : {etatCopie[deploiement.etat]}
          {deploiement.etat === 'echec' && deploiement.erreur ? ` — ${deploiement.erreur}` : null}
          {deploiement.url ? (
            <>
              {' '}
              <a href={deploiement.url} className="underline underline-offset-2">
                {deploiement.url}
              </a>
            </>
          ) : null}
        </p>
      ) : null}
      {peutFusionner ? (
        <div className="flex flex-col gap-2 border-t border-bordure pt-3">
          <a href={correctif.depot_url} target="_blank" rel="noreferrer" className={classesBouton('primaire', 'md', true)}>
            Fusionner sur GitHub
            <ExternalLink aria-hidden="true" className="size-4" />
            <span className="pour-lecteur"> (nouvel onglet)</span>
          </a>
          <span className="text-petit text-texte-doux">Ouvre la branche sur GitHub. Souk.dev ne fusionne rien à votre place.</span>
        </div>
      ) : null}
    </Carte>
  )
}
