import { LockKeyhole } from 'lucide-react'
import { cx } from '../../ui'
import { moyensAffiches } from './reseaux'

/** Sous le bouton « Payer » : qui encaisse, et avec quoi (les logos désignent les moyens acceptés). */
export function MoyensDePaiement({ className }: { className?: string }) {
  return (
    <div className={cx('flex flex-col gap-2', className)}>
      <p className="m-0 flex items-center gap-1.5 text-petit text-texte-doux">
        <LockKeyhole aria-hidden="true" className="size-3.5 shrink-0" />
        Paiement sécurisé par SasPay, en Mobile Money ou par carte.
      </p>
      <p className="m-0 text-petit text-texte-doux">MTN MoMo, Moov Money, Orange Money, Wave, Celtiis Cash ou carte bancaire.</p>
      <ul aria-hidden="true" className="m-0 flex list-none flex-wrap items-center gap-1.5 p-0">
        {moyensAffiches.map((moyen) => (
          <li key={moyen.nom}>
            <img src={moyen.logo} alt="" title={moyen.nom} width={32} height={32} loading="lazy" className="size-8 rounded-petit border border-bordure bg-fond-logo object-contain" />
          </li>
        ))}
      </ul>
    </div>
  )
}
