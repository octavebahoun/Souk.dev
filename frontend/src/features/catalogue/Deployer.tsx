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

  if (appli.isPending) return <div role="status" className="squelette mx-auto mt-8 h-64 max-w-[1200px] rounded-[18px]" />
  if (!appli.data) {
    return (
      <p className="px-6 py-10">
        Appli introuvable. <button type="button" onClick={() => appli.refetch()} className="min-h-cible font-semibold">Réessayer</button>
      </p>
    )
  }

  const fiche = appli.data
  const host = hebergement[fiche.taille]
  const nomSaisi = Object.values(valeurs).find((valeur) => valeur.trim()) || fiche.nom
  const initiales = fiche.nom.slice(0, 2).toUpperCase()

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
    <main className="mx-auto flex max-w-[1200px] flex-col gap-6 px-4 py-6 min-[761px]:px-6">
      <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-muted">
        <p className="m-0">
          <Link to="/store">Store</Link> / <Link to={`/store/${fiche.id}`}>{fiche.nom}</Link> / <span className="font-semibold text-ink">Déployer</span>
        </p>
        <Link to={`/store/${fiche.id}`} className="inline-flex min-h-10 items-center font-semibold">← Retour à la fiche</Link>
      </div>

      <div className="flex flex-wrap items-center gap-8">
        <div className="min-w-0 flex-1 basis-[320px]">
          <p className="m-0 text-xs font-bold tracking-widest text-accent uppercase">Déploiement · {fiche.nom}</p>
          <h1 className="m-0 mt-2 text-[34px] leading-tight font-extrabold tracking-tight min-[761px]:text-[44px]">Votre copie, en ligne en quelques minutes.</h1>
          <p className="max-w-[46ch] text-subtle">Une copie neuve et isolée de {fiche.nom}. Rien n’est débité : le paiement n’est pas en place.</p>
        </div>
        <Telephone nom={nomSaisi} />
      </div>

      <div className="grid items-start gap-6 min-[861px]:grid-cols-[minmax(0,1fr)_320px]">
        <div className="flex flex-col gap-4">
          <section className="rounded-[18px] border border-line bg-canvas p-6">
            <h2 className="m-0 text-lg">Vos informations</h2>
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
          <section className="overflow-hidden rounded-[18px] border border-line bg-canvas" aria-label="Aperçu">
            <div className="flex items-center justify-between border-b border-line px-4 py-2 text-sm">
              <span className="font-semibold">Votre copie, en direct</span>
              <span className="text-accent">Aperçu</span>
            </div>
            <div className="bg-[#F8F1E7] p-4">
              <div className="overflow-hidden rounded-xl border border-line bg-canvas">
                <div className="flex h-8 items-center gap-1.5 bg-[#8C4A1F] px-3">
                  <i className="size-2 rounded-full bg-[#F87171]" /><i className="size-2 rounded-full bg-[#FBBF24]" /><i className="size-2 rounded-full bg-[#34D399]" />
                  <span className="ml-2 text-xs text-white/80">aperçu</span>
                </div>
                <div className="flex items-center gap-3 p-4">
                  <span className="grid size-10 place-items-center rounded-lg bg-[#F6C445] font-extrabold text-[#92400E]">{initiales}</span>
                  <strong>{nomSaisi}</strong>
                </div>
                <div className="mx-4 mb-4 h-16 rounded-lg bg-[#F3D48A]" />
                <div className="mx-4 mb-4 grid grid-cols-3 gap-2">
                  <span className="h-14 rounded-lg bg-[#F6E7C1]" /><span className="h-14 rounded-lg bg-[#E7D7F5]" /><span className="h-14 rounded-lg bg-[#D7EBD4]" />
                </div>
              </div>
            </div>
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

function Telephone({ nom }: { nom: string }) {
  return (
    <div className="relative mx-auto h-[340px] w-[280px] shrink-0" aria-hidden="true">
      <span className="absolute top-2 left-0 z-10 rounded-2xl bg-white px-2.5 py-1.5 text-[11px] shadow-carte">Paiement reçu</span>
      <span className="absolute top-6 right-0 z-10 rounded-2xl bg-white px-2.5 py-1.5 text-[11px] shadow-carte">Commande</span>
      <span className="absolute top-28 left-1 z-10 grid size-10 place-items-center rounded-xl bg-white text-sm shadow-carte">{'</>'}</span>
      <span className="absolute right-2 bottom-16 z-10 grid size-10 place-items-center rounded-xl bg-white text-sm shadow-carte">↗</span>
      <span className="absolute bottom-8 left-4 z-10 grid size-10 place-items-center rounded-xl bg-white shadow-carte">▢</span>
      <span className="absolute right-0 bottom-2 z-10 rounded-2xl bg-white px-2.5 py-1.5 text-[11px] shadow-carte">En ligne</span>
      <div className="absolute top-8 left-8 w-[190px] rounded-[32px] border-[8px] border-[#1F2937] bg-[#1F2937] shadow-[0_24px_50px_rgba(15,23,42,0.18)]">
        <div className="overflow-hidden rounded-[24px] bg-[#F7F4EF]">
          <div className="flex items-center justify-between px-3 pt-2 text-[10px] text-muted"><span>9:41</span><span>●●●</span></div>
          <p className="m-0 px-3 text-xs">Bonjour</p>
          <p className="m-0 truncate px-3 text-lg font-extrabold">{nom}</p>
          <div className="m-3 rounded-2xl bg-[#1E3A8A] p-3 text-white">
            <p className="m-0 text-[11px]">Nouveautés</p>
            <div className="mt-2 grid grid-cols-2 gap-2 text-[11px] text-ink">
              <span className="rounded-lg bg-[#F6C445] p-2">Page vue</span>
              <span className="rounded-lg bg-[#F3A35C] p-2">Sac</span>
            </div>
          </div>
          <div className="mx-3 mb-3 grid grid-cols-3 gap-1 text-[10px]">
            <span className="rounded-lg bg-[#F6E7C1] p-1.5">4 500 F</span>
            <span className="rounded-lg bg-[#E7D7F5] p-1.5">7 000 F</span>
            <span className="rounded-lg bg-[#D7EBD4] p-1.5">6 000 F</span>
          </div>
        </div>
      </div>
    </div>
  )
}
