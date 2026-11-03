import type { ReactNode } from 'react'
import { Check, LoaderCircle, X } from 'lucide-react'
import { cx } from './cx'

export type EtatEtape = 'a_venir' | 'en_cours' | 'terminee' | 'echec'

export type Etape = {
  id: string
  libelle: ReactNode
  description?: ReactNode
  etat: EtatEtape
}

type EtapesProps = {
  libelle: string
  etapes: Etape[]
  orientation?: 'verticale' | 'horizontale'
  className?: string
}

const pastilles: Record<EtatEtape, string> = {
  a_venir: 'border-bordure-champ bg-surface text-texte-doux',
  en_cours: 'border-primaire bg-primaire-doux text-primaire',
  terminee: 'border-succes-plein bg-succes-plein text-sur-couleur',
  echec: 'border-danger-solide bg-danger-solide text-sur-couleur',
}

function Pastille({ etat, numero }: { etat: EtatEtape; numero: number }) {
  return (
    <span aria-hidden="true" className={cx('grid size-7 shrink-0 place-items-center rounded-pastille border-2 text-petit font-semibold', pastilles[etat])}>
      {etat === 'terminee' ? <Check className="size-4" strokeWidth={3} /> : null}
      {etat === 'echec' ? <X className="size-4" strokeWidth={3} /> : null}
      {etat === 'en_cours' ? <LoaderCircle className="size-4 animate-tourne" /> : null}
      {etat === 'a_venir' ? numero : null}
    </span>
  )
}

const libellesEtat: Record<EtatEtape, string> = {
  a_venir: 'à venir',
  en_cours: 'en cours',
  terminee: 'terminée',
  echec: 'en échec',
}

/**
 * Suivi d'un déploiement ou d'un paiement (état du système visible). L'étape courante porte
 * aria-current="step" ; chaque étape dit son état aux lecteurs d'écran.
 */
export function Etapes({ libelle, etapes, orientation = 'verticale', className }: EtapesProps) {
  const horizontale = orientation === 'horizontale'
  return (
    <ol
      aria-label={libelle}
      className={cx('m-0 list-none p-0', horizontale ? 'flex flex-col gap-3 tablette:flex-row tablette:items-center tablette:gap-2' : 'flex flex-col', className)}
    >
      {etapes.map((etape, index) => {
        const derniere = index === etapes.length - 1
        return (
          <li
            key={etape.id}
            aria-current={etape.etat === 'en_cours' ? 'step' : undefined}
            className={cx('relative flex gap-3', horizontale ? 'items-center tablette:flex-1' : 'pb-6 last:pb-0')}
          >
            {!horizontale && !derniere ? (
              <span
                aria-hidden="true"
                className={cx('absolute top-8 bottom-1 left-[13px] w-0.5 rounded-pastille', etape.etat === 'terminee' ? 'bg-succes-solide' : 'bg-bordure')}
              />
            ) : null}
            <Pastille etat={etape.etat} numero={index + 1} />
            <div className="flex min-w-0 flex-col gap-0.5 pt-0.5">
              <span className={cx('font-medium', etape.etat === 'a_venir' ? 'text-texte-doux' : 'text-titre')}>
                {etape.libelle}
                <span className="pour-lecteur"> — {libellesEtat[etape.etat]}</span>
              </span>
              {etape.description && !horizontale ? <span className="text-petit text-texte-doux">{etape.description}</span> : null}
            </div>
            {horizontale && !derniere ? <span aria-hidden="true" className="ml-2 hidden h-0.5 flex-1 rounded-pastille bg-bordure tablette:block" /> : null}
          </li>
        )
      })}
    </ol>
  )
}
