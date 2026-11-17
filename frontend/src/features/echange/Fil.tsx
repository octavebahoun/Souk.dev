import { useCallback, useState, type ReactNode } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Link, useNavigate, useSearchParams } from 'react-router'
import { ArrowRight, BookOpen, Bug, CalendarDays, CircleHelp, GitPullRequestArrow, Mail, Plus, Rocket, Star, Users } from 'lucide-react'
import { useTitrePage } from '../../app/titre'
import { listerCanaux, listerDiscussions, messageAction, suivreCanal, type Canal, type Discussion, type Etiquette } from './api'
import { listerDevs, listerEvenements } from '../catalogue/api'
import { CarteDiscussion } from './CarteDiscussion'
import { nomPays } from './texte'
import { FormulaireDiscussion } from './FormulaireDiscussion'
import { FormulaireCanal } from './FormulaireCanal'
import { correspond, ecrireFiltres, filtresActifs, lireFiltres, type FiltresFil } from './filtres'
import { libelleDifficulte } from './presentation'
import { useBandeau } from './tempsReel'
import { useCompte, useSelonCompte } from '../../app/compte'
import { ModaleConnexion } from '../../app/ModaleConnexion'
import { Avatar, AvisDonneesPartielles, Bouton, Carte, Champ, ChoixPastilles, EnTetePage, EtatErreur, EtatVide, Liste, Modale, Onglets, Pagination, PuceFiltre, Squelette, SqueletteListe, cx, formaterDate, useToast } from '../../ui'

function phraseCompte(total: number, filtres: boolean): string {
  const mot = total > 1 ? 'discussions' : 'discussion'
  return `${total} ${mot}${filtres ? ' pour ces filtres' : ''}`
}

type OngletFil = 'pour_moi' | 'tout' | 'bugs' | 'question' | 'projet' | 'tuto'

/**
 * Échange : le fil des discussions. Tout part d'une boîte « Sur quoi bloquez-vous ? » et de ses
 * boutons rapides ; des onglets simples (pour moi, bugs à résoudre, questions…), les canaux à
 * gauche et, à droite, comment ça marche, des devs disponibles et les prochains événements.
 */
