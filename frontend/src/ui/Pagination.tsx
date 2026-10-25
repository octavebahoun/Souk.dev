import { ChevronLeft, ChevronRight } from 'lucide-react'
import { formater, t } from '../i18n'
import { cx } from './cx'

type PaginationProps = {
  page: number
  dernierePage: number
  onChange: (page: number) => void
  className?: string
}

/** Pages à montrer : la première, la dernière, et deux voisines de la courante. */
function pages(courante: number, derniere: number): Array<number | 'ellipse'> {
  const ensemble = new Set([1, derniere, courante - 1, courante, courante + 1].filter((page) => page >= 1 && page <= derniere))
  const triees = [...ensemble].sort((a, b) => a - b)
  const resultat: Array<number | 'ellipse'> = []
  triees.forEach((page, index) => {
    if (index > 0 && page - triees[index - 1] > 1) resultat.push('ellipse')
    resultat.push(page)
  })
  return resultat
}

export function Pagination({ page, dernierePage, onChange, className }: PaginationProps) {
  if (dernierePage <= 1) return null
  const classe = 'grid h-11 min-w-11 place-items-center rounded-moyen px-2 text-corps chiffres transition-colors duration-150 tablette:h-9 tablette:min-w-9'
  return (
    <nav aria-label={t.commun.pagination} className={cx('flex items-center justify-center gap-1', className)}>
      <button
        type="button"
        onClick={() => onChange(page - 1)}
        disabled={page <= 1}
        aria-label={t.commun.precedent}
        className={cx(classe, 'text-texte hover:bg-surface-douce disabled:opacity-40')}
      >
        <ChevronLeft aria-hidden="true" className="size-4" />
      </button>
      {pages(page, dernierePage).map((element, index) =>
        element === 'ellipse' ? (
          <span key={`e${index}`} aria-hidden="true" className="px-1 text-texte-doux">
            …
          </span>
        ) : (
          <button
            key={element}
            type="button"
            onClick={() => onChange(element)}
            aria-label={formater(t.commun.page, { numero: element })}
            aria-current={element === page ? 'page' : undefined}
            className={cx(classe, element === page ? 'bg-primaire font-medium text-sur-primaire' : 'text-texte hover:bg-surface-douce')}
          >
            {element}
          </button>
        ),
      )}
      <button
        type="button"
        onClick={() => onChange(page + 1)}
        disabled={page >= dernierePage}
        aria-label={t.commun.suivant}
        className={cx(classe, 'text-texte hover:bg-surface-douce disabled:opacity-40')}
      >
        <ChevronRight aria-hidden="true" className="size-4" />
      </button>
    </nav>
  )
}
