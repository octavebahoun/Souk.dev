import { cx } from '../../ui'
import { logoReseau } from './reseaux'

type LogoReseauProps = {
  code: string | null | undefined
  /** Nom du réseau, lu par les lecteurs d'écran quand le texte n'est pas affiché à côté. */
  nom: string
  taille?: 'petit' | 'moyen'
  className?: string
}

/** Logo d'un réseau Mobile Money sur un carreau blanc (lisible dans les deux thèmes). */
export function LogoReseau({ code, nom, taille = 'moyen', className }: LogoReseauProps) {
  const logo = logoReseau(code)
  const dimension = taille === 'petit' ? 'size-6' : 'size-9'
  if (!logo) {
    return (
      <span aria-hidden="true" className={cx('grid shrink-0 place-items-center rounded-petit border border-bordure bg-surface-douce text-micro font-semibold text-texte-doux', dimension, className)}>
        {nom.slice(0, 2).toUpperCase()}
      </span>
    )
  }
  return <img src={logo} alt="" width={36} height={36} loading="lazy" className={cx('shrink-0 rounded-petit border border-bordure bg-fond-logo object-contain', dimension, className)} />
}