export function Fil() {
  useTitrePage('Échange', { description: 'L’espace d’entraide des devs africains : postez un bug avec votre dépôt, un autre dev propose un correctif que vous essayez avant d’accepter.' })
  const [params, setParams] = useSearchParams()
  const navigate = useNavigate()
  const toast = useToast()
  const compte = useCompte()
  const lus = lireFiltres(params)
  // Un membre arrive sur son fil : ce qui le concerne d'abord. Il peut tout voir par date d'un clic.
  const pourMoi = Boolean(compte.data) && lus.fil !== 'tout'
  const filtres: FiltresFil = { ...lus, fil: pourMoi ? 'pour_moi' : lus.fil }
  const actifs = filtresActifs(filtres)
  const queryClient = useQueryClient()
  const selonCompte = useSelonCompte()
  const [ouverte, setOuverte] = useState(false)
  const [etiquetteForm, setEtiquetteForm] = useState<Etiquette | undefined>(undefined)
  const [ouvertures, setOuvertures] = useState(0)
  const [connexion, setConnexion] = useState<string | null>(null)
  const [nouveauCanal, setNouveauCanal] = useState(0)
  const [epinglees, setEpinglees] = useState<Discussion[]>([])
  const bandeau = useBandeau(filtres)
  const fermer = useCallback(() => setOuverte(false), [])

  const canaux = useQuery({ queryKey: ['canaux'], queryFn: listerCanaux })
  const discussions = useQuery({
    queryKey: ['discussions', filtres],
    queryFn: () => listerDiscussions(filtres),
  })

  const suivre = useMutation({
    mutationFn: ({ slug, actif }: { slug: string; actif: boolean }) => suivreCanal(slug, actif),
    onSuccess: async (canal) => {
      queryClient.setQueryData<Canal[]>(['canaux'], (liste) => liste?.map((item) => (item.slug === canal.slug ? canal : item)))
      toast.succes(canal.suivi ? `Vous suivez #${canal.slug} : ses discussions passent en premier dans votre fil.` : `Vous ne suivez plus #${canal.slug}.`)
      await queryClient.invalidateQueries({ queryKey: ['discussions'] })
    },
    onError: (erreur) => toast.erreur(messageAction(erreur, 'Le canal n’a pas pu être suivi.')),
  })

  function basculerSuivi(canal: Canal) {
    void selonCompte(() => suivre.mutate({ slug: canal.slug, actif: !canal.suivi }), () => setConnexion('suivre un canal'))
  }

  /** Pourquoi une discussion remonte dans le fil « Pour moi » : la raison la plus forte seulement. */
  function raisonPour(discussion: Discussion): string | undefined {
    if (!pourMoi || !compte.data) return undefined
    const canal = (canaux.data ?? []).find((item) => item.slug === discussion.canal)
    if (canal?.suivi) return 'Canal suivi'
    if (discussion.bug?.techno && (compte.data.competences ?? []).includes(discussion.bug.techno)) return 'Votre techno'
    if (canal?.pays && canal.pays === compte.data.pays) return 'Votre pays'
    return undefined
  }

  // Publier une discussion demande un compte : un visiteur l'apprend avant de remplir le formulaire.
  function nouvelle(etiquette?: Etiquette) {
    void selonCompte(
      () => {
        setEtiquetteForm(etiquette)
        setOuvertures((nombre) => nombre + 1)
        setOuverte(true)
      },
      () => setConnexion('publier une discussion'),
    )
  }

  // Les onglets sont des filtres prêts à l'emploi : le dev n'a pas à les composer lui-même.
  const onglet: OngletFil =
    filtres.etiquette === 'bug' ? 'bugs' : filtres.etiquette === 'question' || filtres.etiquette === 'projet' || filtres.etiquette === 'tuto' ? filtres.etiquette : pourMoi ? 'pour_moi' : 'tout'
  const onglets: { valeur: OngletFil; libelle: string }[] = [
    ...(compte.data ? [{ valeur: 'pour_moi' as const, libelle: 'Pour moi' }] : []),
    { valeur: 'tout', libelle: compte.data ? 'Tout' : 'Récent' },
    { valeur: 'bugs', libelle: 'Bugs à résoudre' },
    { valeur: 'question', libelle: 'Questions' },
    { valeur: 'projet', libelle: 'Projets' },
    { valeur: 'tuto', libelle: 'Tutos' },
  ]

  function choisirOnglet(valeur: OngletFil) {
    const sans = { etiquette: undefined, statut: undefined, difficulte: undefined }
    if (valeur === 'pour_moi') changer({ ...sans, fil: undefined })
    else if (valeur === 'tout') changer({ ...sans, fil: 'tout' })
    else if (valeur === 'bugs') changer({ ...sans, etiquette: 'bug', statut: 'ouvertes' })
    else changer({ ...sans, etiquette: valeur })
  }

  // Le compteur remonte un formulaire vierge à chaque ouverture (0 : fermé).
  function ouvrirCanal() {
    void selonCompte(() => setNouveauCanal((nombre) => Math.abs(nombre) + 1), () => setConnexion('créer un canal'))
  }

  function canalCree(canal: Canal) {
    setNouveauCanal((nombre) => -Math.abs(nombre))
    void queryClient.invalidateQueries({ queryKey: ['canaux'] })
    changer({ canal: canal.slug })
    toast.succes(`Canal #${canal.slug} créé.`)
  }

  function changer(partiel: Partial<FiltresFil>) {
    setParams(ecrireFiltres({ ...lus, ...partiel, page: 1 }))
  }

  function montrerNouvelles() {
    const recues = bandeau.prendre()
    setEpinglees((liste) => {
      const ids = new Set(liste.map((discussion) => discussion.id))
      return [...recues.filter((discussion) => !ids.has(discussion.id)), ...liste]
    })
    setParams(ecrireFiltres({ ...lus, page: 1 }))
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
        description="L’entraide entre devs africains : postez ce qui vous bloque avec votre dépôt, un autre dev propose sa branche, et vous l’essayez sur une copie avant d’accepter."
        actions={
          // Sur téléphone, la boîte « Sur quoi bloquez-vous ? » juste en dessous fait la même chose.
          <Bouton icone={<Plus aria-hidden="true" className="size-4" />} onClick={() => nouvelle()} className="max-tablette:hidden">
            Nouvelle discussion
          </Bouton>
        }
      />

      <div className="flex flex-col gap-6 tablette:flex-row tablette:items-start">
        <Canaux generaux={generaux} parPays={parPays} canalActif={filtres.canal} onChoisir={(canal) => changer({ canal })} onCreer={ouvrirCanal} onSuivre={basculerSuivi} />

        <section aria-labelledby="titre-fil" className="flex min-w-0 flex-1 flex-col gap-4">
          <h2 id="titre-fil" className="pour-lecteur">
            Discussions
          </h2>

          <Composeur nom={compte.data?.nom ?? null} avatar={compte.data?.avatar_url ?? null} onEcrire={nouvelle} />

          {bandeau.nombre > 0 ? (
            <Bouton variante="secondaire" pleineLargeur onClick={montrerNouvelles}>
              {bandeau.nombre > 1 ? `${bandeau.nombre} nouvelles discussions · Afficher` : '1 nouvelle discussion · Afficher'}
            </Bouton>
          ) : null}

          <Onglets libelle="Fil de l’Échange" valeur={onglet} onChange={choisirOnglet} onglets={onglets}>
            <div className="flex flex-col gap-4 pt-4">
              {onglet === 'pour_moi' ? (
                <p className="m-0 text-petit text-texte">
                  D’abord vos canaux suivis (étoile à côté d’un canal), les bugs dans vos technos{(compte.data?.competences ?? []).length > 0 ? ` (${(compte.data?.competences ?? []).slice(0, 3).join(', ')})` : ''} et votre pays.{' '}
                  <Link to="/profil" className="font-medium underline underline-offset-2">
                    Régler mes technos
                  </Link>
                </p>
              ) : null}

              {onglet === 'bugs' ? (
                <div role="group" aria-labelledby="t-difficulte" className="flex flex-wrap items-center gap-2">
                  <span id="t-difficulte" className="mr-1 text-petit font-medium text-titre">
                    Difficulté
                  </span>
                  {(['facile', 'moyen', 'difficile'] as const).map((difficulte) => (
                    <PuceFiltre key={difficulte} presse={filtres.difficulte === difficulte} onClick={() => changer({ difficulte: filtres.difficulte === difficulte ? undefined : difficulte })}>
                      {libelleDifficulte[difficulte]}
                    </PuceFiltre>
                  ))}
                </div>
              ) : null}

              {canaux.isError ? <AvisDonneesPartielles>Les canaux n’ont pas pu être chargés : les discussions restent lisibles.</AvisDonneesPartielles> : null}

              <div className="flex min-h-11 flex-wrap items-center justify-between gap-2">
                <p role="status" className="m-0 text-petit text-texte-doux">
                  {liste ? phraseCompte(Math.max(liste.meta.total, lignes.length), actifs) : null}
                </p>
                <div className="flex flex-wrap items-center gap-2">
                  {onglet !== 'bugs' ? (
                    <ChoixPastilles
                      libelle="Statut"
                      valeur={filtres.statut ?? 'toutes'}
                      onChange={(statut) => changer({ statut: statut === 'toutes' ? undefined : statut })}
                      options={[
                        { valeur: 'toutes', libelle: 'Toutes' },
                        { valeur: 'ouvertes', libelle: 'Ouvertes' },
                        { valeur: 'resolues', libelle: 'Résolues' },
                      ]}
                    />
                  ) : null}
                  {filtres.canal ? (
                    <Bouton variante="fantome" taille="sm" onClick={() => changer({ canal: undefined })}>
                      Tous les canaux
                    </Bouton>
                  ) : null}
                </div>
              </div>

              {discussions.isPending ? <SqueletteListe nombre={4} /> : null}
              {discussions.isError ? (
                <EtatErreur description={messageAction(discussions.error, 'Les discussions n’ont pas pu être chargées.')} onReessayer={() => void discussions.refetch()} />
              ) : null}
              {discussions.isSuccess && lignes.length === 0 ? (
                <EtatVide
                  illustration="vide-discussions"
                  titre={onglet === 'bugs' ? 'Aucun bug en attente ici' : 'Rien ici pour l’instant'}
                  description={onglet === 'bugs' ? 'Tous les bugs de ce fil ont un correctif accepté. Bloqué sur le vôtre ? Postez-le.' : 'Changez d’onglet ou de canal, ou lancez la première discussion.'}
                  action={
                    actifs ? (
                      <Bouton variante="contour" onClick={() => setParams({})}>
                        Effacer les filtres
                      </Bouton>
                    ) : (
                      <Bouton onClick={() => nouvelle(onglet === 'bugs' ? 'bug' : undefined)}>Nouvelle discussion</Bouton>
                    )
                  }
                />
              ) : null}
              {lignes.length > 0 ? (
                <ol className="m-0 flex list-none flex-col gap-3 p-0">
                  {lignes.map((discussion, index) => (
                    <CarteDiscussion
                      key={discussion.id}
                      discussion={discussion}
                      delai={Math.min(index, 11) * 50}
                      nouvelle={visibles.some((item) => item.id === discussion.id)}
                      raison={raisonPour(discussion)}
                      onPartager={(item) => void partager(item)}
                    />
                  ))}
                </ol>
              ) : null}
              {liste ? (
                <Pagination libelle="Pages des discussions" page={liste.meta.current_page} dernierePage={liste.meta.last_page} onChange={(page) => setParams(ecrireFiltres({ ...lus, page }))} />
              ) : null}
            </div>
          </Onglets>
        </section>

        <ColonneAide connecte={Boolean(compte.data)} moiId={compte.data?.id ?? null} />
      </div>

      <Modale
        ouverte={ouverte}
        onFermer={fermer}
        titre={etiquetteForm === 'bug' ? 'Je suis bloqué : décrivez le bug' : 'Nouvelle discussion'}
        description="Avec votre dépôt GitHub, d’autres devs peuvent proposer un correctif, que vous essayez sur une copie avant d’accepter."
        taille="grande"
      >
        <FormulaireDiscussion key={ouvertures} canaux={canaux.data ?? []} canalInitial={filtres.canal} etiquetteInitiale={etiquetteForm} onAnnuler={fermer} onPubliee={publiee} />
      </Modale>
      <Modale ouverte={nouveauCanal > 0} onFermer={() => setNouveauCanal((nombre) => -Math.abs(nombre))} titre="Créer un canal" description="Un canal regroupe les discussions d’un sujet ou d’un pays.">
        <FormulaireCanal key={Math.abs(nouveauCanal)} onAnnuler={() => setNouveauCanal((nombre) => -Math.abs(nombre))} onCree={canalCree} />
      </Modale>
      <ModaleConnexion ouverte={connexion !== null} onFermer={() => setConnexion(null)} action={connexion ?? 'publier une discussion'} />
    </div>
  )
}

