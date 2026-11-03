import { t } from '../i18n'
import { cx } from './cx'
import { formaterFcfa } from './format'

type MontantProps = {
  valeur: number
  /** Ajoute « / mois » ou « une fois » après le montant. */
  periode?: 'mois' | 'unique'
  /** 0 s'écrit « Gratuit ». */
  gratuitSiZero?: boolean
  className?: string
}

export function Montant({ valeur, periode, gratuitSiZero = false, className }: MontantProps) {
  if (gratuitSiZero && valeur === 0) {
    return <span className={className}>{t.commun.gratuit}</span>
  }
  return (
    <span className={cx('chiffres whitespace-nowrap', className)}>
      {formaterFcfa(valeur)}
      {periode === 'mois' ? <span className="font-normal text-texte-doux"> {t.commun.parMois}</span> : null}
      {periode === 'unique' ? <span className="font-normal text-texte-doux"> {t.commun.uneFois}</span> : null}
    </span>
  )
}
