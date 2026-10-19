import { useCallback, useEffect, useState, type ReactNode } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useSearchParams, Link } from 'react-router'
import { EmptyState } from '../../shared/ui/EmptyState'
import { Modal } from '../../shared/ui/Modal'
import { listerCanaux, listerDiscussions, listerEtiquettes, type Discussion, type Etiquette } from './api'
import { CarteDiscussion } from './CarteDiscussion'
import { nomPays } from './texte'
import { FormulaireDiscussion } from './FormulaireDiscussion'
import { correspond, ecrireFiltres, filtresActifs, lireFiltres, type FiltresFil } from './filtres'
import { useBandeau } from './tempsReel'

const puceActive: Record<Etiquette, string> = {
  bug: 'border-danger bg-danger text-canvas',
  question: 'border-question bg-question text-canvas',
  tuto: 'border-success bg-success text-canvas',
  projet: 'border-projet bg-projet text-canvas',
  entraide: 'border-nav-texte bg-nav-texte text-canvas',
}

const puceRepos: Record<Etiquette, string> = {
  bug: 'border-line bg-canvas text-danger',
  question: 'border-line bg-canvas text-question',
  tuto: 'border-line bg-canvas text-success',
  projet: 'border-line bg-canvas text-projet',
  entraide: 'border-line bg-canvas text-nav-texte',
}

const difficulteActive = {
  facile: 'border-success bg-success text-canvas',
  moyen: 'border-warning bg-warning text-canvas',
  difficile: 'border-difficile bg-difficile text-canvas',
} as const

const difficulteRepos = {
  facile: 'border-line bg-canvas text-success',
  moyen: 'border-line bg-canvas text-warning',
  difficile: 'border-line bg-canvas text-difficile',
} as const

const libelleDifficulte = { facile: 'Facile', moyen: 'Moyen', difficile: 'Difficile' } as const

function phraseCompte(total: number, filtres: boolean): string {
  const mot = total > 1 ? 'discussions' : 'discussion'
  return `${total} ${mot}${filtres ? ' pour ces filtres' : ''}`
}