type CanalListe = Canal

function Canaux({
  generaux,
  parPays,
  canalActif,
  onChoisir,
  onCreer,
  onSuivre,
}: {
  generaux: CanalListe[]
  parPays: CanalListe[]
  canalActif?: string
  onChoisir: (canal?: string) => void
  onCreer: () => void
  onSuivre: (canal: CanalListe) => void
}) {
  const choisi = [...generaux, ...parPays].find((canal) => canal.slug === canalActif)

  return (
    <div className="w-full min-w-0 tablette:sticky tablette:top-24 tablette:w-56 tablette:shrink-0">
      <div role="group" aria-label="Canaux" className="hidden flex-col gap-0.5 rounded-moyen border border-bordure bg-surface p-2 tablette:flex">
        <BoutonCanal actif={!canalActif} onClick={() => onChoisir(undefined)}>
          Tous les canaux
        </BoutonCanal>
        <p className="m-0 mt-3 mb-1 px-2.5 text-etiquette text-texte-doux uppercase">Généraux</p>
        {generaux.map((canal) => (
          <LigneCanal key={canal.slug} canal={canal} onSuivre={onSuivre}>
            <BoutonCanal actif={canalActif === canal.slug} onClick={() => onChoisir(canal.slug)}>
              #{canal.slug}
            </BoutonCanal>
          </LigneCanal>
        ))}
        <p className="m-0 mt-3 mb-1 px-2.5 text-etiquette text-texte-doux uppercase">Par pays</p>
        {parPays.map((canal) => (
          <LigneCanal key={canal.slug} canal={canal} onSuivre={onSuivre}>
            <BoutonCanal actif={canalActif === canal.slug} onClick={() => onChoisir(canal.slug)}>
              <span className="flex min-w-0 flex-col leading-tight">
                <span className="break-all">#{canal.slug}</span>
                {canal.pays ? <span className="text-petit font-normal text-texte-doux">{nomPays(canal.pays)}</span> : null}
              </span>
            </BoutonCanal>
          </LigneCanal>
        ))}
        <Bouton variante="fantome" taille="sm" icone={<Plus aria-hidden="true" className="size-4" />} onClick={onCreer} className="mt-3 justify-start">
          Créer un canal
        </Bouton>
      </div>
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
        <div className="mt-2 flex flex-wrap gap-2">
          {choisi ? (
            <Bouton variante={choisi.suivi ? 'secondaire' : 'contour'} taille="sm" icone={<Star aria-hidden="true" className={cx('size-4', choisi.suivi && 'fill-current')} />} onClick={() => onSuivre(choisi)}>
              {choisi.suivi ? 'Canal suivi' : 'Suivre ce canal'}
            </Bouton>
          ) : null}
          <Bouton variante="fantome" taille="sm" icone={<Plus aria-hidden="true" className="size-4" />} onClick={onCreer}>
            Créer un canal
          </Bouton>
        </div>
      </div>
    </div>
  )
}

