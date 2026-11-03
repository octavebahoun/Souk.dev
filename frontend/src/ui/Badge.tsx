import type { ReactNode } from 'react'
import { cx } from './cx'

export type TonBadge = 'neutre' | 'marque' | 'info' | 'succes' | 'alerte' | 'danger' | 'ia'

const tons: Record<TonBadge, string> = {
  neutre: 'border-bordure bg-surface-douce text-texte',
  marque: 'border-transparent bg-primaire-doux text-sur-primaire-doux',
  info: 'border-bordure-info bg-fond-info text-info',
  succes: 'border-bordure-succes bg-fond-succes text-succes',
  alerte: 'border-bordure-alerte bg-fond-alerte text-alerte',
  danger: 'border-bordure-danger bg-fond-danger text-danger',
  ia: 'border-bordure-ia bg-fond-ia text-ia',
}

type BadgeProps = {
  ton?: TonBadge
  icone?: ReactNode
  className?: string
  children: ReactNode
}

export function Badge({ ton = 'neutre', icone, className, children }: BadgeProps) {
  return (
    <span
      className={cx(
        'inline-flex max-w-full items-center gap-1 rounded-pastille border px-2 py-0.5 text-etiquette font-medium normal-case tracking-normal whitespace-nowrap',
        tons[ton],
        className,
      )}
    >
      {icone}
      <span className="truncate">{children}</span>
    </span>
  )
}

const points: Record<TonBadge, string> = {
  neutre: 'bg-texte-doux',
  marque: 'bg-primaire',
  info: 'bg-info-solide',
  succes: 'bg-succes-solide',
  alerte: 'bg-alerte-solide',
  danger: 'bg-danger-solide',
  ia: 'bg-ia',
}

type PastilleEtatProps = {
  ton: TonBadge
  libelle: string
  /** Travail en cours : le point pulse (sauf mouvement réduit). */
  enCours?: boolean
  className?: string
}

/** Point coloré + libellé. Un état, une couleur, partout (docs/design.md, § 8). */
export function PastilleEtat({ ton, libelle, enCours = false, className }: PastilleEtatProps) {
  return (
    <span className={cx('inline-flex items-center gap-2 text-petit font-medium text-titre', className)}>
      <span aria-hidden="true" className="relative inline-flex size-2.5">
        {enCours ? <span className={cx('absolute inset-0 rounded-pastille opacity-60 animate-ping', points[ton])} /> : null}
        <span className={cx('relative inline-flex size-2.5 rounded-pastille', points[ton])} />
      </span>
      {libelle}
    </span>
  )
}