export function Fil() {
  const [params, setParams] = useSearchParams()
  const filtres = lireFiltres(params)
  const actifs = filtresActifs(filtres)
  const queryClient = useQueryClient()
  const [ouverte, setOuverte] = useState(false)
  const [epinglees, setEpinglees] = useState<Discussion[]>([])
  const [toast, setToast] = useState<{ texte: string; id?: number } | null>(null)
  const bandeau = useBandeau(filtres)
  const fermer = useCallback(() => setOuverte(false), [])

  const canaux = useQuery({ queryKey: ['canaux'], queryFn: listerCanaux })
  const etiquettes = useQuery({ queryKey: ['etiquettes'], queryFn: listerEtiquettes })
  const discussions = useQuery({
    queryKey: ['discussions', filtres],
    queryFn: () => listerDiscussions(filtres),
  })

  useEffect(() => {
    if (!toast) return
    const identifiant = window.setTimeout(() => setToast(null), 3500)
    return () => window.clearTimeout(identifiant)
  }, [toast])

  function changer(partiel: Partial<FiltresFil>) {
    setParams(ecrireFiltres({ ...filtres, ...partiel, page: 1 }))
  }

  function montrerNouvelles() {
    const recues = bandeau.prendre()
    setEpinglees((liste) => {
      const ids = new Set(liste.map((discussion) => discussion.id))
      return [...recues.filter((discussion) => !ids.has(discussion.id)), ...liste]
    })
    setParams(ecrireFiltres({ ...filtres, page: 1 }))
  }

  async function partager(discussion: Discussion) {
    const url = `${window.location.origin}/echange/${discussion.id}`
    try {
      await navigator.clipboard.writeText(url)
      setToast({ texte: 'Lien copié' })
    } catch {
      setToast({ texte: 'Impossible de copier le lien.' })
    }
  }

  function publiee(discussion: Discussion) {
    setOuverte(false)
    setEpinglees((liste) => [discussion, ...liste.filter((item) => item.id !== discussion.id)])
    setToast({ texte: 'Discussion publiée', id: discussion.id })
    void queryClient.invalidateQueries({ queryKey: ['discussions'] })
  }

  const liste = discussions.data
  const visibles = epinglees.filter((discussion) => correspond(discussion, filtres))
  const duServeur = (liste?.data ?? []).filter((discussion) => !visibles.some((item) => item.id === discussion.id))
  const lignes = [...visibles, ...duServeur]
  const generaux = (canaux.data ?? []).filter((canal) => canal.pays === null)
  const parPays = (canaux.data ?? []).filter((canal) => canal.pays !== null)
  const etiquettesAffichees = etiquettes.data ?? []

  return (
    <main className="mx-auto flex max-w-[1200px] flex-col gap-6 overflow-x-clip px-4 py-7 min-[761px]:px-6">
      <div className="flex flex-col gap-4 min-[761px]:flex-row min-[761px]:items-end min-[761px]:justify-between">
        <div className="min-w-0">
          <h1 className="m-0 text-[30px] leading-[1.2] font-bold tracking-tight text-ink">Échange</h1>
          <p className="m-0 text-muted">
            Posez votre problème avec votre dépôt : d’autres devs le voient, le corrigent et vous proposent leur branche.
          </p>
        </div>
        <button
          type="button"
          onClick={() => setOuverte(true)}
          className="rebond inline-flex min-h-cible w-full items-center justify-center gap-2 rounded-controle bg-accent px-5 font-semibold text-canvas hover:bg-accent-hover min-[761px]:w-auto"
        >
          <span aria-hidden="true">+</span>
          Nouvelle discussion
        </button>
      </div>

      <div className="flex flex-col items-start gap-6 min-[761px]:flex-row">
        <Canaux
          generaux={generaux}
          parPays={parPays}
          canalActif={filtres.canal}
          onChoisir={(canal) => changer({ canal })}
        />
        <section aria-labelledby="titre-fil" className="flex min-w-0 flex-1 flex-col gap-4">
          <h2 id="titre-fil" className="sr-only">
            Discussions
          </h2>
          <div role="tablist" aria-label="Fil" className="flex gap-1 border-b border-line">
            <button
              type="button"
              role="tab"
              id="onglet-pour-toi"
              aria-selected="true"
              className="min-h-cible border-b-2 border-accent px-4 font-bold text-ink"
            >
              Pour toi
            </button>
          </div>
          <div role="tabpanel" aria-labelledby="onglet-pour-toi" className="flex flex-col gap-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div role="group" aria-label="Étiquettes" className="flex flex-wrap gap-2">
                {etiquettesAffichees.map((etiquette) => (
                  <Puce
                    key={etiquette}
                    presse={filtres.etiquette === etiquette}
                    onClick={() => changer({ etiquette: filtres.etiquette === etiquette ? undefined : etiquette })}
                    classes={filtres.etiquette === etiquette ? puceActive[etiquette] : puceRepos[etiquette]}
                  >
                    {etiquette}
                  </Puce>
                ))}
              </div>
              <div role="group" aria-label="Statut" className="inline-flex rounded-controle bg-statut p-[3px]">
                <BoutonStatut
                  libelle="Toutes"
                  presse={!filtres.statut}
                  onClick={() => changer({ statut: undefined })}
                />
                <BoutonStatut
                  libelle="Ouvertes"
                  presse={filtres.statut === 'ouvertes'}
                  onClick={() => changer({ statut: 'ouvertes' })}
                />
                <BoutonStatut
                  libelle="Résolues"
                  presse={filtres.statut === 'resolues'}
                  onClick={() => changer({ statut: 'resolues' })}
                />
              </div>
            </div>
            <div role="group" aria-labelledby="t-difficulte" className="flex flex-wrap items-center gap-2">
              <span id="t-difficulte" className="mr-1 text-sm font-semibold text-nav-texte">
                Difficulté <span className="font-medium text-muted">(bugs)</span>
              </span>
              {(['facile', 'moyen', 'difficile'] as const).map((difficulte) => (
                <Puce
                  key={difficulte}
                  presse={filtres.difficulte === difficulte}
                  onClick={() =>
                    changer({ difficulte: filtres.difficulte === difficulte ? undefined : difficulte })
                  }
                  classes={filtres.difficulte === difficulte ? difficulteActive[difficulte] : difficulteRepos[difficulte]}
                >
                  <Barres niveau={difficulte === 'facile' ? 1 : difficulte === 'moyen' ? 2 : 3} />
                  {libelleDifficulte[difficulte]}
                </Puce>
              ))}
            </div>
          </div>

          {bandeau.nombre > 0 ? (
            <button
              type="button"
              onClick={montrerNouvelles}
              className="inline-flex min-h-cible items-center justify-center gap-2 rounded-xl border border-banner-line bg-nav font-semibold text-accent"
            >
              <span aria-hidden="true" className="bandeau-point size-2 rounded-full bg-accent" />
              {bandeau.nombre > 1
                ? `${bandeau.nombre} nouvelles discussions · Afficher`
                : '1 nouvelle discussion · Afficher'}
            </button>
          ) : null}

          <div className="flex min-h-8 flex-wrap items-center justify-between gap-2">
            <p role="status" className="m-0 text-sm text-muted">
              {liste ? phraseCompte(Math.max(liste.meta.total, lignes.length), actifs) : null}
            </p>
            {actifs ? (
              <button type="button" onClick={() => setParams({})} className="min-h-cible font-semibold text-accent">
                Effacer les filtres
              </button>
            ) : null}
          </div>

          {discussions.isPending ? <SquelettesFil /> : null}
          {discussions.isError ? (
            <div role="alert" className="flex flex-col items-start gap-3 rounded-carte bg-canvas p-5">
              <p className="m-0 text-ink">Les discussions n’ont pas pu être chargées.</p>
              <button
                type="button"
                onClick={() => void discussions.refetch()}
                className="inline-flex min-h-cible items-center rounded-controle bg-accent px-4 font-semibold text-canvas"
              >
                Réessayer
              </button>
            </div>
          ) : null}
          {discussions.isSuccess && lignes.length === 0 ? (
            <EmptyState message="Aucune discussion pour ces filtres. Changez de canal ou d’étiquette, ou lancez la première discussion.">
              {actifs ? (
                <button
                  type="button"
                  onClick={() => setParams({})}
                  className="inline-flex min-h-cible items-center rounded-controle border border-line bg-canvas px-4 font-semibold text-ink"
                >
                  Effacer les filtres
                </button>
              ) : (
                <button
                  type="button"
                  onClick={() => setOuverte(true)}
                  className="inline-flex min-h-cible items-center rounded-controle bg-accent px-4 font-semibold text-canvas"
                >
                  Nouvelle discussion
                </button>
              )}
            </EmptyState>
          ) : null}
          {lignes.length > 0 ? (
            <ol className="fil-vivant m-0 flex list-none flex-col gap-3 p-0">
              {lignes.map((discussion) => (
                <CarteDiscussion
                  key={discussion.id}
                  discussion={discussion}
                  nouvelle={visibles.some((item) => item.id === discussion.id)}
                  onPartager={(item) => void partager(item)}
                />
              ))}
            </ol>
          ) : null}
          {liste && liste.meta.last_page > 1 ? (
            <nav aria-label="Pages" className="flex items-center gap-3">
              <button
                type="button"
                disabled={filtres.page <= 1}
                onClick={() => setParams(ecrireFiltres({ ...filtres, page: filtres.page - 1 }))}
                className="inline-flex min-h-cible items-center rounded-controle border border-line bg-canvas px-4 font-semibold text-ink disabled:opacity-50"
              >
                Page précédente
              </button>
              <span className="text-sm text-muted">
                Page {liste.meta.current_page} sur {liste.meta.last_page}
              </span>
              <button
                type="button"
                disabled={filtres.page >= liste.meta.last_page}
                onClick={() => setParams(ecrireFiltres({ ...filtres, page: filtres.page + 1 }))}
                className="inline-flex min-h-cible items-center rounded-controle border border-line bg-canvas px-4 font-semibold text-ink disabled:opacity-50"
              >
                Page suivante
              </button>
            </nav>
          ) : null}
        </section>
      </div>

      {ouverte ? (
        <Modal titre="Nouvelle discussion" onFermer={fermer}>
          <FormulaireDiscussion
            canaux={canaux.data ?? []}
            canalInitial={filtres.canal}
            onAnnuler={fermer}
            onPubliee={publiee}
          />
        </Modal>
      ) : null}

      {toast ? (
        <div
          role="status"
          className="fixed bottom-6 left-1/2 z-50 flex max-w-[calc(100vw-2rem)] -translate-x-1/2 items-center gap-3 rounded-xl bg-ink px-[18px] py-3 font-semibold text-canvas shadow-toast"
        >
          {toast.texte}
          {toast.id ? (
            <Link to={`/echange/${toast.id}`} className="font-bold text-toast-link no-underline">
              Voir
            </Link>
          ) : null}
        </div>
      ) : null}
    </main>
  )
}

