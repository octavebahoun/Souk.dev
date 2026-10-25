import type { ReactNode } from 'react'
import { t } from '../i18n'
import { cx } from './cx'

/** Bloc de chargement à la forme du contenu attendu. */
export function Squelette({ className }: { className?: string }) {
  return <span aria-hidden="true" className={cx('squelette block', className)} />
}

type ChargementProps = {
  /** Annonce lue par les lecteurs d'écran pendant le chargement. */
  libelle?: string
  className?: string
  children: ReactNode
}

/** Enveloppe annoncée (role="status") autour des squelettes d'un écran. */
export function Chargement({ libelle = t.commun.chargement, className, children }: ChargementProps) {
  return (
    <div role="status" aria-live="polite" className={className}>
      <span className="pour-lecteur">{libelle}</span>
      {children}
    </div>
  )
}

export function SqueletteLignes({ lignes = 3, className }: { lignes?: number; className?: string }) {
  return (
    <div className={cx('flex flex-col gap-2.5', className)}>
      {Array.from({ length: lignes }, (_, index) => (
        <Squelette key={index} className={cx('h-4', index === lignes - 1 ? 'w-2/3' : 'w-full')} />
      ))}
    </div>
  )
}

/** Grille de cartes en chargement (Store, recherche de devs). */
export function SqueletteCartes({ nombre = 6, avecMedia = true }: { nombre?: number; avecMedia?: boolean }) {
  return (
    <div className="grid gap-4 tablette:grid-cols-2 bureau:grid-cols-3">
      {Array.from({ length: nombre }, (_, index) => (
        <div key={index} className="overflow-hidden rounded-moyen border border-bordure bg-surface">
          {avecMedia ? <Squelette className="aspect-[16/10] w-full rounded-none" /> : null}
          <div className="flex flex-col gap-3 p-4">
            <Squelette className="h-5 w-1/2" />
            <SqueletteLignes lignes={2} />
            <div className="flex gap-2">
              <Squelette className="h-6 w-16 rounded-pastille" />
              <Squelette className="h-6 w-20 rounded-pastille" />
            </div>
          </div>
        </div>
      ))}
    </div>
  )
}

/** Liste dense en chargement (fil de l'Échange, copies, factures). */
export function SqueletteListe({ nombre = 5 }: { nombre?: number }) {
  return (
    <div className="divide-y divide-bordure overflow-hidden rounded-moyen border border-bordure bg-surface">
      {Array.from({ length: nombre }, (_, index) => (
        <div key={index} className="flex items-center gap-4 px-4 py-4">
          <Squelette className="size-9 shrink-0 rounded-pastille" />
          <div className="flex flex-1 flex-col gap-2">
            <Squelette className="h-4 w-2/5" />
            <Squelette className="h-3.5 w-3/5" />
          </div>
          <Squelette className="hidden h-6 w-20 rounded-pastille tablette:block" />
        </div>
      ))}
    </div>
  )
}
