import { useState } from 'react'
import { Link, useParams } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { fcfa, libelleCategorie, lireAppli } from './api'
import { Fenetre } from './Fenetre'
import { nomPays } from '../echange/texte'

const hebergement = { petite: '3 500 F/mois', moyenne: '7 000 F/mois', grande: '13 500 F/mois' }

const ecrans = ['Accueil', 'Fiche', 'Panier', 'Paiement', 'Suivi']

export function Fiche() {
  const { id = '' } = useParams()
  const appli = useQuery({
    queryKey: ['appli', id],
    queryFn: () => lireAppli(Number(id)),
    enabled: Number.isFinite(Number(id)),
  })
  const [ecran, setEcran] = useState(0)

  if (appli.isPending) return <div role="status" aria-label="Chargement de la fiche" className="squelette mx-auto mt-8 h-80 max-w-[1200px] rounded-[18px]" />
  if (appli.isError || !appli.data) {
    return (
      <div className="mx-auto max-w-[1200px] px-4 py-10">
        <p role="alert">Cette appli est introuvable, ou le chargement a échoué.</p>
        <button type="button" onClick={() => appli.refetch()} className="min-h-cible rounded-controle bg-accent px-4 font-semibold text-canvas">Réessayer</button>
      </div>
    )
  }

  const fiche = appli.data
  const prix = fiche.prix === 0 ? 'Gratuit' : `${fcfa(fiche.prix)}${fiche.type_prix === 'mensuel' ? '/mois' : ''}`

  return (
    <div>
      <main className="mx-auto grid max-w-[1200px] gap-6 px-4 py-8 min-[861px]:grid-cols-[minmax(0,1fr)_320px] min-[861px]:px-6">
        <div className="flex flex-col gap-5">
          <p className="m-0 text-sm text-muted">
            <Link to="/store">Store</Link> / {fiche.nom}
          </p>
          <p className="m-0 text-xs font-bold tracking-wide text-accent uppercase">{libelleCategorie(fiche.categorie)}</p>
          <h1 className="m-0 text-4xl font-extrabold tracking-tight">{fiche.nom}</h1>
          <p className="m-0 max-w-[62ch] text-subtle">{fiche.description}</p>
          <p className="m-0 text-sm text-muted">
            par {fiche.auteur.username ? <Link to={`/devs/${fiche.auteur.username}`}>{fiche.auteur.nom || fiche.auteur.username}</Link> : fiche.auteur.nom}
            {fiche.auteur.pays ? ` · ${nomPays(fiche.auteur.pays)}` : ''}
          </p>
          <div className="rounded-2xl bg-[#F8F1E7] p-4">
            <div role="tablist" aria-label="Aperçu de l’appli" className="mb-3 flex flex-wrap gap-2">
              {ecrans.map((nom, index) => (
                <button key={nom} type="button" role="tab" aria-selected={ecran === index} onClick={() => setEcran(index)} className={`min-h-10 rounded-full px-3 text-sm font-semibold ${ecran === index ? 'bg-ink text-canvas' : 'bg-canvas text-subtle'}`}>
                  {index + 1}. {nom}
                </button>
              ))}
            </div>
            <p className="m-0 mb-3 text-center font-mono text-xs text-muted">{fiche.demo_url}</p>
            <Fenetre nom={`${fiche.nom} · ${ecrans[ecran]}`} categorie={fiche.categorie} />
            <p className="m-0 mt-2 text-center text-sm text-muted">Écran {ecran + 1} / {ecrans.length}. L’aperçu change avec l’onglet. La démo réelle s’ouvre avec « Tester la démo ».</p>
          </div>
          <section>
            <h2 className="text-xl">À propos</h2>
            <p className="whitespace-pre-wrap text-subtle">{fiche.readme || fiche.description}</p>
          </section>
          <section className="rounded-xl bg-ink p-4 font-mono text-sm text-[#E2E8F0]">
            <div>git clone {fiche.depot_url}</div>
            <div>cd {fiche.depot_url.split('/').pop()}</div>
          </section>
        </div>
        <aside className="flex h-fit flex-col gap-3 rounded-[18px] border border-line bg-canvas p-5">
          <strong className="text-2xl">{prix}</strong>
          <p className="m-0 text-sm text-muted">Hébergement {hebergement[fiche.taille]}. Commission Souk.dev : 0 F.</p>
          {fiche.securite.statut === 'verifie' ? <span className="w-fit rounded-full bg-[#DCFCE7] px-2 py-0.5 text-sm font-semibold text-[#166534]">Security Checked</span> : null}
          {fiche.securite.statut === 'problemes' ? <span className="w-fit rounded-full bg-[#FFEDD5] px-2 py-0.5 text-sm font-semibold text-[#9A3412]">Problèmes détectés</span> : null}
          <div className="flex flex-wrap gap-1.5">
            {fiche.stack.map((techno) => (
              <span key={techno} className="rounded-full bg-chip px-2 py-0.5 text-sm">{techno}</span>
            ))}
          </div>
          <a href={fiche.demo_url} className="inline-flex min-h-cible items-center justify-center rounded-controle border border-line font-semibold no-underline">Tester la démo</a>
          <Link to={`/store/${fiche.id}/deployer`} className="inline-flex min-h-cible items-center justify-center rounded-controle bg-ink font-semibold text-canvas no-underline">Déployer ma copie</Link>
          <a href={fiche.depot_url} className="text-sm">Dépôt GitHub</a>
        </aside>
      </main>
    </div>
  )
}