function Barres({ niveau }: { niveau: 1 | 2 | 3 }) {
  return (
    <svg width="14" height="12" viewBox="0 0 14 12" aria-hidden="true">
      <rect x="0" y="7" width="3" height="5" rx="1" fill="currentColor" />
      <rect x="5.5" y="4" width="3" height="8" rx="1" fill="currentColor" opacity={niveau >= 2 ? 1 : 0.3} />
      <rect x="11" y="0" width="3" height="12" rx="1" fill="currentColor" opacity={niveau >= 3 ? 1 : 0.3} />
    </svg>
  )
}

function SquelettesFil() {
  return (
    <div role="status" aria-label="Chargement des discussions" className="fil-vivant flex flex-col gap-3">
      {[0, 1, 2].map((indice) => (
        <div key={indice} className="squelette h-32 rounded-carte" />
      ))}
    </div>
  )
}

function BoutonStatut({
  libelle,
  presse,
  onClick,
}: {
  libelle: string
  presse: boolean
  onClick: () => void
}) {
  return (
    <button
      type="button"
      aria-pressed={presse}
      onClick={onClick}
      className={`inline-flex min-h-cible items-center rounded-lg px-3.5 text-sm font-semibold ${
        presse ? 'bg-canvas text-ink' : 'bg-transparent text-subtle'
      }`}
    >
      {libelle}
    </button>
  )
}

