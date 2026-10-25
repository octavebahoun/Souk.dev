import { useCallback, useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Avatar } from '../../shared/ui/Avatar'
import { Badge } from '../../shared/ui/Badge'
import { Champ, classeChamp } from '../../shared/ui/Champ'
import { EmptyState } from '../../shared/ui/EmptyState'
import { Modal } from '../../shared/ui/Modal'
import {
  accepterCorrectif,
  creerCorrectif,
  demanderAnalyse,
  deployerCorrectif,
  lireErreurHttp,
  lireMoi,
  listerCorrectifs,
  messageAction,
  type AnalyseIa,
  type Correctif,
  type Discussion,
} from './api'
import { ilYA, nomAuteur } from './texte'

const boutonAccent =
  'inline-flex min-h-cible items-center justify-center rounded-controle bg-accent px-4 font-semibold text-canvas disabled:opacity-60'
const boutonSecond =
  'inline-flex min-h-cible items-center justify-center rounded-controle border border-line bg-canvas px-4 text-sm font-semibold text-ink'
const boutonIa =
  'inline-flex min-h-cible items-center justify-center rounded-controle bg-[#5B21B6] px-4 text-sm font-semibold text-canvas disabled:opacity-60'

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
  const [aAccepter, setAAccepter] = useState<Correctif | null>(null)
  const [demander, setDemander] = useState(false)
  const [depot, setDepot] = useState('')
  const [description, setDescription] = useState('')
  const [demo, setDemo] = useState('')
  const [champs, setChamps] = useState<Record<string, string>>({})
  const [erreur, setErreur] = useState<string | null>(null)
  const fermerProposition = useCallback(() => setProposer(false), [])
  const fermerAcceptation = useCallback(() => setAAccepter(null), [])
  const fermerAnalyse = useCallback(() => setDemander(false), [])
  const moi = useQuery({ queryKey: ['moi'], queryFn: lireMoi })
  const correctifs = useQuery({
    queryKey: ['correctifs', discussion.id, page],
    queryFn: () => listerCorrectifs(discussion.id, page),
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

  const analyseMutation = useMutation({
    mutationFn: () => demanderAnalyse(discussion.id),
    onSuccess: (recue) => {
      client.setQueryData<Discussion>(['discussion', discussion.id], (actuelle) =>
        actuelle ? { ...actuelle, analyse_ia: recue } : actuelle,
      )
      setDemander(false)
      setErreur(null)
    },
    onError: (recue) => setErreur(messageAction(recue, '3 analyses par jour.')),
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
    <aside aria-labelledby="titre-correctifs" className="flex w-full min-w-0 flex-col gap-3.5 min-[761px]:w-auto min-[761px]:flex-1 min-[761px]:basis-[300px]">
      <div className="flex items-baseline justify-between gap-2.5">
        <h2 id="titre-correctifs" className="m-0 text-[17px] font-bold">
          Correctifs
        </h2>
        <span className="text-sm text-muted">{correctifs.isSuccess ? `${total} proposé${total > 1 ? 's' : ''}` : ''}</span>
      </div>

      {correctifs.isPending ? (
        <div role="status" aria-label="Chargement des correctifs" className="squelette h-28 rounded-carte" />
      ) : null}
      {correctifs.isError ? (
        <button type="button" onClick={() => correctifs.refetch()} className={boutonAccent}>
          Réessayer les correctifs
        </button>
      ) : null}
      {correctifs.isSuccess && correctifs.data.data.length === 0 ? <EmptyState message="Aucun correctif pour l’instant." /> : null}
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

      {correctifs.data && correctifs.data.meta.last_page > 1 ? (
        <div className="flex flex-wrap gap-2">
          <button type="button" disabled={page <= 1} onClick={() => onPage(page - 1)} className={boutonSecond}>
            Page précédente
          </button>
          <button type="button" disabled={page >= correctifs.data.meta.last_page} onClick={() => onPage(page + 1)} className={boutonSecond}>
            Page suivante
          </button>
        </div>
      ) : null}

      <button type="button" onClick={() => setProposer(true)} className="min-h-cible rounded-controle border border-dashed border-[#A9B8DA] bg-canvas font-semibold text-accent">
        Proposer un correctif
      </button>
      <p className="m-0 text-[13px] text-muted">Un correctif, c’est l’URL de votre branche. Un seul correctif peut être accepté par discussion.</p>

      {bug ? <Analyse analyse={analyse} auteur={auteur} onDemander={() => setDemander(true)} /> : null}
      {erreur && !proposer && !demander && !aAccepter ? (
        <p role="alert" className="m-0 text-sm text-danger">
          {erreur}
        </p>
      ) : null}

      {proposer ? (
        <Modal titre="Proposer un correctif" onFermer={fermerProposition}>
          <form onSubmit={soumettre} className="flex flex-col gap-3">
            <Champ id="depot-correctif" label="URL de la branche" erreur={champs.depot_url}>
              <input id="depot-correctif" value={depot} onChange={(e) => setDepot(e.target.value)} aria-invalid={champs.depot_url ? true : undefined} aria-describedby={champs.depot_url ? 'depot-correctif-erreur' : undefined} className={classeChamp} />
            </Champ>
            <Champ id="description-correctif" label="Description" facultatif erreur={champs.description}>
              <textarea id="description-correctif" rows={3} value={description} onChange={(e) => setDescription(e.target.value)} className={`${classeChamp} py-2.5`} />
            </Champ>
            <Champ id="demo-correctif" label="Démo corrigée" facultatif erreur={champs.demo_url}>
              <input id="demo-correctif" value={demo} onChange={(e) => setDemo(e.target.value)} aria-invalid={champs.demo_url ? true : undefined} aria-describedby={champs.demo_url ? 'demo-correctif-erreur' : undefined} className={classeChamp} />
            </Champ>
            {erreur ? <p role="alert" className="m-0 text-sm text-danger">{erreur}</p> : null}
            <div className="flex flex-wrap justify-end gap-2">
              <button type="button" onClick={fermerProposition} className={boutonSecond}>Annuler</button>
              <button type="submit" disabled={creer.isPending} className={boutonAccent}>Envoyer le correctif</button>
            </div>
          </form>
        </Modal>
      ) : null}

      {aAccepter ? (
        <Modal titre="Accepter ce correctif ?" onFermer={fermerAcceptation}>
          <p className="m-0 text-subtle">
            Vous allez accepter le correctif de {nomAuteur(aAccepter.auteur.nom, aAccepter.auteur.username)}. Souk.dev ne fusionne rien sur GitHub.
          </p>
          <p className="m-0 break-all rounded-controle bg-page p-2.5 font-mono text-[13px]">{aAccepter.depot_url}</p>
          {erreur ? <p role="alert" className="m-0 text-sm text-danger">{erreur}</p> : null}
          <div className="flex flex-wrap justify-end gap-2">
            <button type="button" onClick={fermerAcceptation} className={boutonSecond}>Annuler</button>
            <button type="button" onClick={() => accepter.mutate(aAccepter.id)} disabled={accepter.isPending} className={boutonAccent}>
              Accepter le correctif
            </button>
          </div>
        </Modal>
      ) : null}

      {demander ? (
        <Modal titre="Demander l’avis de Souk AI" onFermer={fermerAnalyse}>
          <p className="m-0 text-subtle">
            Votre fiche et votre code partent chez un fournisseur extérieur (Claude, Anthropic). 3 analyses par jour. L’analyse ne résout pas la discussion.
          </p>
          {erreur ? <p role="alert" className="m-0 text-sm text-danger">{erreur}</p> : null}
          <div className="flex flex-wrap justify-end gap-2">
            <button type="button" onClick={fermerAnalyse} className={boutonSecond}>Annuler</button>
            <button type="button" onClick={() => analyseMutation.mutate()} disabled={analyseMutation.isPending} className={boutonIa}>
              Lancer l’analyse
            </button>
          </div>
        </Modal>
      ) : null}
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
  const [deploiement, setDeploiement] = useState(correctif.deploiement)
  const [erreurLancement, setErreurLancement] = useState<string | null>(null)
  const lancer = useMutation({
    mutationFn: () => deployerCorrectif(correctif.id),
    onSuccess: (recu) => {
      setDeploiement(recu)
      setErreurLancement(null)
    },
    onError: (erreur) => setErreurLancement(messageAction(erreur, '3 déploiements par heure. Réessayez plus tard.')),
  })
  const mien = moiId !== null && moiId === correctif.auteur.id

  return (
    <article className={`flex flex-col gap-2.5 rounded-carte border bg-canvas p-4 ${correctif.accepte ? 'border-success' : 'border-line'}`}>
      <div className="flex flex-wrap items-center gap-2 text-sm">
        <Avatar nom={correctif.auteur.nom} username={correctif.auteur.username} avatarUrl={correctif.auteur.avatar_url} />
        {correctif.auteur.username ? (
          <Link to={`/devs/${correctif.auteur.username}`} className="font-semibold text-ink no-underline">
            {nomAuteur(correctif.auteur.nom, correctif.auteur.username)}
          </Link>
        ) : (
          <span className="font-semibold">{nomAuteur(correctif.auteur.nom, correctif.auteur.username)}</span>
        )}
        <time dateTime={correctif.cree_le} className="text-muted">
          · {ilYA(correctif.cree_le)}
        </time>
        {correctif.accepte ? <Badge ton="resolue">Accepté</Badge> : null}
      </div>
      <a href={correctif.depot_url} target="_blank" rel="noreferrer" className="break-all font-mono text-[13px]">
        {correctif.depot_url}
      </a>
      {correctif.description ? <p className="m-0 text-sm wrap-break-word whitespace-pre-wrap text-subtle">{correctif.description}</p> : null}
      <div className="flex flex-wrap gap-2">
        {correctif.demo_url ? (
          <a href={correctif.demo_url} target="_blank" rel="noreferrer" className={boutonSecond}>
            Voir la démo corrigée
          </a>
        ) : (
          <span className="inline-flex min-h-cible items-center text-[13px] text-muted">Pas de démo</span>
        )}
        {peutAccepter ? (
          <button type="button" onClick={onAccepter} className={boutonAccent}>
            Accepter
          </button>
        ) : null}
        {mien && !deploiement ? (
          <button type="button" onClick={() => lancer.mutate()} disabled={lancer.isPending} className={boutonAccent}>
            Lancer ma version corrigée
          </button>
        ) : null}
      </div>
      {erreurLancement ? <p role="alert" className="m-0 text-sm text-danger">{erreurLancement}</p> : null}
      {deploiement ? (
        <p className="m-0 text-sm text-subtle">
          Version corrigée : {etatCopie[deploiement.etat]}
          {deploiement.etat === 'echec' && deploiement.erreur ? ` — ${deploiement.erreur}` : null}
          {deploiement.url ? (
            <>
              {' '}
              <a href={deploiement.url}>{deploiement.url}</a>
            </>
          ) : null}
        </p>
      ) : null}
      {peutFusionner ? (
        <div className="flex flex-col gap-2 border-t border-[#EEF2F9] pt-2.5">
          <a href={correctif.depot_url} target="_blank" rel="noreferrer" className="inline-flex min-h-cible items-center justify-center rounded-controle bg-ink px-4 text-sm font-semibold text-canvas">
            Fusionner sur GitHub
          </a>
          <span className="text-[12.5px] text-muted">Ouvre la branche sur GitHub. Souk.dev ne fusionne rien à votre place.</span>
        </div>
      ) : null}
    </article>
  )
}

function Analyse({
  analyse,
  auteur,
  onDemander,
}: {
  analyse: AnalyseIa | null
  auteur: boolean
  onDemander: () => void
}) {
  return (
    <section aria-labelledby="titre-ia" className="mt-2 flex flex-col gap-3 rounded-carte border border-[#DDD6FE] bg-canvas p-4">
      <div>
        <h2 id="titre-ia" className="m-0 text-base font-bold">
          Analyse IA
        </h2>
        <span className="text-[12.5px] text-muted">Souk AI · une suggestion, pas un correctif</span>
      </div>
      {!analyse && auteur ? (
        <>
          <p className="m-0 text-sm text-subtle">Souk AI lit la fiche du bug et propose une piste, à côté des correctifs.</p>
          <button type="button" onClick={onDemander} className={boutonIa}>
            Demander l’avis de Souk AI
          </button>
          <p className="m-0 text-[12.5px] text-muted">3 analyses par jour. Le reste n’est pas renvoyé par l’API.</p>
        </>
      ) : null}
      {!analyse && !auteur ? <p className="m-0 text-sm text-muted">Pas encore d’analyse.</p> : null}
      {analyse?.statut === 'en_cours' ? (
        <p role="status" className="m-0 rounded-controle bg-[#F5F3FF] p-3 text-sm text-[#4C1D95]">
          Analyse en cours. La réponse arrive ici, sans recharger la page.
        </p>
      ) : null}
      {analyse?.statut === 'prete' && analyse.texte ? (
        <p className="m-0 text-sm wrap-break-word whitespace-pre-wrap">{analyse.texte}</p>
      ) : null}
      {analyse?.statut === 'echec' ? (
        <p role="status" className="m-0 rounded-controle bg-page p-3 text-sm">
          Analyse indisponible. Les réponses de la communauté restent là.
        </p>
      ) : null}
      {auteur && analyse ? (
        <button type="button" onClick={onDemander} className="inline-flex min-h-cible items-center justify-center rounded-controle border border-[#DDD6FE] bg-canvas px-3.5 text-sm font-semibold text-[#5B21B6]">
          {analyse.statut === 'echec' ? 'Réessayer l’analyse' : 'Relancer'}
        </button>
      ) : null}
      {auteur ? <p className="m-0 text-[12.5px] font-semibold text-[#5B21B6]">3 analyses par jour</p> : null}
    </section>
  )
}
