import { cx } from './cx'
import { urlIllustration, type NomIllustration } from './illustrations'

type IllustrationProps = {
  nom: NomIllustration
  taille?: 'petite' | 'moyenne'
  className?: string
}

/**
 * Illustration décorative, dans un cadre aux coins de 12 px. Une version par thème : le fond
 * de l'image se confond avec le cadre, clair ou bleu nuit.
 */
export function Illustration({ nom, taille = 'moyenne', className }: IllustrationProps) {
  const claire = urlIllustration(nom)
  const sombre = urlIllustration(nom, 'sombre')
  const dimension = taille === 'petite' ? 'size-28' : 'size-40 tablette:size-48'
  const image = 'size-full object-cover'
  return (
    <div aria-hidden="true" className={cx('shrink-0 overflow-hidden rounded-grand bg-surface-accent', dimension, className)}>
      {claire ? <img src={claire} alt="" width={192} height={192} loading="lazy" decoding="async" className={cx(image, sombre ? 'dark:hidden' : 'dark:brightness-90')} /> : null}
      {sombre ? <img src={sombre} alt="" width={192} height={192} loading="lazy" decoding="async" className={cx(image, 'hidden dark:block')} /> : null}
    </div>
  )
}
