import { useCallback, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Modal } from '../../shared/ui/Modal'
import {
  arreterDeploiement,
  lancerCopieTest,
  messageAction,
  type Deploiement,
  type Discussion,
} from './api'

const boutonSecond =
  'inline-flex min-h-cible items-center justify-center gap-2 rounded-controle border border-line bg-canvas px-4 font-semibold text-ink'
const boutonAccent =
  'inline-flex min-h-cible items-center justify-center rounded-controle bg-accent px-4 font-semibold text-canvas disabled:opacity-60'

const etapes = [
  ['en_file', 'En file'],
  ['construction', 'Construction'],
  ['demarrage', 'Démarrage'],
  ['en_ligne', 'En ligne'],
] as const

function Vitrine({ titre }: { titre: string }) {
  return (
    <div className="overflow-hidden rounded-xl border border-line bg-[#FBF7F1]" aria-hidden="true">
      <div className="flex h-9 items-center gap-1.5 bg-canvas px-3">
        <i className="size-2 rounded-full bg-[#F87171]" />
        <i className="size-2 rounded-full bg-[#FBBF24]" />
        <i className="size-2 rounded-full bg-[#34D399]" />
        <span className="mx-auto max-w-[28ch] truncate rounded-full bg-[#EEF2F9] px-3 font-mono text-[11px] text-muted">{titre}</span>
      </div>
      <div className="flex flex-col items-center gap-3 px-4 py-8 text-center">
        <p className="m-0 text-sm text-muted">Aperçu illustré · la vraie démo remplace ce dessin</p>
        <p className="m-0 text-sm">Votre panier · 3 articles</p>
        <p className="m-0 text-3xl font-extrabold">12 500 F</p>
        <span className="rounded-lg bg-[#F6C445] px-5 py-2.5 text-sm font-bold text-ink">Payer avec MTN MoMo</span>
        <span className="rounded-full bg-[#FEE2E2] px-3 py-1 text-xs font-semibold text-[#991B1B]">Erreur 500 · callback</span>
      </div>
    </div>
  )
}

function adresseVisible(url: string): string {
  try {
    const lue = new URL(url)
    return `${lue.host}${lue.pathname === '/' ? '' : lue.pathname}`
  } catch {
    return url
  }
}

function messageCopie(copie: Deploiement): string {
  if (copie.etat === 'echec') return copie.erreur || 'La copie a échoué.'
  if (copie.etat === 'arrete') return 'Copie arrêtée.'
  if (copie.etat === 'en_ligne') return 'La copie tourne.'
  return 'La copie avance. La page se met à jour toute seule.'
}

type CadreDemoProps = {
  discussion: Discussion
  auteur: boolean
}