/** Un canal et son étoile : suivi, ses discussions passent en premier dans le fil « Pour moi ». */
function LigneCanal({ canal, onSuivre, children }: { canal: CanalListe; onSuivre: (canal: CanalListe) => void; children: ReactNode }) {
  return (
    <div className="flex items-center gap-0.5">
      <div className="flex min-w-0 flex-1 flex-col">{children}</div>
      <button
        type="button"
        aria-pressed={canal.suivi}
        aria-label={canal.suivi ? `Ne plus suivre #${canal.slug}` : `Suivre #${canal.slug}`}
        title={canal.suivi ? 'Canal suivi' : 'Suivre ce canal'}
        onClick={() => onSuivre(canal)}
        className={cx(
          'grid size-9 shrink-0 place-items-center rounded-petit transition-colors duration-150 hover:bg-surface-douce',
          canal.suivi ? 'text-alerte' : 'text-texte-doux hover:text-titre',
        )}
      >
        <Star aria-hidden="true" className={cx('size-4', canal.suivi && 'fill-current')} />
      </button>
    </div>
  )
}

function BoutonCanal({ actif, onClick, children }: { actif: boolean; onClick: () => void; children: ReactNode }) {
  return (
    <button
      type="button"
      aria-current={actif ? 'true' : undefined}
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

const raccourcis: { etiquette: Etiquette; libelle: string; icone: typeof Bug; ton: string }[] = [
  { etiquette: 'bug', libelle: 'Je suis bloqué', icone: Bug, ton: 'text-danger' },
  { etiquette: 'question', libelle: 'Une question', icone: CircleHelp, ton: 'text-info' },
  { etiquette: 'projet', libelle: 'Un projet', icone: Rocket, ton: 'text-ia' },
  { etiquette: 'tuto', libelle: 'Un tuto', icone: BookOpen, ton: 'text-succes' },
]

/** La porte d'entrée du fil : une invitation à écrire, et un bouton par intention. */
function Composeur({ nom, avatar, onEcrire }: { nom: string | null; avatar: string | null; onEcrire: (etiquette?: Etiquette) => void }) {
  return (
    <Carte className="flex flex-col gap-3">
      <div className="flex items-center gap-3">
        <Avatar nom={nom ?? 'Vous'} url={avatar} taille="md" />
        <button
          type="button"
          onClick={() => onEcrire()}
          className="flex min-h-12 flex-1 items-center rounded-pastille border border-bordure-champ bg-surface-douce px-4 text-left text-corps text-texte-doux transition-colors duration-150 hover:border-primaire hover:text-titre"
        >
          Sur quoi bloquez-vous ? Un bug, une question, un projet…
        </button>
      </div>
      <div className="flex flex-wrap gap-2">
        {raccourcis.map(({ etiquette, libelle, icone: Icone, ton }) => (
          <button
            key={etiquette}
            type="button"
            onClick={() => onEcrire(etiquette)}
            className="inline-flex min-h-11 items-center gap-2 rounded-pastille border border-bordure px-3.5 text-petit font-medium text-titre transition-colors duration-150 hover:border-bordure-champ hover:bg-surface-douce tablette:min-h-10"
          >
            <Icone aria-hidden="true" className={cx('size-4', ton)} />
            {libelle}
          </button>
        ))}
      </div>
    </Carte>
  )
}

const etapesEchange = [
  { titre: 'Postez ce qui bloque', texte: 'L’erreur, ce que vous attendiez, et le lien de votre dépôt GitHub.' },
  { titre: 'Un dev propose sa branche', texte: 'Il corrige dans son fork et vous envoie l’adresse de la branche.' },
  { titre: 'Essayez, puis acceptez', texte: 'Lancez la version corrigée sur une copie de test, avant d’accepter.' },
]

/** Colonne de droite (grands écrans) : le mode d'emploi, des devs prêts à aider, ce qui se prépare. */
function ColonneAide({ connecte, moiId }: { connecte: boolean; moiId: number | null }) {
  const devs = useQuery({ queryKey: ['devs', 'disponibles'], queryFn: () => listerDevs({ disponible: 'true' }), staleTime: 60_000 })
  const evenements = useQuery({ queryKey: ['evenements', 1], queryFn: () => listerEvenements(1), staleTime: 60_000 })
  const [maintenant] = useState(() => Date.now())
  const prochains = (evenements.data?.data ?? [])
    .filter((evenement) => new Date(evenement.date).getTime() >= maintenant)
    .sort((a, b) => new Date(a.date).getTime() - new Date(b.date).getTime())
    .slice(0, 3)

  return (
    <aside aria-label="Aide et communauté" className="hidden w-76 shrink-0 flex-col gap-4 min-[90rem]:sticky min-[90rem]:top-24 min-[90rem]:flex">
      <Carte className="flex flex-col gap-3">
        <h2 className="m-0 flex items-center gap-2 text-corps font-semibold text-titre">
          <GitPullRequestArrow aria-hidden="true" className="size-4 text-sur-primaire-doux" />
          Comment ça marche
        </h2>
        <ol className="m-0 flex list-none flex-col gap-3 p-0">
          {etapesEchange.map((etape, index) => (
            <li key={etape.titre} className="flex gap-3">
              <span aria-hidden="true" className="chiffres grid size-6 shrink-0 place-items-center rounded-pastille bg-surface-accent text-petit font-semibold text-sur-primaire-doux">
                {index + 1}
              </span>
              <span className="flex flex-col">
                <span className="text-petit font-medium text-titre">{etape.titre}</span>
                <span className="text-petit text-texte-doux">{etape.texte}</span>
              </span>
            </li>
          ))}
        </ol>
      </Carte>

      <Carte className="flex flex-col gap-3">
        <h2 className="m-0 flex items-center gap-2 text-corps font-semibold text-titre">
          <Users aria-hidden="true" className="size-4 text-sur-primaire-doux" />
          Devs disponibles
        </h2>
        {devs.isPending ? <Squelette className="h-24 rounded-moyen" /> : null}
        <ul className="m-0 flex list-none flex-col gap-2.5 p-0">
          {(devs.data?.data ?? []).filter((dev) => dev.id !== moiId).slice(0, 4).map((dev) => (
            <li key={dev.id}>
              <Link to={`/devs/${dev.username}`} className="flex items-center gap-2.5 rounded-moyen no-underline hover:bg-surface-douce">
                <Avatar nom={dev.nom || dev.username || 'Dev'} url={dev.avatar_url} taille="sm" />
                <span className="flex min-w-0 flex-col leading-tight">
                  <span className="truncate text-petit font-medium text-titre">{dev.nom || dev.username}</span>
                  <span className="truncate text-petit text-texte-doux">
                    {[dev.pays ? nomPays(dev.pays) : null, ...(dev.competences ?? []).slice(0, 2)].filter(Boolean).join(' · ')}
                  </span>
                </span>
              </Link>
            </li>
          ))}
        </ul>
        <p className="m-0 text-petit text-texte-doux">
          {connecte ? (
            <>
              Depuis son profil, invitez un dev à échanger en privé.{' '}
              <Link to="/messages" className="inline-flex items-center gap-1 font-medium">
                <Mail aria-hidden="true" className="size-3.5" />
                Mes messages
              </Link>
            </>
          ) : (
            'Connecté, vous pouvez inviter un dev à échanger en privé.'
          )}
        </p>
        <Link to="/devs" className="inline-flex items-center gap-1 text-petit font-medium">
          Trouver un dev <ArrowRight aria-hidden="true" className="size-3.5" />
        </Link>
      </Carte>

      {prochains.length > 0 ? (
        <Carte className="flex flex-col gap-3">
          <h2 className="m-0 flex items-center gap-2 text-corps font-semibold text-titre">
            <CalendarDays aria-hidden="true" className="size-4 text-sur-primaire-doux" />
            Prochains événements
          </h2>
          <ul className="m-0 flex list-none flex-col gap-2.5 p-0">
            {prochains.map((evenement) => (
              <li key={evenement.id} className="flex flex-col">
                <Link to="/evenements" className="text-petit font-medium text-titre no-underline hover:underline">
                  {evenement.titre}
                </Link>
                <span className="text-petit text-texte-doux">
                  {formaterDate(evenement.date)} · {evenement.type === 'en_ligne' ? 'En ligne' : evenement.lieu}
                </span>
              </li>
            ))}
          </ul>
        </Carte>
      ) : null}
    </aside>
  )
}
