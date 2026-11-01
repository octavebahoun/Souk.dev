import type { ReactNode } from 'react'
import { cx } from './cx'

type PuceFiltreProps = {
  presse: boolean
  onClick: () => void
  children: ReactNode
  className?: string
}

/**
 * Filtre à bascule en pastille (catégorie, étiquette, difficulté) : `aria-pressed`, 44 px de haut
 * sur mobile, bleu plein quand il est actif.
 */
export function PuceFiltre({ presse, onClick, children, className }: PuceFiltreProps) {
  return (
    <button
      type="button"
      aria-pressed={presse}
      onClick={onClick}
      className={cx(
        'inline-flex h-11 shrink-0 items-center gap-1.5 rounded-pastille border px-4 text-petit font-medium transition-colors duration-150 tablette:h-9',
        presse ? 'border-primaire bg-primaire text-sur-couleur' : 'border-bordure bg-surface text-texte hover:border-bordure-champ hover:text-titre',
        className,
      )}
    >
      {children}
    </button>
  )
}
