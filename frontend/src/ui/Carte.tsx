import type { ComponentProps, ElementType, ReactNode } from 'react'
import { Link } from 'react-router'
import { cx } from './cx'

type CarteProps<T extends ElementType> = {
  as?: T
  /** Marge intérieure : aucune (contenu pleine largeur), normale, ou large. */
  marge?: 'aucune' | 'normale' | 'large'
  className?: string
  children: ReactNode
} & Omit<ComponentProps<T>, 'as' | 'className' | 'children'>

const marges = {
  aucune: '',
  normale: 'p-4 tablette:p-5',
  large: 'p-5 tablette:p-7',
}

export function Carte<T extends ElementType = 'section'>({ as, marge = 'normale', className, children, ...reste }: CarteProps<T>) {
  const Balise = as ?? 'section'
  return (
    <Balise className={cx('rounded-moyen border border-bordure bg-surface shadow-1', marges[marge], className)} {...reste}>
      {children}
    </Balise>
  )
}

type EnTeteCarteProps = {
  titre: ReactNode
  description?: ReactNode
  action?: ReactNode
  niveau?: 2 | 3
  className?: string
}

export function EnTeteCarte({ titre, description, action, niveau = 2, className }: EnTeteCarteProps) {
  const Titre = niveau === 2 ? 'h2' : 'h3'
  return (
    <div className={cx('flex flex-wrap items-start justify-between gap-3', className)}>
      <div className="flex min-w-0 flex-col gap-1">
        <Titre className="m-0 text-titre-3">{titre}</Titre>
        {description ? <p className="m-0 text-petit text-texte-doux">{description}</p> : null}
      </div>
      {action ? <div className="shrink-0">{action}</div> : null}
    </div>
  )
}

type CarteLienProps = {
  vers: ComponentProps<typeof Link>['to']
  /** Texte du lien, lu par les lecteurs d'écran : le titre de la carte. */
  titre: ReactNode
  className?: string
  children?: ReactNode
  /** Contenu au-dessus du titre (image de couverture). */
  media?: ReactNode
}

/**
 * Carte entièrement cliquable avec un seul lien (le titre) : pas de liens imbriqués,
 * une seule annonce pour les lecteurs d'écran.
 */
export function CarteLien({ vers, titre, className, children, media }: CarteLienProps) {
  return (
    <article
      className={cx(
        'group relative flex flex-col overflow-hidden rounded-moyen border border-bordure bg-surface shadow-1',
        'transition-[box-shadow,border-color,transform] duration-200 ease-souk hover:-translate-y-0.5 hover:border-bordure-champ hover:shadow-3',
        'has-[a:focus-visible]:outline-3 has-[a:focus-visible]:outline-offset-2 has-[a:focus-visible]:outline-lien',
        className,
      )}
    >
      {media}
      <div className="flex flex-1 flex-col gap-2 p-4">
        <h3 className="m-0 text-titre-3">
          <Link to={vers} className="text-titre no-underline after:absolute after:inset-0 after:content-[''] focus-visible:outline-none">
            {titre}
          </Link>
        </h3>
        {children}
      </div>
    </article>
  )
}
