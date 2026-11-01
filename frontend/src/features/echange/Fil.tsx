import { useCallback, useState, type ReactNode } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate, useSearchParams } from 'react-router'
import { Plus } from 'lucide-react'
import { useTitrePage } from '../../app/titre'
import { listerCanaux, listerDiscussions, listerEtiquettes, messageAction, type Discussion } from './api'
import { CarteDiscussion } from './CarteDiscussion'
import { nomPays } from './texte'
import { FormulaireDiscussion } from './FormulaireDiscussion'
import { correspond, ecrireFiltres, filtresActifs, lireFiltres, type FiltresFil } from './filtres'
import { libelleDifficulte } from './presentation'
import { useBandeau } from './tempsReel'
import { Bouton, Champ, EnTetePage, EtatErreur, EtatVide, Liste, Modale, Pagination, PuceFiltre, SqueletteListe, cx, useToast } from '../../ui'

function phraseCompte(total: number, filtres: boolean): string {
  const mot = total > 1 ? 'discussions' : 'discussion'
  return `${total} ${mot}${filtres ? ' pour ces filtres' : ''}`
}

/**
 * Échange : le fil des discussions. Canaux à gauche (liste sur mobile), filtres par étiquette,
 * statut et difficulté, nouvelles discussions annoncées en direct sans bouger la lecture.
 */
