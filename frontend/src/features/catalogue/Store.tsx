import { useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { useInfiniteQuery } from '@tanstack/react-query'
import { Search, ShieldAlert, ShieldCheck, SlidersHorizontal } from 'lucide-react'
import { useTitrePage } from '../../app/titre'
import { categories, fcfa, libelleCategorie, listerApplis, type Appli } from './api'
import { nomPays } from '../echange/texte'
import { messageAction } from '../echange/api'
import {
  Badge,
  Bouton,
  CaseACocher,
  Champ,
  EnTetePage,
  EtatErreur,
  EtatVide,
  Etoiles,
  Interrupteur,
  LienBouton,
  Liste,
  Saisie,
  SqueletteCartes,
  cx,
  formaterNote,
} from '../../ui'

type PageStore = {
  data: Appli[]
  meta: { current_page: number; last_page: number; per_page: number; total: number }
}

/** Filtres repliés par défaut (loi de Hick) : la recherche, le tri et les catégories suffisent souvent. */
const FILTRES_AVANCES = ['pays', 'prix_max', 'type_prix', 'gratuit', 'securite'] as const

/**
 * Store : trouver l'appli qui fera tourner son activité. Recherche, tri et catégories visibles,
 * filtres avancés repliés ; chaque carte mène à la fiche (une seule cible par carte).
 */
export function Store() {
  useTitrePage('Store')
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
  })

  function changer(cle: string, valeur: string) {
    const suivant = new URLSearchParams(params)
    if (valeur) suivant.set(cle, valeur)
    else suivant.delete(cle)
    setParams(suivant, { replace: cle === 'q' })
  }

  const liste = applis.data?.pages.flatMap((lot) => lot.data) ?? []
  const total = applis.data?.pages[0] ? Math.max(applis.data.pages[0].meta.total, liste.length) : 0

  return (
    <div className="mx-auto flex w-full max-w-site flex-col gap-6 px-4 py-8 tablette:px-6 bureau:px-8">
      <EnTetePage
        titre="Store"
        description="Des applis publiées par des devs africains, prêtes à tourner pour vous : chaque client a sa copie isolée, payée en FCFA."
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
                  value={filtres.q}
                  placeholder="Stock, paiement, réservation…"
                  onChange={(evenement) => changer('q', evenement.target.value)}
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

        <div role="group" aria-label="Catégorie" className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 tablette:mx-0 tablette:flex-wrap tablette:px-0">
          {[{ valeur: '', libelle: 'Toutes' }, ...categories].map((item) => {
            const actif = filtres.categorie === item.valeur
            return (
              <button
                key={item.valeur || 'toutes'}
                type="button"
                aria-pressed={actif}
                onClick={() => changer('categorie', item.valeur)}
                className={cx(
                  'inline-flex h-11 shrink-0 items-center rounded-pastille border px-4 text-petit font-medium transition-colors duration-150 tablette:h-9',
                  actif ? 'border-primaire bg-primaire text-sur-couleur' : 'border-bordure bg-surface text-texte hover:border-bordure-champ hover:text-titre',
                )}
              >
                {item.libelle}
              </button>
            )
          })}
        </div>

        {ouverts ? (
          <div id="filtres-avances" className="grid gap-4 rounded-moyen border border-bordure bg-surface p-4 tablette:grid-cols-3 tablette:p-5">
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
                libelle="Badge Security Checked seulement"
              />
            </div>
          </div>
        ) : null}

        <div className="flex min-h-11 flex-wrap items-center justify-between gap-3">
          <p role="status" className="m-0 text-petit text-texte-doux">
            {applis.isSuccess ? `${total} appli${total > 1 ? 's' : ''}${filtre ? ' pour ces critères' : ''}` : ''}
          </p>
          {filtre ? (
            <Bouton variante="fantome" taille="sm" onClick={() => setParams({})}>
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
            action={<Bouton onClick={() => setParams({})}>Réinitialiser les filtres</Bouton>}
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
        <ul className="m-0 grid list-none gap-5 p-0 tablette:grid-cols-2 bureau:grid-cols-4">
          {liste.map((appli) => (
            <li key={appli.id} className="flex">
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
          <h2 className="m-0 text-titre-3 text-titre">Vous êtes dev ? Publiez votre appli</h2>
          <p className="m-0 text-texte">
            Liez votre dépôt, ajoutez un soukdev.json et fixez votre prix. Publier ne coûte rien, et Souk.dev ne prend aucune commission. Besoin d’une version sur mesure ? <Link to="/devs" className="underline underline-offset-2">Trouvez un dev</Link>.
          </p>
        </div>
        <LienBouton to="/publier" variante="contour" className="shrink-0">
          Publier une appli
        </LienBouton>
      </section>
    </div>
  )
}

function BadgeSecurite({ statut }: { statut: Appli['securite']['statut'] }) {
  if (statut === 'verifie') {
    return <Badge ton="succes" icone={<ShieldCheck aria-hidden="true" className="size-3.5" />}>Security Checked</Badge>
  }
  if (statut === 'problemes') {
    return <Badge ton="alerte" icone={<ShieldAlert aria-hidden="true" className="size-3.5" />}>Problèmes détectés</Badge>
  }
  return <Badge>{statut === 'en_cours' ? 'Analyse en cours' : 'Analyse impossible'}</Badge>
}

/** Une appli : capture, nom (seul lien, qui couvre la carte), auteur, note, description, prix. */
function CarteAppli({ appli }: { appli: Appli }) {
  const prix = appli.prix === 0 ? 'Gratuit' : fcfa(appli.prix)
  return (
    <article className="group relative flex w-full flex-col overflow-hidden rounded-moyen border border-bordure bg-surface shadow-1 transition-[box-shadow,border-color,transform] duration-200 ease-souk hover:-translate-y-0.5 hover:border-bordure-champ hover:shadow-3 has-[a:focus-visible]:outline-3 has-[a:focus-visible]:outline-offset-2 has-[a:focus-visible]:outline-lien">
      {appli.captures[0] ? (
        <img
          src={appli.captures[0].url}
          alt=""
          loading="lazy"
          className="aspect-video w-full border-b border-bordure bg-surface-douce object-cover object-top"
        />
      ) : (
        <div aria-hidden="true" className="grid aspect-video place-items-center border-b border-bordure bg-surface-accent text-titre-2 text-sur-primaire-doux">
          {appli.nom.slice(0, 2).toUpperCase()}
        </div>
      )}
      <div className="flex flex-1 flex-col gap-3 p-4">
        <div className="flex flex-wrap gap-1.5">
          <Badge ton="marque">{libelleCategorie(appli.categorie)}</Badge>
          <BadgeSecurite statut={appli.securite.statut} />
        </div>
        <div className="flex flex-col gap-0.5">
          <h2 className="m-0 text-titre-3">
            <Link to={`/store/${appli.id}`} className="text-titre no-underline after:absolute after:inset-0 after:content-[''] focus-visible:outline-none">
              {appli.nom}
            </Link>
          </h2>
          <p className="m-0 text-petit text-texte-doux">par {appli.auteur.nom || appli.auteur.username || 'un dev'}</p>
        </div>
        <p className="m-0 line-clamp-3 flex-1 text-corps text-texte">{appli.description}</p>
        <div className="flex items-center justify-between gap-2 border-t border-bordure pt-3">
          <p className="m-0 text-corps font-semibold text-titre">
            <span className="chiffres">{prix}</span>
            {appli.prix > 0 && appli.type_prix === 'mensuel' ? <span className="font-normal text-texte-doux"> / mois</span> : null}
          </p>
          {appli.note_moyenne !== null ? (
            <p className="m-0 flex items-center gap-1.5 text-petit text-texte-doux">
              <Etoiles note={appli.note_moyenne} taille="sm" />
              <span aria-hidden="true" className="font-medium text-titre">{formaterNote(appli.note_moyenne)}</span>
              <span>({appli.nb_avis})</span>
            </p>
          ) : null}
        </div>
      </div>
    </article>
  )
}
