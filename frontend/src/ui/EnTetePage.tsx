import type { ReactNode } from 'react'
import { Link } from 'react-router'
import { ChevronRight } from 'lucide-react'
import { t } from '../i18n'
import { cx } from './cx'

export type Miette = { libelle: string; vers?: string }

export function FilAriane({ miettes }: { miettes: Miette[] }) {
  return (
    <nav aria-label={t.commun.filAriane}>
      <ol className="m-0 flex list-none flex-wrap items-center gap-1 p-0 text-petit text-texte-doux">
        {miettes.map((miette, index) => {
          const derniere = index === miettes.length - 1
          return (
            <li key={`${miette.libelle}-${index}`} className="flex items-center gap-1">
              {miette.vers && !derniere ? (
                <Link to={miette.vers} className="rounded-petit text-texte-doux no-underline hover:text-titre hover:underline">
                  {miette.libelle}
                </Link>
              ) : (
                <span aria-current={derniere ? 'page' : undefined} className={derniere ? 'text-titre' : undefined}>
                  {miette.libelle}
                </span>
              )}
              {derniere ? null : <ChevronRight aria-hidden="true" className="size-3.5 text-texte-indice" />}
            </li>
          )
        })}
      </ol>
    </nav>
  )
}

type EnTetePageProps = {
  titre: ReactNode
  description?: ReactNode
  surTitre?: ReactNode
  /** L'action principale de la page (une seule), à droite sur bureau, dessous sur mobile. */
  actions?: ReactNode
  filAriane?: Miette[]
  className?: string
}

export function EnTetePage({ titre, description, surTitre, actions, filAriane, className }: EnTetePageProps) {
  return (
    <header className={cx('flex flex-col gap-3', className)}>
      {filAriane ? <FilAriane miettes={filAriane} /> : null}
      <div className="flex flex-col gap-4 tablette:flex-row tablette:items-end tablette:justify-between">
        <div className="flex min-w-0 flex-col gap-1.5">
          {surTitre ? <p className="m-0 text-etiquette text-texte-doux uppercase">{surTitre}</p> : null}
          <h1 className="m-0 text-titre-2 tablette:text-titre-1">{titre}</h1>
          {description ? <p className="m-0 max-w-2xl text-texte-doux">{description}</p> : null}
        </div>
        {actions ? <div className="flex shrink-0 flex-wrap gap-2">{actions}</div> : null}
      </div>
    </header>
  )
}