export function Fil() {
  useTitrePage('Échange')
  const [params, setParams] = useSearchParams()
  const navigate = useNavigate()
  const toast = useToast()
  const filtres = lireFiltres(params)
  const actifs = filtresActifs(filtres)
  const queryClient = useQueryClient()
  const [ouverte, setOuverte] = useState(false)
  const [epinglees, setEpinglees] = useState<Discussion[]>([])
  const bandeau = useBandeau(filtres)
  const fermer = useCallback(() => setOuverte(false), [])

  const canaux = useQuery({ queryKey: ['canaux'], queryFn: listerCanaux })
  const etiquettes = useQuery({ queryKey: ['etiquettes'], queryFn: listerEtiquettes })
  const discussions = useQuery({
    queryKey: ['discussions', filtres],
    queryFn: () => listerDiscussions(filtres),
  })

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
      toast.succes('Lien copié.')
    } catch {
      toast.erreur('Impossible de copier le lien.')
    }
  }

  function publiee(discussion: Discussion) {
    setOuverte(false)
    setEpinglees((liste) => [discussion, ...liste.filter((item) => item.id !== discussion.id)])
    toast.succes('Discussion publiée.', { libelle: 'Voir', onClick: () => navigate(`/echange/${discussion.id}`) })
    void queryClient.invalidateQueries({ queryKey: ['discussions'] })
  }

  const liste = discussions.data
  const visibles = epinglees.filter((discussion) => correspond(discussion, filtres))
  const duServeur = (liste?.data ?? []).filter((discussion) => !visibles.some((item) => item.id === discussion.id))
  const lignes = [...visibles, ...duServeur]
  const generaux = (canaux.data ?? []).filter((canal) => canal.pays === null)
  const parPays = (canaux.data ?? []).filter((canal) => canal.pays !== null)

  return (
    <div className="mx-auto flex w-full max-w-site flex-col gap-6 px-4 py-8 tablette:px-6 bureau:px-8">
      <EnTetePage
        titre="Échange"
        description="Posez votre problème avec votre dépôt : d’autres devs le voient, le corrigent et vous proposent leur branche."
        actions={
          <Bouton icone={<Plus aria-hidden="true" className="size-4" />} onClick={() => setOuverte(true)}>
            Nouvelle discussion
          </Bouton>
        }
      />

      <div className="flex flex-col gap-6 tablette:flex-row tablette:items-start">
        <Canaux generaux={generaux} parPays={parPays} canalActif={filtres.canal} onChoisir={(canal) => changer({ canal })} />

        <section aria-labelledby="titre-fil" className="flex min-w-0 flex-1 flex-col gap-4">
          <h2 id="titre-fil" className="pour-lecteur">
            Discussions
          </h2>
          <div className="flex flex-col gap-3 rounded-moyen border border-bordure bg-surface p-3 tablette:p-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
              <div role="group" aria-label="Étiquettes" className="flex flex-wrap gap-2">
                {(etiquettes.data ?? []).map((etiquette) => (
                  <PuceFiltre key={etiquette} presse={filtres.etiquette === etiquette} onClick={() => changer({ etiquette: filtres.etiquette === etiquette ? undefined : etiquette })}>
                    {etiquette}
                  </PuceFiltre>
                ))}
              </div>
              <div role="group" aria-label="Statut" className="inline-flex rounded-moyen border border-bordure bg-surface-douce p-1">
                {([
                  [undefined, 'Toutes'],
                  ['ouvertes', 'Ouvertes'],
                  ['resolues', 'Résolues'],
                ] as const).map(([statut, libelle]) => (
                  <button
                    key={libelle}
                    type="button"
                    aria-pressed={filtres.statut === statut}
                    onClick={() => changer({ statut })}
                    className={cx(
                      'inline-flex h-11 items-center rounded-petit px-3 text-petit font-medium transition-colors duration-150 tablette:h-8',
                      filtres.statut === statut ? 'bg-primaire-doux text-sur-primaire-doux shadow-1' : 'text-texte-doux hover:text-titre',
                    )}
                  >
                    {libelle}
                  </button>
                ))}
              </div>
            </div>
            {filtres.etiquette === 'bug' || filtres.difficulte ? (
            <div role="group" aria-labelledby="t-difficulte" className="flex flex-wrap items-center gap-2 border-t border-bordure pt-3">
              <span id="t-difficulte" className="mr-1 text-petit font-medium text-titre">
                Difficulté du bug
              </span>
              {(['facile', 'moyen', 'difficile'] as const).map((difficulte) => (
                <PuceFiltre key={difficulte} presse={filtres.difficulte === difficulte} onClick={() => changer({ difficulte: filtres.difficulte === difficulte ? undefined : difficulte })}>
                  {libelleDifficulte[difficulte]}
                </PuceFiltre>
              ))}
            </div>
            ) : null}
          </div>

          {bandeau.nombre > 0 ? (
            <Bouton variante="secondaire" pleineLargeur onClick={montrerNouvelles}>
              {bandeau.nombre > 1 ? `${bandeau.nombre} nouvelles discussions · Afficher` : '1 nouvelle discussion · Afficher'}
            </Bouton>
          ) : null}

          <div className="flex min-h-11 flex-wrap items-center justify-between gap-2">
            <p role="status" className="m-0 text-petit text-texte-doux">
              {liste ? phraseCompte(Math.max(liste.meta.total, lignes.length), actifs) : null}
            </p>
            {actifs ? (
              <Bouton variante="fantome" taille="sm" onClick={() => setParams({})}>
                Effacer les filtres
              </Bouton>
            ) : null}
          </div>

          {discussions.isPending ? <SqueletteListe nombre={4} /> : null}
          {discussions.isError ? (
            <EtatErreur description={messageAction(discussions.error, 'Les discussions n’ont pas pu être chargées.')} onReessayer={() => void discussions.refetch()} />
          ) : null}
          {discussions.isSuccess && lignes.length === 0 ? (
            <EtatVide
              illustration="vide-discussions"
              titre="Aucune discussion pour ces filtres"
              description="Changez de canal ou d’étiquette, ou lancez la première discussion."
              action={
                actifs ? (
                  <Bouton variante="contour" onClick={() => setParams({})}>
                    Effacer les filtres
                  </Bouton>
                ) : (
                  <Bouton onClick={() => setOuverte(true)}>Nouvelle discussion</Bouton>
                )
              }
            />
          ) : null}
          {lignes.length > 0 ? (
            <ol className="m-0 flex list-none flex-col gap-3 p-0">
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
          {liste ? (
            <Pagination page={liste.meta.current_page} dernierePage={liste.meta.last_page} onChange={(page) => setParams(ecrireFiltres({ ...filtres, page }))} />
          ) : null}
        </section>
      </div>

      <Modale ouverte={ouverte} onFermer={fermer} titre="Nouvelle discussion" description="Un bug, une question, un tuto : avec votre dépôt, d’autres devs peuvent proposer un correctif." taille="grande">
        <FormulaireDiscussion canaux={canaux.data ?? []} canalInitial={filtres.canal} onAnnuler={fermer} onPubliee={publiee} />
      </Modale>
    </div>
  )
}

type CanalListe = { slug: string; nom: string; pays: string | null }

function Canaux({
  generaux,
  parPays,
  canalActif,
  onChoisir,
}: {
  generaux: CanalListe[]
  parPays: CanalListe[]
  canalActif?: string
  onChoisir: (canal?: string) => void
}) {
  return (
    <div className="w-full min-w-0 tablette:sticky tablette:top-24 tablette:w-56 tablette:shrink-0">
      <nav aria-label="Canaux" className="hidden flex-col gap-0.5 rounded-moyen border border-bordure bg-surface p-2 tablette:flex">
        <BoutonCanal actif={!canalActif} onClick={() => onChoisir(undefined)}>
          Tous les canaux
        </BoutonCanal>
        <p className="m-0 mt-3 mb-1 px-2.5 text-etiquette text-texte-doux uppercase">Généraux</p>
        {generaux.map((canal) => (
          <BoutonCanal key={canal.slug} actif={canalActif === canal.slug} onClick={() => onChoisir(canal.slug)}>
            #{canal.slug}
          </BoutonCanal>
        ))}
        <p className="m-0 mt-3 mb-1 px-2.5 text-etiquette text-texte-doux uppercase">Par pays</p>
        {parPays.map((canal) => (
          <BoutonCanal key={canal.slug} actif={canalActif === canal.slug} onClick={() => onChoisir(canal.slug)}>
            <span className="flex min-w-0 flex-col leading-tight">
              <span className="break-all">#{canal.slug}</span>
              {canal.pays ? <span className="text-petit font-normal text-texte-doux">{nomPays(canal.pays)}</span> : null}
            </span>
          </BoutonCanal>
        ))}
      </nav>
      <div className="tablette:hidden">
        <Champ libelle="Canal">
          {(liaison) => (
            <Liste {...liaison} value={canalActif ?? 'tous'} onChange={(evenement) => onChoisir(evenement.target.value === 'tous' ? undefined : evenement.target.value)}>
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
            </Liste>
          )}
        </Champ>
      </div>
    </div>
  )
}

function BoutonCanal({ actif, onClick, children }: { actif: boolean; onClick: () => void; children: ReactNode }) {
  return (
    <button
      type="button"
      aria-pressed={actif}
      onClick={onClick}
      className={cx(
        'flex min-h-11 items-center rounded-petit px-2.5 text-left text-corps break-all transition-colors duration-150 tablette:min-h-9',
        actif ? 'bg-surface-accent font-medium text-sur-primaire-doux' : 'text-texte hover:bg-surface-douce hover:text-titre',
      )}
    >
      {children}
    </button>
  )
}
