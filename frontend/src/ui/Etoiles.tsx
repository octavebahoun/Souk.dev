import { Star } from 'lucide-react'
import { cx } from './cx'
import { formaterNote } from './format'

const tailles = { sm: 'size-3.5', md: 'size-4', lg: 'size-6' }

/** Note sur 5 en étoiles, fractions comprises ; le nom lu donne la valeur exacte. */
export function Etoiles({ note, taille = 'md', className }: { note: number; taille?: keyof typeof tailles; className?: string }) {
  return (
    <span role="img" aria-label={`${formaterNote(note)} sur 5`} className={cx('inline-flex shrink-0 items-center gap-0.5', className)}>
      {[1, 2, 3, 4, 5].map((rang) => {
        const remplissage = Math.max(0, Math.min(1, note - rang + 1))
        return (
          <span key={rang} className={cx('relative inline-block', tailles[taille])}>
            <Star aria-hidden="true" className={cx('absolute inset-0 text-bordure-champ', tailles[taille])} />
            {remplissage > 0 ? (
              <span className="absolute inset-y-0 left-0 overflow-hidden" style={{ width: `${remplissage * 100}%` }}>
                <Star aria-hidden="true" className={cx('fill-etoile text-etoile', tailles[taille])} />
              </span>
            ) : null}
          </span>
        )
      })}
    </span>
  )
}
