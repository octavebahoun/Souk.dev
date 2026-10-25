import { useId, type InputHTMLAttributes, type ReactNode } from 'react'
import { Check } from 'lucide-react'
import { cx } from './cx'

type CaseACocherProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> & {
  libelle: ReactNode
  aide?: ReactNode
}

export function CaseACocher({ libelle, aide, className, id, ...reste }: CaseACocherProps) {
  const genere = useId()
  const identifiant = id ?? genere
  return (
    <div className={cx('flex items-start gap-3', className)}>
      <input
        id={identifiant}
        type="checkbox"
        aria-describedby={aide ? `${identifiant}-aide` : undefined}
        className="mt-0.5 size-5 shrink-0 cursor-pointer rounded-petit accent-[var(--color-primaire)]"
        {...reste}
      />
      <div className="flex flex-col gap-0.5">
        <label htmlFor={identifiant} className="cursor-pointer text-corps text-titre">
          {libelle}
        </label>
        {aide ? (
          <p id={`${identifiant}-aide`} className="m-0 text-petit text-texte-doux">
            {aide}
          </p>
        ) : null}
      </div>
    </div>
  )
}

type InterrupteurProps = {
  actif: boolean
  onChange: (actif: boolean) => void
  libelle: ReactNode
  aide?: ReactNode
  disabled?: boolean
}

/**
 * Choix binaire appliqué tout de suite (préférence de notification, disponibilité).
 * Toute la ligne est la cible (44 px au moins) : cliquer sur le libellé bascule aussi.
 */
export function Interrupteur({ actif, onChange, libelle, aide, disabled }: InterrupteurProps) {
  const id = useId()
  return (
    <button
      type="button"
      role="switch"
      aria-checked={actif}
      aria-labelledby={`${id}-libelle`}
      aria-describedby={aide ? `${id}-aide` : undefined}
      disabled={disabled}
      onClick={() => onChange(!actif)}
      className="flex min-h-11 w-full cursor-pointer items-center justify-between gap-4 rounded-moyen py-1.5 text-left disabled:cursor-not-allowed disabled:opacity-50"
    >
      <span className="flex flex-col gap-0.5">
        <span id={`${id}-libelle`} className="text-corps text-titre">
          {libelle}
        </span>
        {aide ? (
          <span id={`${id}-aide`} className="text-petit text-texte-doux">
            {aide}
          </span>
        ) : null}
      </span>
      <span
        aria-hidden="true"
        className={cx(
          'relative inline-flex h-7 w-12 shrink-0 items-center rounded-pastille border transition-colors duration-150 ease-souk',
          actif ? 'border-primaire bg-primaire' : 'border-bordure-champ bg-surface-douce',
        )}
      >
        <span
          className={cx(
            'inline-block size-5 rounded-pastille bg-sur-couleur shadow-2 transition-transform duration-200 ease-souk',
            actif ? 'translate-x-6' : 'translate-x-1',
          )}
        />
      </span>
    </button>
  )
}

export type OptionChoix<V extends string> = {
  valeur: V
  libelle: ReactNode
  description?: ReactNode
  /** Contenu à droite (un prix, un logo). */
  complement?: ReactNode
  disabled?: boolean
}

type ChoixCartesProps<V extends string> = {
  nom: string
  legende: ReactNode
  legendeCachee?: boolean
  options: OptionChoix<V>[]
  valeur: V | null
  onChange: (valeur: V) => void
  colonnes?: 1 | 2 | 3
  erreur?: string | null
}

/**
 * Choix parmi peu d'options, présentées en cartes (formule d'hébergement, réseau Mobile Money).
 * Boutons radio natifs : les flèches du clavier passent d'une option à l'autre.
 */
export function ChoixCartes<V extends string>({
  nom,
  legende,
  legendeCachee,
  options,
  valeur,
  onChange,
  colonnes = 3,
  erreur,
}: ChoixCartesProps<V>) {
  const id = useId()
  return (
    <fieldset className="m-0 flex min-w-0 flex-col gap-2 border-0 p-0" aria-describedby={erreur ? `${id}-erreur` : undefined}>
      <legend className={cx('mb-1.5 p-0 text-petit font-medium text-titre', legendeCachee && 'pour-lecteur')}>{legende}</legend>
      <div
        className={cx(
          'grid gap-3',
          colonnes === 2 && 'tablette:grid-cols-2',
          colonnes === 3 && 'tablette:grid-cols-3',
        )}
      >
        {options.map((option) => {
          const choisie = option.valeur === valeur
          return (
            <label
              key={option.valeur}
              className={cx(
                'relative flex cursor-pointer items-start gap-3 rounded-moyen border bg-surface p-4 transition-[border-color,box-shadow,background-color] duration-150 ease-souk',
                'has-[:focus-visible]:outline-3 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-lien',
                choisie ? 'border-primaire bg-primaire-doux shadow-focus' : 'border-bordure hover:border-bordure-champ',
                option.disabled && 'cursor-not-allowed opacity-50',
              )}
            >
              <input
                type="radio"
                name={nom}
                value={option.valeur}
                checked={choisie}
                disabled={option.disabled}
                onChange={() => onChange(option.valeur)}
                className="pour-lecteur"
              />
              <span
                aria-hidden="true"
                className={cx(
                  'mt-0.5 grid size-5 shrink-0 place-items-center rounded-pastille border',
                  choisie ? 'border-primaire bg-primaire text-sur-couleur' : 'border-bordure-champ bg-surface',
                )}
              >
                {choisie ? <Check className="size-3.5" strokeWidth={3} /> : null}
              </span>
              <span className="flex min-w-0 flex-1 flex-col gap-1">
                <span className="flex items-start justify-between gap-2">
                  <span className="font-medium text-titre">{option.libelle}</span>
                  {option.complement}
                </span>
                {option.description ? <span className="text-petit text-texte-doux">{option.description}</span> : null}
              </span>
            </label>
          )
        })}
      </div>
      {erreur ? (
        <p id={`${id}-erreur`} className="m-0 text-petit text-danger">
          {erreur}
        </p>
      ) : null}
    </fieldset>
  )
}
