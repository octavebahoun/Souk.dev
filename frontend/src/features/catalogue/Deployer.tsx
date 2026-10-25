import { useState } from 'react'
import { Link, useParams } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { deployerAppli, fcfa, lireAppli } from './api'
import { messageAction } from '../echange/api'
import { nomPays } from '../echange/texte'

const hebergement = { petite: 3500, moyenne: 7000, grande: 13500 }
const tailleLibelle = { petite: '512 Mo', moyenne: '1 Go', grande: '2 Go' }

export function Deployer() {
  const { id = '' } = useParams()
  const appli = useQuery({ queryKey: ['appli', id], queryFn: () => lireAppli(Number(id)) })
  const [valeurs, setValeurs] = useState<Record<string, string>>({})
  const [erreur, setErreur] = useState<string | null>(null)
  const [etat, setEtat] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)

  if (appli.isPending) return <div role="status" className="squelette mx-auto mt-8 h-64 max-w-[1320px] rounded-[18px]" />
  if (!appli.data) {
    return (
      <div className="mx-auto flex max-w-[1320px] flex-wrap items-center gap-3 px-6 py-10">
        <p role="alert" className="m-0">{messageAction(appli.error, 'Cette appli est introuvable.')}</p>
        <Link to="/store" className="inline-flex min-h-cible items-center font-semibold">Retour au Store</Link>
        <button type="button" onClick={() => void appli.refetch()} className="min-h-cible font-semibold">Réessayer</button>
      </div>
    )
  }

  const fiche = appli.data
  const host = hebergement[fiche.taille]

  async function lancer() {
    setEnvoi(true)
    setErreur(null)
    try {
      const recu = await deployerAppli(fiche.id, valeurs)
      setEtat(recu.etat)
    } catch (erreurHttp) {
      setErreur(messageAction(erreurHttp, '3 déploiements par heure. Réessayez plus tard.'))
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <main className="mx-auto flex max-w-[1320px] flex-col gap-6 px-4 py-6 min-[761px]:px-9">
      <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-muted">
        <p className="m-0">
          <Link to="/store">Store</Link> / <Link to={`/store/${fiche.id}`}>{fiche.nom}</Link> / <span className="font-semibold text-ink">Déployer</span>
        </p>
        <Link to={`/store/${fiche.id}`} className="inline-flex min-h-10 items-center font-semibold">← Retour à la fiche</Link>
      </div>

      <div className="grid items-center gap-8 min-[861px]:grid-cols-[minmax(0,1fr)_minmax(280px,420px)]">
        <div className="min-w-0">
          <p className="m-0 text-xs font-bold tracking-widest text-accent uppercase">Déploiement · {fiche.nom}</p>
          <h1 className="m-0 mt-2 text-[34px] leading-tight font-extrabold tracking-tight min-[761px]:text-[44px]">Votre copie, en ligne en quelques minutes.</h1>
          <p className="max-w-[58ch] text-subtle">Une copie neuve et isolée de {fiche.nom}, construite à partir du dépôt public de l’auteur. Aucun paiement n’est effectué sur cette page.</p>
        </div>
        {fiche.captures[0] ? (
          <img src={fiche.captures[0].url} alt={`Capture publiée par l’auteur pour ${fiche.nom}`} className="max-h-[280px] w-full rounded-[22px] border border-line bg-canvas object-cover shadow-carte" />
        ) : (
          <div className="grid min-h-[200px] place-items-center rounded-[22px] border border-dashed border-line bg-canvas p-6 text-center text-muted">
            Aucune capture publiée pour cette application.
          </div>
        )}
      </div>

      <div className="grid items-start gap-6 min-[861px]:grid-cols-[minmax(0,1fr)_320px]">
        <div className="flex flex-col gap-4">
          <section className="flex flex-col gap-3 rounded-[18px] border border-line bg-canvas p-6">
            <div className="flex items-center gap-2.5">
              <span aria-hidden="true" className="grid size-7 place-items-center rounded-full bg-nav text-sm font-bold text-accent">1</span>
              <h2 className="m-0 text-lg">Vos informations</h2>
            </div>
            <p className="text-sm text-muted">Demandées par l’auteur dans son soukdev.json. Elles vont uniquement dans votre copie.</p>
            {fiche.variables_client.map((variable) => (
              <label key={variable.nom} className="mt-3 flex flex-col gap-1 font-semibold">
                {variable.libelle}{variable.requis ? ' *' : ''}
                <input
                  value={valeurs[variable.nom] ?? ''}
                  onChange={(evenement) => setValeurs((courant) => ({ ...courant, [variable.nom]: evenement.target.value }))}
                  className="min-h-cible rounded-controle border border-line px-3 font-normal"
                />
              </label>
            ))}
            {fiche.variables_client.length === 0 ? <p className="text-muted">Cette appli ne demande aucune information.</p> : null}
            {erreur ? <p role="alert" className="text-danger">{erreur}</p> : null}
            {etat ? <p role="status">Copie {etat}. Le suivi est dans Mes copies.</p> : null}
          </section>
          <section className="rounded-[18px] border border-line bg-canvas p-6">
            <div className="flex items-center gap-2.5">
              <span aria-hidden="true" className="grid size-7 place-items-center rounded-full bg-nav text-sm font-bold text-accent">2</span>
              <h2 className="m-0 text-lg">Ce qui se passe ensuite</h2>
            </div>
            <ol className="m-0 flex list-decimal flex-col gap-1.5 pl-5 text-nav-texte">
              <li>Vous confirmez. Rien n’est débité.</li>
              <li>La plateforme construit votre copie à partir du dépôt de l’auteur.</li>
              <li>L’adresse apparaît dans Mes copies, seulement quand la copie est en ligne.</li>
            </ol>
          </section>
        </div>
        <aside className="rounded-[18px] border border-line bg-canvas p-5">
          <h2 className="m-0 text-lg">Récapitulatif</h2>
          <p className="m-0 mt-3 font-bold">{fiche.nom}</p>
          <p className="m-0 text-sm text-muted">par {fiche.auteur.nom || fiche.auteur.username || 'un dev'}{fiche.auteur.pays ? ` · ${nomPays(fiche.auteur.pays)}` : ''}</p>
          <dl className="m-0 mt-4 grid grid-cols-2 gap-y-2 border-t border-line pt-3 text-sm">
            <dt>Appli</dt><dd className="m-0 text-right">{fcfa(fiche.prix)}{fiche.type_prix === 'mensuel' ? '/mois' : ''}</dd>
            <dt>Hébergement, {tailleLibelle[fiche.taille]}</dt><dd className="m-0 text-right">{fcfa(host)}/mois</dd>
            <dt>Commission</dt><dd className="m-0 text-right">0 F</dd>
            <dt className="font-bold">Total affiché</dt><dd className="m-0 text-right text-lg font-extrabold">{fcfa(fiche.prix + host)}</dd>
          </dl>
          <button type="button" disabled={envoi} onClick={() => void lancer()} className="mt-4 min-h-[52px] w-full rounded-controle bg-ink font-semibold text-canvas">
            Déployer ma copie
          </button>
          <p className="m-0 mt-2 text-xs text-muted">Aucun frais caché. Vous pouvez arrêter la copie depuis Mes copies.</p>
        </aside>
      </div>
    </main>
  )
}
