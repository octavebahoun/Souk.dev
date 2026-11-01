import { useRef, type KeyboardEvent, type ReactNode } from 'react'
import { cx } from './cx'

type PuceFiltreProps = {
  presse: boolean
  onClick: () => void
  children: ReactNode
  className?: string
}

/**
 * Filtre à bascule en pastille (catégorie, étiquette, difficulté) : `aria-pressed`, 44 px de haut
 * sur mobile, bleu plein quand il est actif.
 */
export function PuceFiltre({ presse, onClick, children, className }: PuceFiltreProps) {
  return (
    <button
      type="button"
      aria-pressed={presse}
      onClick={onClick}
      className={cx(
        'inline-flex h-11 shrink-0 items-center gap-1.5 rounded-pastille border px-4 text-petit font-medium transition-colors duration-150 tablette:h-9',
        presse ? 'border-primaire bg-primaire text-sur-couleur' : 'border-bordure bg-surface text-texte hover:border-bordure-champ hover:text-titre',
        className,
      )}
    >
      {children}
    </button>
  )
}

type OptionPastille<V extends string> = { valeur: V; libelle: ReactNode; libelleAccessible?: string }

type ChoixPastillesProps<V extends string> = {
  libelle: string
  options: OptionPastille<V>[]
  valeur: V
  onChange: (valeur: V) => void
  className?: string
  /** Rendu d'une option (étoiles de note) ; par défaut, une pastille. */
  classeOption?: (choisie: boolean) => string
}

/**
 * Choix exclusif en pastilles (catégorie, format, statut, profil) : un groupe radio ARIA, une
 * seule tabulation pour le groupe, les flèches, Début et Fin changent la sélection.
 */
export function ChoixPastilles<V extends string>({ libelle, options, valeur, onChange, className, classeOption }: ChoixPastillesProps<V>) {
  const refs = useRef<Array<HTMLButtonElement | null>>([])
  const trouve = options.findIndex((option) => option.valeur === valeur)
  // Sans valeur choisie (note pas encore donnée), aucune option n'est cochée ; la première reste atteignable.
  const index = Math.max(0, trouve)

  function clavier(evenement: KeyboardEvent<HTMLDivElement>) {
    const dernier = options.length - 1
    const cibles: Record<string, number> = {
      ArrowRight: index === dernier ? 0 : index + 1,
      ArrowDown: index === dernier ? 0 : index + 1,
      ArrowLeft: index === 0 ? dernier : index - 1,
      ArrowUp: index === 0 ? dernier : index - 1,
      Home: 0,
      End: dernier,
    }
    const cible = cibles[evenement.key]
    if (cible === undefined) return
    evenement.preventDefault()
    onChange(options[cible].valeur)
    refs.current[cible]?.focus()
  }

  return (
    <div role="radiogroup" aria-label={libelle} onKeyDown={clavier} className={cx('flex flex-wrap gap-2', className)}>
      {options.map((option, position) => {
        const choisie = position === trouve
        return (
          <button
            key={option.valeur}
            ref={(element) => {
              refs.current[position] = element
            }}
            type="button"
            role="radio"
            aria-checked={choisie}
            aria-label={option.libelleAccessible}
            tabIndex={position === index ? 0 : -1}
            onClick={() => onChange(option.valeur)}
            className={
              classeOption
                ? classeOption(choisie)
                : cx(
                    'inline-flex h-11 shrink-0 items-center gap-1.5 rounded-pastille border px-4 text-petit font-medium transition-colors duration-150 tablette:h-9',
                    choisie ? 'border-primaire bg-primaire text-sur-couleur' : 'border-bordure bg-surface text-texte hover:border-bordure-champ hover:text-titre',
                  )
            }
          >
            {option.libelle}
          </button>
        )
      })}
    </div>
  )
}
