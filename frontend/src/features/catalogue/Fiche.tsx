import { useState, type FormEvent } from 'react'
import { Link, useParams } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { fcfa, libelleCategorie, lireAppli, listerDiscussionsAppli, proposerMissionAppli } from './api'
import { teintes } from './teintes'
import { messageAction } from '../echange/api'
import { nomPays } from '../echange/texte'
import { Modal } from '../../shared/ui/Modal'
import { useCompte } from '../../app/compte'
import { RapportSecurite } from '../securite/RapportSecurite'

const hebergement = { petite: 3500, moyenne: 7000, grande: 13500 }
const tailleNom = { petite: 'Petite, 512 Mo', moyenne: 'Moyenne, 1 Go', grande: 'Grande, 2 Go' }

export function Fiche() {
  const compte = useCompte()
  const { id = '' } = useParams()
  const appli = useQuery({
    queryKey: ['appli', id],
    queryFn: () => lireAppli(Number(id)),
    enabled: Number.isFinite(Number(id)),
  })
  const discussions = useQuery({
    queryKey: ['discussions-appli', id],
    queryFn: () => listerDiscussionsAppli(Number(id)),
    enabled: appli.isSuccess,
  })
  const [ecran, setEcran] = useState(0)
  const [mission, setMission] = useState(false)

  if (appli.isPending) return <div role="status" aria-label="Chargement de la fiche" className="squelette mx-auto mt-8 h-80 max-w-[1440px] rounded-[18px]" />
  if (appli.isError || !appli.data) {
    return (
      <div className="mx-auto max-w-[1440px] px-4 py-10 min-[761px]:px-9">
        <p role="alert">{messageAction(appli.error, 'Cette appli est introuvable, ou le chargement a échoué.')}</p>
        <Link to="/store" className="inline-flex min-h-cible items-center font-semibold">Retour au Store</Link>
        <button type="button" onClick={() => appli.refetch()} className="min-h-cible rounded-controle bg-accent px-4 font-semibold text-canvas">Réessayer</button>
      </div>
    )
  }

  const fiche = appli.data
  const teinte = teintes[fiche.categorie]
  const host = hebergement[fiche.taille]
  const prix = fiche.prix === 0 ? 'Gratuit' : fcfa(fiche.prix)
  const captures = fiche.captures
  const index = captures.length === 0 ? 0 : Math.min(ecran, captures.length - 1)
  const auteur = fiche.auteur.nom || fiche.auteur.username || 'un dev'
  const depotCourt = fiche.depot_url.split('/').pop()

  return (
    <div>
      <div className="mx-auto flex max-w-[1440px] flex-col gap-7 px-4 py-7 min-[761px]:px-9">
        <nav aria-label="Fil d'Ariane" className="text-sm text-muted">
          <Link to="/store" className="text-muted">Store</Link> <span aria-hidden="true">/</span> <span className="font-semibold text-ink">{fiche.nom}</span>
        </nav>
        <div className="grid items-start gap-8 min-[961px]:grid-cols-[minmax(0,1fr)_340px]">
          <div className="flex min-w-0 flex-col gap-12">
            <header className="flex max-w-[820px] flex-col gap-3.5">
              <p className="m-0 text-[12.5px] font-bold tracking-[0.08em] text-warning uppercase">{libelleCategorie(fiche.categorie)}{fiche.stack.length ? ` · ${fiche.stack.slice(0, 3).join(' · ')}` : ''}</p>
              <h1 className="m-0 text-5xl leading-none font-extrabold tracking-tight min-[761px]:text-6xl">{fiche.nom}</h1>
              <p className="m-0 max-w-[52ch] text-xl text-subtle">{fiche.description}</p>
              <p className="m-0 text-sm text-muted">
                par {fiche.auteur.username ? <Link to={`/devs/${fiche.auteur.username}`} className="font-semibold">{auteur}</Link> : auteur}
                {fiche.auteur.pays ? ` · ${nomPays(fiche.auteur.pays)}` : ''}
              </p>
            </header>

            <section aria-label="Captures de l'application" className="flex flex-col gap-3.5">
              {captures.length > 0 ? (
                <div role="tablist" aria-label="Captures" className="flex flex-wrap gap-2">
                  {captures.map((capture, numero) => (
                    <button key={capture.url} type="button" role="tab" aria-selected={index === numero} onClick={() => setEcran(numero)} className={`min-h-10 rounded-full px-3 text-sm font-semibold ${index === numero ? 'bg-ink text-canvas' : 'bg-canvas text-subtle'}`}>
                      Capture {numero + 1}
                    </button>
                  ))}
                </div>
              ) : null}
              {captures.length > 0 ? (
                <div className="overflow-hidden rounded-[22px] p-7 pb-0" style={{ background: teinte.fond }}>
                  <div className="flex h-full flex-col overflow-hidden rounded-t-[10px] bg-canvas shadow-[0_20px_50px_rgba(120,80,30,0.22)]">
                    <div className="flex items-center gap-1.5 border-b border-[#E7E2D8] bg-[#F4F1EB] px-3 py-2">
                      <i className="size-2.5 rounded-full bg-[#F87171]" /><i className="size-2.5 rounded-full bg-[#FBBF24]" /><i className="size-2.5 rounded-full bg-[#34D399]" />
                      <span className="mx-auto rounded-full bg-canvas px-3.5 py-0.5 font-mono text-[11.5px] text-muted">capture {index + 1}</span>
                    </div>
                    <img src={captures[index].url} alt={`Capture ${index + 1} de ${fiche.nom}`} className="max-h-[420px] w-full object-contain" />
                  </div>
                </div>
              ) : (
                <div className="grid min-h-[280px] place-items-center rounded-[22px] border border-dashed border-line px-6 py-12 text-center" style={{ background: teinte.fond, color: teinte.texte }}>
                  <p className="m-0"><strong className="block text-lg">Aucune capture publiée</strong><span className="mt-1 block text-sm">L’auteur n’a pas fourni d’image pour cette application.</span></p>
                </div>
              )}
            </section>

            <section aria-labelledby="titre-readme" className="flex flex-col gap-3.5">
              <div className="flex items-center gap-2.5">
                <h2 id="titre-readme" className="m-0 text-[26px] font-bold tracking-tight">À propos</h2>
                <span className="ml-auto font-mono text-[12.5px] text-muted">README.md</span>
              </div>
              <p className="m-0 whitespace-pre-wrap text-nav-texte">{fiche.readme || fiche.description}</p>
              <h3 className="m-0 text-base">Lancer en local</h3>
              <pre className="m-0 overflow-x-auto rounded-[10px] bg-ink p-3.5 font-mono text-[13px] leading-relaxed text-[#E2E8F0]">{`git clone ${fiche.depot_url}\ncd ${depotCourt}`}</pre>
              <p className="m-0 text-[13.5px] text-muted">Affiché depuis le README du dépôt.</p>
            </section>

            <RapportSecurite appId={fiche.id} securite={fiche.securite} estAuteur={compte.data?.id === fiche.auteur.id} />

            <section aria-labelledby="titre-discussions" className="flex flex-col gap-3">
              <div className="flex items-center gap-2.5">
                <h2 id="titre-discussions" className="m-0 text-[26px] font-bold tracking-tight">Discussions</h2>
                {discussions.isSuccess ? <span className="text-[13px] text-muted">{discussions.data.data.length}</span> : null}
              </div>
              {discussions.isPending ? <p className="text-muted">Chargement…</p> : null}
              {discussions.isSuccess && discussions.data.data.length === 0 ? <p className="text-muted">Aucune discussion sur cette appli.</p> : null}
              <ul className="m-0 flex list-none flex-col p-0">
                {discussions.data?.data.map((discussion) => (
                  <li key={discussion.id} className="flex flex-col gap-1 border-t border-ink/10 py-3">
                    <Link to={`/echange/${discussion.id}`} className="font-semibold text-ink no-underline">{discussion.titre}</Link>
                    <span className="flex flex-wrap items-center gap-2.5 text-[13px] text-muted">
                      <span className={`rounded-full px-2 font-semibold ${discussion.resolue ? 'bg-success-bg text-success' : 'bg-nav text-accent'}`}>{discussion.resolue ? 'Résolue' : 'Ouverte'}</span>
                      <span>{discussion.auteur.nom || discussion.auteur.username}</span>
                      <span>{discussion.nb_messages} messages</span>
                    </span>
                  </li>
                ))}
              </ul>
              <Link to="/echange" className="inline-flex min-h-cible items-center justify-center rounded-controle border border-line font-semibold text-accent no-underline">Poser une question sur cette appli</Link>
              <p className="m-0 text-[13px] text-muted">Ouvert à tous : clients et devs. Un dev peut y proposer un correctif.</p>
            </section>
          </div>

          <aside aria-label="Déployer cette appli" className="flex flex-col gap-4 rounded-[22px] border border-line bg-canvas p-6 min-[961px]:sticky min-[961px]:top-6">
            <div className="flex items-center gap-3.5">
              <span aria-hidden="true" className="grid size-14 shrink-0 place-items-center rounded-[14px] text-lg font-bold" style={{ background: teinte.fond, color: teinte.texte }}>{fiche.nom.slice(0, 2).toUpperCase()}</span>
              <div className="min-w-0">
                <p className="m-0 text-[19px] leading-tight font-bold">{fiche.nom}</p>
                <span className="text-sm text-muted">par {auteur}{fiche.auteur.pays ? ` · ${nomPays(fiche.auteur.pays)}` : ''}</span>
              </div>
            </div>
            <div className="flex flex-wrap gap-2">
              <span className="rounded-full bg-chip px-3 py-1 text-[13px] font-semibold text-nav-texte">{libelleCategorie(fiche.categorie)}</span>
              {fiche.securite.statut === 'verifie' ? <span className="rounded-full bg-success-bg px-3 py-1 text-[13px] font-bold text-success">Security Checked</span> : null}
              {fiche.securite.statut === 'problemes' ? <span className="rounded-full bg-[#FFEDD5] px-3 py-1 text-[13px] font-bold text-[#9A3412]">Problèmes détectés</span> : null}
              {fiche.securite.statut === 'en_cours' ? <span className="rounded-full bg-page px-3 py-1 text-[13px] font-semibold text-subtle">Analyse en cours</span> : null}
            </div>
            <ul aria-label="Stack" className="m-0 flex list-none flex-wrap gap-1.5 p-0">
              {fiche.stack.map((techno) => <li key={techno} className="rounded-full bg-chip px-2.5 py-0.5 text-[13px] font-semibold text-nav-texte">{techno}</li>)}
            </ul>
            <div className="flex items-baseline gap-1.5 border-t border-[#EEF2F9] pt-3.5">
              <span className="text-[30px] font-bold tracking-tight tabular-nums">{prix}</span>
              {fiche.prix > 0 && fiche.type_prix === 'mensuel' ? <span className="text-muted">/ mois</span> : null}
            </div>
            <dl className="m-0 flex flex-col gap-2 text-sm">
              <div className="flex justify-between gap-3"><dt className="text-subtle">Appli, versée à {auteur}</dt><dd className="m-0 font-semibold">{prix}</dd></div>
              <div className="flex justify-between gap-3"><dt className="text-subtle">Hébergement, {tailleNom[fiche.taille]}</dt><dd className="m-0 font-semibold">{fcfa(host)}/mois</dd></div>
              <div className="flex justify-between gap-3"><dt className="text-subtle">Commission Souk.dev</dt><dd className="m-0 font-semibold text-success">0 F</dd></div>
              <div className="flex justify-between gap-3 border-t border-[#EEF2F9] pt-2 font-bold"><dt>Total affiché</dt><dd className="m-0">{fcfa(fiche.prix + host)}</dd></div>
            </dl>
            {fiche.demo_url ? <a href={fiche.demo_url} target="_blank" rel="noreferrer" className="inline-flex min-h-12 items-center justify-center rounded-xl bg-accent font-semibold text-canvas no-underline">Ouvrir l’application publiée ↗</a> : <p className="m-0 text-sm text-muted">L’auteur n’a pas renseigné d’URL publique pour cette application.</p>}
            <Link to={`/store/${fiche.id}/deployer`} className="inline-flex min-h-cible items-center justify-center rounded-controle bg-ink font-semibold text-canvas no-underline">Déployer ma copie</Link>
            <button type="button" onClick={() => setMission(true)} className="min-h-cible rounded-controle border border-line bg-canvas font-semibold">Demander une version sur mesure</button>
            {fiche.variables_client.length > 0 ? (
              <div className="rounded-xl bg-page px-3.5 py-3">
                <p className="m-0 text-[13.5px] font-semibold">Au déploiement, vous remplirez :</p>
                <ul className="m-0 mt-1 list-disc pl-4 text-[13.5px] text-subtle">
                  {fiche.variables_client.map((variable) => <li key={variable.nom}>{variable.libelle}</li>)}
                </ul>
              </div>
            ) : null}
            <a href={fiche.depot_url} target="_blank" rel="noreferrer" className="text-sm">Dépôt GitHub ↗</a>
          </aside>
        </div>
      </div>
      {mission ? <FormMission id={fiche.id} onFerme={() => setMission(false)} /> : null}
    </div>
  )
}

