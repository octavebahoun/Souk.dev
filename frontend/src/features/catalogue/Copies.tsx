import { useState } from 'react'
import { Link } from 'react-router'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { arreterCopie, listerCopies, type Deploiement } from './api'
import { messageAction } from '../echange/api'

const etats: Record<Deploiement['etat'], { libelle: string; fond: string; texte: string }> = {
  en_file: { libelle: 'En file', fond: '#E9EEF7', texte: '#334155' },
  construction: { libelle: 'Construction', fond: '#E8EEFD', texte: '#1F4FE0' },
  demarrage: { libelle: 'Démarrage', fond: '#FEF3C7', texte: '#92400E' },
  en_ligne: { libelle: 'En ligne', fond: '#DCFCE7', texte: '#166534' },
  echec: { libelle: 'Échec', fond: '#FEE2E2', texte: '#991B1B' },
  arrete: { libelle: 'Arrêtée', fond: '#F1F5F9', texte: '#475569' },
}

export function Copies() {
  const copies = useQuery({ queryKey: ['copies'], queryFn: listerCopies })
  const client = useQueryClient()
  const [filtre, setFiltre] = useState<Deploiement['etat'] | 'toutes'>('toutes')
  const [cible, setCible] = useState<Deploiement | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)
  const liste = (copies.data?.data ?? []).filter((copie) => filtre === 'toutes' || copie.etat === filtre)

  async function confirmer() {
    if (!cible) return
    try {
      await arreterCopie(cible.id)
      setCible(null)
      await client.invalidateQueries({ queryKey: ['copies'] })
    } catch (erreurHttp) {
      setErreur(messageAction(erreurHttp, 'L’arrêt a échoué.'))
    }
  }

  return (
    <main className="mx-auto flex max-w-[1200px] flex-col gap-5 px-4 py-8 min-[761px]:px-6">
      <div className="rounded-[22px] bg-[#0B1A3D] px-5 py-6 text-canvas min-[761px]:px-8 min-[761px]:py-8">
        <div className="flex flex-col gap-4 min-[761px]:flex-row min-[761px]:items-center min-[761px]:justify-between">
          <div>
            <h1 className="m-0 text-[34px] font-extrabold">Mes copies</h1>
            <p className="m-0 max-w-[42ch] text-[#D6E0F5]">Toutes les applis que vous avez déployées, au même endroit.</p>
          </div>
          <Link to="/store" className="inline-flex min-h-cible w-full items-center justify-center rounded-controle bg-[#F6C445] px-4 font-semibold text-ink no-underline min-[761px]:w-auto">Déployer une autre appli</Link>
        </div>
      </div>
      <div role="group" aria-label="Filtrer par état" className="flex flex-wrap gap-2">
        {(['toutes', 'en_ligne', 'construction', 'echec', 'arrete'] as const).map((etat) => (
          <button key={etat} type="button" aria-pressed={filtre === etat} onClick={() => setFiltre(etat)} className={`min-h-10 rounded-full border px-3.5 text-sm font-semibold ${filtre === etat ? 'border-accent bg-nav text-accent' : 'border-line bg-canvas'}`}>
            {etat === 'toutes' ? 'Toutes' : etats[etat].libelle}
          </button>
        ))}
      </div>
      {copies.isPending ? <div role="status" className="squelette h-32 rounded-xl" /> : null}
      {copies.isError ? <button type="button" onClick={() => copies.refetch()} className="min-h-cible w-fit rounded-controle bg-accent px-4 font-semibold text-canvas">Réessayer</button> : null}
      {copies.isSuccess && liste.length === 0 ? <p className="rounded-xl border border-line bg-canvas p-8 text-center text-subtle">Aucune copie dans cet état.</p> : null}
      <ul className="m-0 flex list-none flex-col gap-3 p-0">
        {liste.map((copie) => {
          const etat = etats[copie.etat]
          return (
            <li key={copie.id} className="flex flex-wrap items-center gap-4 rounded-[14px] border border-line bg-canvas px-4 py-3">
              <span className="grid size-11 place-items-center rounded-[10px] bg-nav font-bold text-accent">{copie.app_nom.slice(0, 2).toUpperCase()}</span>
              <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                  <Link to={`/store/${copie.app_id}`} className="font-bold no-underline">{copie.app_nom}</Link>
                  <span className="rounded-full px-2 py-0.5 text-sm font-semibold" style={{ background: etat.fond, color: etat.texte }}>{etat.libelle}</span>
                </div>
                {copie.url ? <a href={copie.url} className="font-mono text-sm">{copie.url}</a> : null}
                {copie.erreur ? <p className="m-0 text-sm text-danger">{copie.erreur}</p> : null}
              </div>
              <div className="flex flex-wrap gap-2">
                {copie.url ? <a href={copie.url} className="inline-flex min-h-cible items-center rounded-controle border border-line px-3.5 font-semibold no-underline">Ouvrir</a> : null}
                {copie.etat !== 'arrete' ? (
                  <button type="button" onClick={() => { setErreur(null); setCible(copie) }} className="min-h-cible rounded-controle border border-[#FECACA] px-3.5 font-semibold text-[#B91C1C]">Arrêter</button>
                ) : null}
              </div>
            </li>
          )
        })}
      </ul>
      {cible ? (
        <div className="fixed inset-0 z-[60] grid place-items-center bg-ink/45 p-4" role="presentation" onClick={() => setCible(null)}>
          <div role="dialog" aria-modal="true" aria-labelledby="titre-arret" className="w-full max-w-md rounded-2xl bg-canvas p-5" onClick={(e) => e.stopPropagation()}>
            <h2 id="titre-arret" className="m-0 text-xl">Arrêter la copie de {cible.app_nom} ?</h2>
            <p className="text-subtle">Elle ne sera plus accessible. Vous pourrez en lancer une autre depuis le Store.</p>
            {erreur ? <p role="alert" className="text-danger">{erreur}</p> : null}
            <div className="flex justify-end gap-2">
              <button type="button" onClick={() => setCible(null)} className="min-h-cible rounded-controle border border-line px-4">Annuler</button>
              <button type="button" onClick={() => void confirmer()} className="min-h-cible rounded-controle bg-[#B91C1C] px-4 font-semibold text-canvas">Arrêter la copie</button>
            </div>
          </div>
        </div>
      ) : null}
    </main>
  )
}