export function CadreDemo({ discussion, auteur }: CadreDemoProps) {
  const client = useQueryClient()
  const [confirmer, setConfirmer] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)
  const fermer = useCallback(() => setConfirmer(false), [])
  const copie = discussion.copie_test
  const demo = discussion.demo_url
  const cadre = demo || (copie?.etat === 'en_ligne' ? copie.url : null)
  const peutLancer = auteur && !copie && (discussion.depot_url !== null || discussion.app_id !== null)
  const peutArreter =
    auteur && copie !== null && copie.etat !== 'echec' && copie.etat !== 'arrete'

  const lancer = useMutation({
    mutationFn: () => lancerCopieTest(discussion.id),
    onSuccess: (deploiement) => {
      client.setQueryData<Discussion>(['discussion', discussion.id], (actuelle) =>
        actuelle ? { ...actuelle, copie_test: deploiement } : actuelle,
      )
      setConfirmer(false)
      setErreur(null)
    },
    onError: (recue) => setErreur(messageAction(recue, '3 copies de test par heure.')),
  })

  const arreter = useMutation({
    mutationFn: () => arreterDeploiement(copie?.id ?? 0),
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: ['discussion', discussion.id] })
      setErreur(null)
    },
    onError: (recue) => setErreur(messageAction(recue, '3 copies de test par heure.')),
  })

  if (!discussion.depot_url && !cadre && !copie && !peutLancer) return null

  return (
    <section aria-labelledby="titre-projet" className="verre flex flex-col gap-4 rounded-carte p-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h2 id="titre-projet" className="m-0 text-[17px] font-bold">
          Le projet
        </h2>
        {discussion.depot_url ? (
          <a
            href={discussion.depot_url}
            target="_blank"
            rel="noreferrer"
            className="inline-flex min-h-cible items-center rounded-controle border border-line bg-page px-3 font-mono text-[13px] text-ink"
          >
            {adresseVisible(discussion.depot_url)}
          </a>
        ) : null}
      </div>

      {!cadre ? <Vitrine titre={discussion.titre} /> : null}
      {cadre ? (
        <div className="overflow-hidden rounded-controle border border-line">
          <div className="flex items-center gap-2 border-b border-line bg-[#EEF2F9] px-3 py-2">
            <span className="min-w-0 flex-1 truncate rounded-md bg-canvas px-2.5 py-0.5 font-mono text-[12.5px] text-subtle">
              {adresseVisible(cadre)}
            </span>
            <span className="text-xs text-muted">Démo externe · isolée</span>
          </div>
          <iframe title="Démo isolée" src={cadre} sandbox="allow-scripts allow-forms" referrerPolicy="no-referrer" className="h-64 w-full max-w-full border-0 bg-canvas" />
        </div>
      ) : null}

      <div className="flex flex-wrap items-center gap-2.5">
        {cadre ? (
          <a href={cadre} target="_blank" rel="noreferrer" className={boutonSecond}>
            Ouvrir dans un onglet
          </a>
        ) : null}
        {peutLancer ? (
          <button type="button" onClick={() => setConfirmer(true)} className={`${boutonSecond} border-accent text-accent`}>
            Lancer une copie de test
          </button>
        ) : null}
      </div>

      {copie ? (
        <div className="flex flex-col gap-3 rounded-controle border border-line bg-page p-4" role="status">
          <h3 className="m-0 text-[15px] font-bold">Copie de test</h3>
          <ol aria-label="Étapes de la copie" className="m-0 flex list-none flex-wrap gap-x-4 gap-y-2 p-0">
            {etapes.map(([etat, libelle]) => (
              <li key={etat} aria-current={copie.etat === etat ? 'step' : undefined} className={copie.etat === etat ? 'font-bold text-accent' : 'text-muted'}>
                {libelle}
              </li>
            ))}
          </ol>
          <p className="m-0 text-sm">{messageCopie(copie)}</p>
          {copie.etat === 'en_ligne' && copie.url ? (
            <a href={copie.url} target="_blank" rel="noreferrer" className={boutonAccent}>
              Ouvrir la copie
            </a>
          ) : null}
          {peutArreter ? (
            <button type="button" onClick={() => arreter.mutate()} disabled={arreter.isPending} className={`${boutonSecond} border-danger text-danger`}>
              Arrêter la copie
            </button>
          ) : null}
        </div>
      ) : null}

      {erreur ? (
        <p role="alert" className="m-0 text-sm text-danger">
          {erreur}
        </p>
      ) : null}

      {confirmer ? (
        <Modal titre="Lancer une copie de test ?" onFermer={fermer}>
          <p className="m-0 text-subtle">
            Souk.dev lance une copie à partir du dépôt ou de l’appli jointe. Rien ne part tout seul : 3 copies par heure.
          </p>
          <div className="flex flex-wrap justify-end gap-2">
            <button type="button" onClick={fermer} className={boutonSecond}>
              Annuler
            </button>
            <button type="button" onClick={() => lancer.mutate()} disabled={lancer.isPending} className={boutonAccent}>
              Lancer la copie
            </button>
          </div>
        </Modal>
      ) : null}
    </section>
  )
}
