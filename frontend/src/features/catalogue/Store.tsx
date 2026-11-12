import { useEffect, useRef, useState, type CSSProperties } from 'react'
import { Link, useSearchParams } from 'react-router'
import { keepPreviousData, useInfiniteQuery } from '@tanstack/react-query'
import { Search, SlidersHorizontal } from 'lucide-react'
import { useTitrePage } from '../../app/titre'
import { categories, listerApplis, type Appli } from './api'
import { CarteAppli } from './CarteAppli'
import { nomPays } from '../echange/texte'
import { messageAction } from '../echange/api'
import {
  Bouton,
  CaseACocher,
  Champ,
  EnTetePage,
  EtatErreur,
  EtatVide,
  Interrupteur,
  LienBouton,
  Liste,
  Saisie,
  ChoixPastilles,
  SqueletteCartes,
  cx,
} from '../../ui'

type PageStore = {
  data: Appli[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

const delai = (ms: number) => ({ '--delai': `${ms}ms` }) as CSSProperties

/** Filtres repliés par défaut (loi de Hick) : la recherche, le tri et les catégories suffisent souvent. */
const FILTRES_AVANCES = ['pays', 'prix_max', 'type_prix', 'gratuit', 'securite'] as const

/**
 * Store : trouver l'appli qui fera tourner son activité. Recherche, tri et catégories visibles,
 * filtres avancés repliés ; chaque carte mène à la fiche (une seule cible par carte).
 */
export function Store() {
  useTitrePage('Store', { description: 'Des applis web prêtes à l’emploi, créées par des devs africains : essayez la démo, déployez votre copie en un clic, payez en Mobile Money.' })
  const [params, setParams] = useSearchParams()
  const filtres = {
    q: params.get('q') ?? '',
    categorie: params.get('categorie') ?? '',
    pays: params.get('pays') ?? '',
    prix_max: params.get('prix_max') ?? '',
    type_prix: params.get('type_prix') ?? '',
    tri: params.get('tri') ?? 'recent',
    gratuit: params.get('gratuit') === '1',
    securite: params.get('securite') === '1',
  }
  const avancesActifs = FILTRES_AVANCES.filter((cle) => params.get(cle)).length
  const [ouverts, setOuverts] = useState(avancesActifs > 0)
  const filtre = Boolean(filtres.q || filtres.categorie || avancesActifs > 0)
  // La saisie s'affiche tout de suite ; l'adresse (donc la requête) suit 300 ms après la dernière
  // lettre : une requête par mot, pas par lettre, sur un réseau mobile lent.
  const [saisie, setSaisie] = useState(filtres.q)
  const [qConnu, setQConnu] = useState(filtres.q)
  const attente = useRef<number | undefined>(undefined)
  if (filtres.q !== qConnu) {
    // Recherche lancée depuis l'en-tête, retour arrière : la saisie reprend l'adresse.
    setQConnu(filtres.q)
    setSaisie(filtres.q)
  }
  useEffect(() => () => window.clearTimeout(attente.current), [])

  const applis = useInfiniteQuery<PageStore>({
    queryKey: ['applis', filtres],
    queryFn: async ({ pageParam }) => {
      const numeroPage = Number(pageParam)
      return listerApplis({
        q: filtres.q || undefined,
        categorie: filtres.categorie || undefined,
        pays: filtres.pays || undefined,
        prix_max: filtres.prix_max ? Number(filtres.prix_max) : undefined,
        type_prix: filtres.type_prix || undefined,
        tri: filtres.tri,
        gratuit: filtres.gratuit || undefined,
        securite: filtres.securite || undefined,
        page: numeroPage > 1 ? numeroPage : undefined,
      })
    },
    initialPageParam: 1,
    getNextPageParam: (derniere) =>
      derniere.meta.current_page < derniere.meta.last_page ? derniere.meta.current_page + 1 : undefined,
    // Pendant une nouvelle recherche, les résultats précédents restent (atténués) au lieu d'un squelette.
    placeholderData: keepPreviousData,
  })

  function changer(cle: string, valeur: string) {
    setParams(
      (courants) => {
        const suivant = new URLSearchParams(courants)
        if (valeur) suivant.set(cle, valeur)
        else suivant.delete(cle)
        return suivant
      },
      { replace: cle === 'q' },
    )
  }

  function chercher(valeur: string) {
    setSaisie(valeur)
    window.clearTimeout(attente.current)
    attente.current = window.setTimeout(() => changer('q', valeur), 300)
  }

  function reinitialiser() {
    window.clearTimeout(attente.current)
    setSaisie('')
    setParams({})
  }

  const liste = applis.data?.pages.flatMap((lot) => lot.data) ?? []
  const total = applis.data?.pages[0] ? Math.max(applis.data.pages[0].meta.total, liste.length) : 0

  return (
    <div className="mx-auto flex w-full max-w-site flex-col gap-6 px-4 py-8 tablette:px-6 bureau:px-8">
      <EnTetePage
        titre="Store"
        description="Des applis publiées par des devs africains, prêtes à tourner pour vous : chaque client a sa copie isolée, payée en FCFA."
      />

      <section aria-label="Recherche et filtres" className="flex flex-col gap-4">
        <div className="flex flex-col gap-3 tablette:flex-row tablette:items-end">
          <Champ libelle="Rechercher une appli" libelleCache className="flex-1">
            {(liaison) => (
              <div className="relative">
                <Search aria-hidden="true" className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-texte-indice" />
                <Saisie
                  {...liaison}
                  type="search"
                  value={saisie}
                  placeholder="Stock, paiement, réservation…"
                  onChange={(evenement) => chercher(evenement.target.value)}
                  className="pl-9"
                />
              </div>
            )}
          </Champ>
          <Champ libelle="Trier par" libelleCache className="tablette:w-56">
            {(liaison) => (
              <Liste {...liaison} value={filtres.tri} onChange={(evenement) => changer('tri', evenement.target.value)}>
                <option value="recent">Les plus récentes</option>
                <option value="note">Les mieux notées</option>
                <option value="prix_croissant">Prix croissant</option>
                <option value="prix_decroissant">Prix décroissant</option>
              </Liste>
            )}
          </Champ>
          <Bouton
            variante="secondaire"
            icone={<SlidersHorizontal aria-hidden="true" className="size-4" />}
            aria-expanded={ouverts}
            aria-controls="filtres-avances"
            onClick={() => setOuverts(!ouverts)}
          >
            Filtres{avancesActifs > 0 ? ` (${avancesActifs})` : ''}
          </Bouton>
        </div>

        <ChoixPastilles
          libelle="Catégorie"
          valeur={filtres.categorie}
          onChange={(categorie) => changer('categorie', categorie)}
          options={[{ valeur: '', libelle: 'Toutes' }, ...categories]}
          className="-mx-4 flex-nowrap overflow-x-auto px-4 pb-1 tablette:mx-0 tablette:flex-wrap tablette:px-0"
        />

        {ouverts ? (
          <div id="filtres-avances" className="apparition-fondu grid gap-4 rounded-moyen border border-bordure bg-surface p-4 tablette:grid-cols-3 tablette:p-5">
            <Champ libelle="Pays de l’auteur">
              {(liaison) => (
                <Liste {...liaison} value={filtres.pays} onChange={(evenement) => changer('pays', evenement.target.value)}>
                  <option value="">Tous</option>
                  {['BJ', 'CM', 'CI', 'SN', 'TG'].map((code) => (
                    <option key={code} value={code}>{nomPays(code)}</option>
                  ))}
                </Liste>
              )}
            </Champ>
            <Champ libelle="Prix maximum">
              {(liaison) => (
                <Liste {...liaison} value={filtres.prix_max} onChange={(evenement) => changer('prix_max', evenement.target.value)}>
                  <option value="">Sans limite</option>
                  <option value="10000">10 000 F</option>
                  <option value="20000">20 000 F</option>
                  <option value="100000">100 000 F</option>
                  <option value="200000">200 000 F</option>
                </Liste>
              )}
            </Champ>
            <Champ libelle="Type de prix">
              {(liaison) => (
                <Liste {...liaison} value={filtres.type_prix} onChange={(evenement) => changer('type_prix', evenement.target.value)}>
                  <option value="">Tous</option>
                  <option value="mensuel">Mensuel</option>
                  <option value="unique">Paiement unique</option>
                </Liste>
              )}
            </Champ>
            <div className="tablette:col-span-3 flex flex-col gap-1 border-t border-bordure pt-3 tablette:flex-row tablette:items-center tablette:gap-8">
              <div className="tablette:w-64">
                <Interrupteur actif={filtres.gratuit} onChange={(actif) => changer('gratuit', actif ? '1' : '')} libelle="Gratuites uniquement" />
              </div>
              <CaseACocher
                checked={filtres.securite}
                onChange={() => changer('securite', filtres.securite ? '' : '1')}
                libelle="Sécurité vérifiée seulement"
              />
            </div>
          </div>
        ) : null}

        <div className="flex min-h-11 flex-wrap items-center justify-between gap-3">
          <p role="status" className="m-0 text-petit text-texte-doux">
            {applis.isSuccess ? `${total} appli${total > 1 ? 's' : ''}${filtre ? ' pour ces critères' : ''}` : ''}
          </p>
          {filtre ? (
            <Bouton variante="fantome" taille="sm" onClick={reinitialiser}>
              Réinitialiser
            </Bouton>
          ) : null}
        </div>
      </section>

      {applis.isPending ? <SqueletteCartes nombre={8} /> : null}
      {applis.isError ? (
        <EtatErreur description={messageAction(applis.error, 'Le Store n’a pas pu charger les applis.')} onReessayer={() => void applis.refetch()} />
      ) : null}
      {applis.isSuccess && liste.length === 0 ? (
        filtre ? (
          <EtatVide
            illustration="vide-recherche"
            titre="Aucune appli ne correspond à ces filtres"
            description="Essayez un autre mot ou élargissez les filtres."
            action={<Bouton onClick={reinitialiser}>Réinitialiser les filtres</Bouton>}
          />
        ) : (
          <EtatVide
            illustration="vide-store"
            titre="Aucune appli publiée pour le moment"
            description="Les applications apparaîtront ici après leur publication depuis un dépôt GitHub public."
            action={<LienBouton to="/publier">Publier une appli</LienBouton>}
          />
        )
      ) : null}

      {liste.length > 0 ? (
        <ul
          aria-busy={applis.isPlaceholderData}
          className={cx('m-0 grid list-none gap-5 p-0 transition-opacity duration-150 tablette:grid-cols-2 bureau:grid-cols-4', applis.isPlaceholderData && 'opacity-60')}
        >
          {liste.map((appli, index) => (
            <li key={appli.id} style={delai(Math.min(index, 11) * 50)} className="apparition flex">
              <CarteAppli appli={appli} />
            </li>
          ))}
        </ul>
      ) : null}
      {applis.hasNextPage ? (
        <Bouton variante="secondaire" className="self-center" chargement={applis.isFetchingNextPage} onClick={() => void applis.fetchNextPage()}>
          Afficher plus d’applis
        </Bouton>
      ) : null}

      <section className="mt-4 flex flex-col gap-4 rounded-moyen bg-surface-accent p-5 tablette:flex-row tablette:items-center tablette:justify-between tablette:p-7">
        <div className="flex max-w-prose flex-col gap-1">
          <h2 className="m-0 text-titre-3 text-titre">Vous êtes dev ? Publiez votre appli</h2>
          <p className="m-0 text-texte">
            Liez votre dépôt, ajoutez un soukdev.json et fixez votre prix. Publier ne coûte rien, et Souk.dev ne prend aucune commission. Besoin d’une version sur mesure ? <Link to="/devs" className="underline underline-offset-2">Trouvez un dev</Link>.
          </p>
        </div>
        <LienBouton to="/publier" variante="contour" className="shrink-0">
          Publier une appli
        </LienBouton>
      </section>
    </div>
  )
}
