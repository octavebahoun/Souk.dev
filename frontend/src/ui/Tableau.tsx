import type { Key, ReactNode } from 'react'
import { cx } from './cx'

export type Colonne<L> = {
  cle: string
  entete: ReactNode
  rendu: (ligne: L) => ReactNode
  /** Chiffres alignés à droite (montants, compteurs). */
  alignement?: 'gauche' | 'droite'
  /** Colonne principale : en tête de la carte sur mobile. */
  principale?: boolean
  masquerSurMobile?: boolean
  largeur?: string
}

type TableauProps<L> = {
  legende: string
  /** La légende reste lue par les lecteurs d'écran, sans s'afficher. */
  legendeCachee?: boolean
  colonnes: Colonne<L>[]
  lignes: L[]
  cleLigne: (ligne: L) => Key
  className?: string
}

/**
 * Tableau de données (factures, revenus, admin). Sur mobile, chaque ligne devient une carte
 * qui reprend les en-têtes : aucun défilement horizontal.
 */
export function Tableau<L>({ legende, legendeCachee = true, colonnes, lignes, cleLigne, className }: TableauProps<L>) {
  const principale = colonnes.find((colonne) => colonne.principale) ?? colonnes[0]
  const autres = colonnes.filter((colonne) => colonne !== principale && !colonne.masquerSurMobile)

  return (
    <div className={cx('overflow-hidden rounded-moyen border border-bordure bg-surface shadow-1', className)}>
      <table className="hidden w-full border-collapse text-left tablette:table">
        <caption className={legendeCachee ? 'pour-lecteur' : 'px-4 pt-4 text-left text-titre-3'}>{legende}</caption>
        <thead>
          <tr className="border-b border-bordure bg-surface-douce">
            {colonnes.map((colonne) => (
              <th
                key={colonne.cle}
                scope="col"
                style={colonne.largeur ? { width: colonne.largeur } : undefined}
                className={cx('px-4 py-2.5 text-etiquette text-texte-doux uppercase', colonne.alignement === 'droite' && 'text-right')}
              >
                {colonne.entete}
              </th>
            ))}
          </tr>
        </thead>
        <tbody className="divide-y divide-bordure">
          {lignes.map((ligne) => (
            <tr key={cleLigne(ligne)} className="transition-colors duration-150 hover:bg-surface-douce">
              {colonnes.map((colonne) => (
                <td key={colonne.cle} className={cx('px-4 py-3 align-middle text-corps', colonne.alignement === 'droite' && 'chiffres text-right')}>
                  {colonne.rendu(ligne)}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>

      <ul aria-label={legende} className="m-0 list-none divide-y divide-bordure p-0 tablette:hidden">
        {lignes.map((ligne) => (
          <li key={cleLigne(ligne)} className="flex flex-col gap-2 px-4 py-3.5">
            <div className="font-medium text-titre">{principale.rendu(ligne)}</div>
            <dl className="m-0 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5">
              {autres.map((colonne) => (
                <div key={colonne.cle} className="contents">
                  <dt className="text-petit text-texte-doux">{colonne.entete}</dt>
                  <dd className={cx('m-0 text-right text-corps', colonne.alignement === 'droite' && 'chiffres')}>{colonne.rendu(ligne)}</dd>
                </div>
              ))}
            </dl>
          </li>
        ))}
      </ul>
    </div>
  )
}
