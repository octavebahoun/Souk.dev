import type { ReactNode } from 'react'
import { Link, useSearchParams } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { categories, fcfa, libelleCategorie, listerApplis, type Appli } from './api'
import { Fenetre } from './Fenetre'
import { teintes } from './teintes'
import { nomPays } from '../echange/texte'

const page = 'mx-auto flex max-w-[1200px] flex-col gap-6 px-4 py-8 min-[761px]:px-6'

export function Store() {
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
  const applis = useQuery({
    queryKey: ['applis', filtres],
    queryFn: () =>
      listerApplis({
        q: filtres.q || undefined,
        categorie: filtres.categorie || undefined,
        pays: filtres.pays || undefined,
        prix_max: filtres.prix_max ? Number(filtres.prix_max) : undefined,
        type_prix: filtres.type_prix || undefined,
        tri: filtres.tri,
        gratuit: filtres.gratuit || undefined,
        securite: filtres.securite || undefined,
      }),
  })

  function changer(cle: string, valeur: string) {
    const suivant = new URLSearchParams(params)
    if (valeur) suivant.set(cle, valeur)
    else suivant.delete(cle)
    setParams(suivant)
  }

  const liste = applis.data?.data ?? []
  const total = applis.data ? Math.max(applis.data.meta.total, liste.length) : 0

  return (
    <div>
      <main className={page}>
        <div className="flex flex-col gap-4 min-[761px]:flex-row min-[761px]:items-end min-[761px]:justify-between">
          <div>
            <h1 className="m-0 text-[30px] font-bold tracking-tight">Store</h1>
            <p className="m-0 max-w-[62ch] text-muted">
              Des applis complètes, faites par des devs africains. Testez la démo, puis déployez votre propre copie en un clic.
            </p>
          </div>
          <Link to="/publier" className="inline-flex min-h-cible w-full items-center justify-center rounded-controle bg-accent px-5 font-semibold text-canvas no-underline min-[761px]:w-auto">
            + Publier une appli
          </Link>
        </div>

        <form className="verre flex flex-col gap-3.5 rounded-[14px] p-[18px]" onSubmit={(evenement) => evenement.preventDefault()}>
          <input
            type="search"
            value={filtres.q}
            aria-label="Rechercher une appli"
            placeholder="Rechercher : stock, paiement, réservation…"
            onChange={(evenement) => changer('q', evenement.target.value)}
            className="min-h-cible rounded-controle border border-line bg-canvas px-3.5"
          />
          <div className="grid gap-3 min-[761px]:grid-cols-5">
            <Champ label="Catégorie">
              <select className="champ-select" value={filtres.categorie} onChange={(e) => changer('categorie', e.target.value)}>
                <option value="">Toutes</option>
                {categories.map((item) => (
                  <option key={item.valeur} value={item.valeur}>{item.libelle}</option>
                ))}
              </select>
            </Champ>
            <Champ label="Pays de l'auteur">
              <select className="champ-select" value={filtres.pays} onChange={(e) => changer('pays', e.target.value)}>
                <option value="">Tous</option>
                {['BJ', 'CM', 'CI', 'SN', 'TG'].map((code) => (
                  <option key={code} value={code}>{nomPays(code)}</option>
                ))}
              </select>
            </Champ>
            <Champ label="Prix maximum">
              <select className="champ-select" value={filtres.prix_max} onChange={(e) => changer('prix_max', e.target.value)}>
                <option value="">Sans limite</option>
                <option value="10000">10 000 F</option>
                <option value="20000">20 000 F</option>
                <option value="100000">100 000 F</option>
              </select>
            </Champ>
            <Champ label="Type de prix">
              <select className="champ-select" value={filtres.type_prix} onChange={(e) => changer('type_prix', e.target.value)}>
                <option value="">Tous</option>
                <option value="mensuel">Mensuel</option>
                <option value="unique">Paiement unique</option>
              </select>
            </Champ>
            <Champ label="Trier par">
              <select className="champ-select" value={filtres.tri} onChange={(e) => changer('tri', e.target.value)}>
                <option value="recent">Les plus récentes</option>
                <option value="prix_croissant">Prix croissant</option>
                <option value="prix_decroissant">Prix décroissant</option>
              </select>
            </Champ>
          </div>
          <div className="flex flex-wrap items-center gap-4">
            <button type="button" className="min-h-cible font-semibold" aria-pressed={filtres.gratuit} onClick={() => changer('gratuit', filtres.gratuit ? '' : '1')}>
              {filtres.gratuit ? '●' : '○'} Gratuites uniquement
            </button>
            <label className="inline-flex min-h-cible items-center gap-2 font-semibold">
              <input type="checkbox" checked={filtres.securite} onChange={() => changer('securite', filtres.securite ? '' : '1')} />
              Security Checked seulement
            </label>
            <span role="status" className="ml-auto text-sm text-muted">{applis.isSuccess ? `${total} appli${total > 1 ? 's' : ''}` : ''}</span>
          </div>
        </form>

        {applis.isPending ? <div role="status" aria-label="Chargement du store" className="squelette h-64 rounded-[18px]" /> : null}
        {applis.isError ? (
          <button type="button" onClick={() => applis.refetch()} className="min-h-cible w-fit rounded-controle bg-accent px-4 font-semibold text-canvas">Réessayer</button>
        ) : null}
        {applis.isSuccess && liste.length === 0 ? (
          <div className="verre rounded-[14px] border-dashed px-5 py-10 text-center">
            <strong>Aucune appli ne correspond à ces filtres</strong>
            <p className="m-0 text-subtle">Essayez un autre mot, ou élargissez les filtres.</p>
            <button type="button" onClick={() => setParams({})} className="mt-3 min-h-cible rounded-controle bg-accent px-5 font-semibold text-canvas">Réinitialiser les filtres</button>
          </div>
        ) : null}
        <ul className="m-0 grid list-none gap-[18px] p-0 min-[761px]:grid-cols-2 min-[1100px]:grid-cols-4">
          {liste.map((appli) => (
            <Carte key={appli.id} appli={appli} />
          ))}
        </ul>

        <section className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-[#C9D7FB] bg-nav p-6">
          <div>
            <h2 className="m-0 text-[19px]">Vous êtes dev ? Publiez votre appli</h2>
            <p className="m-0 text-nav-texte">Liez votre dépôt, ajoutez un soukdev.json et fixez votre prix. Publier ne coûte rien, et Souk.dev ne prend aucune commission.</p>
          </div>
          <Link to="/publier" className="inline-flex min-h-cible items-center rounded-controle bg-accent px-5 font-semibold text-canvas no-underline">Publier une appli</Link>
        </section>
        <section className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-line bg-canvas p-6">
          <div className="max-w-[62ch]">
            <h2 className="m-0 text-[19px]">Agences et entreprises : déployez l’appli pour vous</h2>
            <p className="m-0 text-muted">Testez la démo, puis lancez votre copie isolée. Besoin d’une version adaptée ? Trouvez le dev et proposez-lui une mission.</p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Link to="/store" className="inline-flex min-h-cible items-center rounded-controle bg-ink px-4 font-semibold text-canvas no-underline">Voir les applis</Link>
            <Link to="/devs" className="inline-flex min-h-cible items-center rounded-controle border border-line px-4 font-semibold no-underline">Demander une mission</Link>
          </div>
        </section>
      </main>
    </div>
  )
}

function Champ({ label, children }: { label: string; children: ReactNode }) {
  return (
    <label className="flex flex-col gap-1.5 text-[13.5px] font-semibold">
      {label}
      {children}
    </label>
  )
}

function Carte({ appli }: { appli: Appli }) {
  const teinte = teintes[appli.categorie]
  const prix = appli.prix === 0 ? 'Gratuit' : `${fcfa(appli.prix)}${appli.type_prix === 'mensuel' ? '/mois' : ''}`
  return (
    <li className="appli-carte flex overflow-hidden rounded-[18px] border border-white/60 bg-[rgba(246,243,237,0.72)]">
      <article className="flex flex-1 flex-col">
        <Link to={`/store/${appli.id}`} aria-label={`Voir la fiche de ${appli.nom}`} className="no-underline">
          <Fenetre nom={appli.nom} categorie={appli.categorie} />
        </Link>
        <div className="flex flex-1 flex-col gap-3 p-[18px]">
          <div className="flex flex-wrap gap-1.5">
            <span className="rounded-full bg-chip px-2 py-0.5 text-[12.5px] font-semibold text-nav-texte">{libelleCategorie(appli.categorie)}</span>
            {appli.securite.statut === 'verifie' ? <span className="rounded-full bg-[#DCFCE7] px-2 py-0.5 text-[12.5px] font-semibold text-[#166534]">Security Checked</span> : null}
            {appli.securite.statut === 'problemes' ? <span className="rounded-full bg-[#FFEDD5] px-2 py-0.5 text-[12.5px] font-semibold text-[#9A3412]">Problèmes détectés</span> : null}
            {appli.securite.statut === 'en_cours' ? <span className="rounded-full bg-page px-2 py-0.5 text-[12.5px] font-semibold text-subtle">Analyse en cours</span> : null}
          </div>
          <div className="flex items-center gap-3">
            <span className="grid size-11 place-items-center rounded-xl text-[15px] font-bold" style={{ background: teinte.fond, color: teinte.texte }}>
              {appli.nom.slice(0, 2).toUpperCase()}
            </span>
            <div className="min-w-0">
              <Link to={`/store/${appli.id}`} className="text-[17px] font-bold text-ink no-underline">{appli.nom}</Link>
              <p className="m-0 text-[13px] text-muted">par {appli.auteur.nom || appli.auteur.username || 'un dev'}</p>
            </div>
          </div>
          <p className="m-0 line-clamp-3 flex-1 text-[14.5px] text-subtle">{appli.description}</p>
          <div className="flex items-center justify-between gap-2 border-t border-ink/10 pt-3">
            <strong>{prix}</strong>
            <Link to={`/store/${appli.id}`} className="inline-flex min-h-10 items-center font-semibold">Voir la fiche →</Link>
          </div>
        </div>
      </article>
    </li>
  )
}