function Canaux({
  generaux,
  parPays,
  canalActif,
  onChoisir,
}: {
  generaux: { slug: string; nom: string; pays: string | null }[]
  parPays: { slug: string; nom: string; pays: string | null }[]
  canalActif?: string
  onChoisir: (canal?: string) => void
}) {
  return (
    <aside aria-label="Canaux" className="w-full min-w-0 min-[761px]:w-[220px] min-[761px]:shrink-0">
      <nav className="hidden flex-col gap-0.5 rounded-carte border border-line bg-canvas p-2.5 min-[761px]:flex">
        <p className="m-1.5 text-[12.5px] font-semibold tracking-wide text-muted uppercase">Canaux</p>
        <BoutonCanal actif={!canalActif} onClick={() => onChoisir(undefined)}>
          Tous les canaux
        </BoutonCanal>
        <p className="mt-3 mb-1 px-2.5 text-xs font-semibold text-nav-doux">Généraux</p>
        {generaux.map((canal) => (
          <BoutonCanal key={canal.slug} actif={canalActif === canal.slug} onClick={() => onChoisir(canal.slug)}>
            #{canal.slug}
          </BoutonCanal>
        ))}
        <p className="mt-3 mb-1 px-2.5 text-xs font-semibold text-nav-doux">Par pays</p>
        {parPays.map((canal) => (
          <BoutonCanal key={canal.slug} actif={canalActif === canal.slug} onClick={() => onChoisir(canal.slug)}>
            <span className="flex min-w-0 flex-col leading-tight">
              <span className="break-all">#{canal.slug}</span>
              {canal.pays ? <span className="text-xs font-medium text-muted">{nomPays(canal.pays)}</span> : null}
            </span>
          </BoutonCanal>
        ))}
      </nav>
      <div className="flex flex-col gap-1.5 min-[761px]:hidden">
        <label htmlFor="canal-mobile" className="text-sm font-semibold text-ink">
          Canal
        </label>
        <select
          id="canal-mobile"
          value={canalActif ?? 'tous'}
          onChange={(evenement) => onChoisir(evenement.target.value === 'tous' ? undefined : evenement.target.value)}
          className="min-h-cible rounded-controle border border-line bg-canvas px-3 text-ink"
        >
          <option value="tous">Tous les canaux</option>
          <optgroup label="Généraux">
            {generaux.map((canal) => (
              <option key={canal.slug} value={canal.slug}>
                #{canal.slug}
              </option>
            ))}
          </optgroup>
          <optgroup label="Par pays">
            {parPays.map((canal) => (
              <option key={canal.slug} value={canal.slug}>
                #{canal.slug}
              </option>
            ))}
          </optgroup>
        </select>
      </div>
    </aside>
  )
}

function Puce({
  presse,
  onClick,
  classes,
  children,
}: {
  presse: boolean
  onClick: () => void
  classes: string
  children: ReactNode
}) {
  return (
    <button
      type="button"
      aria-pressed={presse}
      onClick={onClick}
      className="inline-flex min-h-cible items-center border-0 bg-transparent p-0"
    >
      <span className={`inline-flex h-9 items-center gap-1.5 rounded-full border px-3.5 text-sm font-semibold ${classes}`}>
        {children}
      </span>
    </button>
  )
}

function BoutonCanal({
  actif,
  onClick,
  children,
}: {
  actif: boolean
  onClick: () => void
  children: ReactNode
}) {
  return (
    <button
      type="button"
      aria-pressed={actif}
      onClick={onClick}
      className={`flex min-h-cible items-center rounded-lg px-2.5 text-left break-all ${
        actif ? 'bg-nav font-semibold text-accent' : 'font-medium text-nav-texte hover:bg-page'
      }`}
    >
      {children}
    </button>
  )
}