function FormMission({ id, onFerme }: { id: number; onFerme: () => void }) {
  const [erreur, setErreur] = useState<string | null>(null)
  const [ok, setOk] = useState(false)

  async function envoyer(evenement: FormEvent<HTMLFormElement>) {
    evenement.preventDefault()
    const donnees = new FormData(evenement.currentTarget)
    const budget = String(donnees.get('budget') ?? '')
    try {
      await proposerMissionAppli(id, {
        message: String(donnees.get('message') ?? ''),
        budget: budget ? Number(budget) : undefined,
        delai: String(donnees.get('delai') ?? '') || undefined,
      })
      setOk(true)
    } catch (erreurHttp) {
      setErreur(messageAction(erreurHttp, 'Trop de missions. Réessayez plus tard.'))
    }
  }

  return (
    <Modal titre="Demander une version sur mesure" onFermer={onFerme}>
      {ok ? <p role="status">Demande envoyée. Elle apparaît dans Missions.</p> : (
        <form onSubmit={(e) => void envoyer(e)} className="flex flex-col gap-3">
          <label className="font-semibold">Besoin
            <textarea name="message" required rows={4} className="mt-1 w-full rounded-controle border border-line bg-canvas p-3 font-normal" />
          </label>
          <label className="font-semibold">Budget (FCFA)
            <input name="budget" inputMode="numeric" className="mt-1 min-h-cible w-full rounded-controle border border-line bg-canvas px-3 font-normal" />
          </label>
          <label className="font-semibold">Délai
            <input name="delai" type="date" className="mt-1 min-h-cible w-full rounded-controle border border-line bg-canvas px-3 font-normal" />
          </label>
          {erreur ? <p role="alert" className="m-0 text-danger">{erreur}</p> : null}
          <button type="submit" className="min-h-cible rounded-controle bg-accent font-semibold text-canvas">Envoyer</button>
        </form>
      )}
    </Modal>
  )
}
