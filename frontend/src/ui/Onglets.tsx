import { useId, useRef, type KeyboardEvent, type ReactNode } from 'react'
import { NavLink } from 'react-router'
import { cx } from './cx'

export type Onglet<V extends string> = {
  valeur: V
  libelle: ReactNode
  compteur?: number
}

type OngletsProps<V extends string> = {
  libelle: string
  onglets: Onglet<V>[]
  valeur: V
  onChange: (valeur: V) => void
  /** Rendu du panneau de l'onglet actif. */
  children: ReactNode
  className?: string
}

const classeOnglet = (actif: boolean) =>
  cx(
    'relative inline-flex h-11 shrink-0 items-center gap-2 px-1 text-corps font-medium whitespace-nowrap no-underline transition-colors duration-150 tablette:h-10',
    'after:absolute after:inset-x-0 after:-bottom-px after:h-0.5 after:rounded-pastille after:content-[""]',
    actif ? 'text-titre after:bg-primaire' : 'text-texte-doux hover:text-titre after:bg-transparent',
  )

function Compteur({ valeur }: { valeur: number }) {
  return <span className="chiffres rounded-pastille bg-surface-douce px-1.5 text-etiquette text-texte-doux">{valeur}</span>
}

/** Onglets ARIA (tablist) : flèches, Début et Fin déplacent le focus et la sélection. */
export function Onglets<V extends string>({ libelle, onglets, valeur, onChange, children, className }: OngletsProps<V>) {
  const base = useId()
  const refs = useRef<Array<HTMLButtonElement | null>>([])
  const index = Math.max(0, onglets.findIndex((onglet) => onglet.valeur === valeur))

  function clavier(evenement: KeyboardEvent<HTMLDivElement>) {
    const dernier = onglets.length - 1
    const cibles: Record<string, number> = {
      ArrowRight: index === dernier ? 0 : index + 1,
      ArrowLeft: index === 0 ? dernier : index - 1,
      Home: 0,
      End: dernier,
    }
    const cible = cibles[evenement.key]
    if (cible === undefined) return
    evenement.preventDefault()
    onChange(onglets[cible].valeur)
    refs.current[cible]?.focus()
  }

  return (
    <div className={className}>
      <div role="tablist" aria-label={libelle} onKeyDown={clavier} className="flex gap-6 overflow-x-auto border-b border-bordure">
        {onglets.map((onglet, position) => {
          const actif = position === index
          return (
            <button
              key={onglet.valeur}
              ref={(element) => {
                refs.current[position] = element
              }}
              type="button"
              role="tab"
              id={`${base}-onglet-${onglet.valeur}`}
              aria-selected={actif}
              aria-controls={`${base}-panneau`}
              tabIndex={actif ? 0 : -1}
              onClick={() => onChange(onglet.valeur)}
              className={classeOnglet(actif)}
            >
              {onglet.libelle}
              {onglet.compteur !== undefined ? <Compteur valeur={onglet.compteur} /> : null}
            </button>
          )
        })}
      </div>
      <div role="tabpanel" id={`${base}-panneau`} aria-labelledby={`${base}-onglet-${onglets[index]?.valeur}`} tabIndex={0} className="pt-5 focus-visible:outline-offset-4">
        {children}
      </div>
    </div>
  )
}

type OngletsLiensProps = {
  libelle: string
  liens: Array<{ vers: string; libelle: ReactNode; compteur?: number; fin?: boolean }>
  className?: string
}

/** Même allure, mais chaque onglet est une page (navigation, aria-current). */
export function OngletsLiens({ libelle, liens, className }: OngletsLiensProps) {
  return (
    <nav aria-label={libelle} className={cx('flex gap-6 overflow-x-auto border-b border-bordure', className)}>
      {liens.map((lien) => (
        <NavLink key={lien.vers} to={lien.vers} end={lien.fin} className={({ isActive }) => classeOnglet(isActive)}>
          {lien.libelle}
          {lien.compteur !== undefined ? <Compteur valeur={lien.compteur} /> : null}
        </NavLink>
      ))}
    </nav>
  )
}
