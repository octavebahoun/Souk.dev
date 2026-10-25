import {
  forwardRef,
  useId,
  type InputHTMLAttributes,
  type ReactNode,
  type SelectHTMLAttributes,
  type TextareaHTMLAttributes,
} from 'react'
import { ChevronDown } from 'lucide-react'
import { t } from '../i18n'
import { classeControle, controle } from './classes'
import { cx } from './cx'

/** Attributs à poser sur le contrôle : identifiant, aide et erreur reliées. */
export type LiaisonChamp = {
  id: string
  'aria-describedby'?: string
  'aria-invalid'?: true
}

type ChampProps = {
  libelle: ReactNode
  /** Texte d'aide sous le champ (ce qu'on attend, le format). */
  aide?: ReactNode
  /** Message d'erreur : il dit quoi corriger, il est relié au champ. */
  erreur?: string | null
  facultatif?: boolean
  /** Étiquette lue mais cachée (ex. une barre de recherche avec une icône). */
  libelleCache?: boolean
  className?: string
  children: (liaison: LiaisonChamp) => ReactNode
}

export function Champ({ libelle, aide, erreur, facultatif, libelleCache, className, children }: ChampProps) {
  const id = useId()
  const idAide = aide ? `${id}-aide` : undefined
  const idErreur = erreur ? `${id}-erreur` : undefined
  const decrit = [idErreur, idAide].filter(Boolean).join(' ') || undefined

  return (
    <div className={cx('flex flex-col gap-1.5', className)}>
      <label htmlFor={id} className={cx('text-petit font-medium text-titre', libelleCache && 'pour-lecteur')}>
        {libelle}
        {facultatif ? <span className="font-normal text-texte-doux"> ({t.commun.facultatif})</span> : null}
      </label>
      {children({ id, 'aria-describedby': decrit, 'aria-invalid': erreur ? true : undefined })}
      {erreur ? (
        <p id={idErreur} className="m-0 text-petit text-danger">
          {erreur}
        </p>
      ) : null}
      {aide ? (
        <p id={idAide} className="m-0 text-petit text-texte-doux">
          {aide}
        </p>
      ) : null}
    </div>
  )
}


export const Saisie = forwardRef<HTMLInputElement, InputHTMLAttributes<HTMLInputElement>>(function Saisie(
  { className, ...reste },
  ref,
) {
  return <input ref={ref} className={cx(classeControle, className)} {...reste} />
})

export const ZoneTexte = forwardRef<HTMLTextAreaElement, TextareaHTMLAttributes<HTMLTextAreaElement>>(function ZoneTexte(
  { className, rows = 4, ...reste },
  ref,
) {
  return <textarea ref={ref} rows={rows} className={cx(controle, 'min-h-24 px-3 py-2.5 leading-6', className)} {...reste} />
})

export const Liste = forwardRef<HTMLSelectElement, SelectHTMLAttributes<HTMLSelectElement>>(function Liste(
  { className, children, ...reste },
  ref,
) {
  return (
    <div className="relative">
      <select ref={ref} className={cx(classeControle, 'appearance-none pr-10', className)} {...reste}>
        {children}
      </select>
      <ChevronDown aria-hidden="true" className="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-texte-doux" />
    </div>
  )
})

type SaisieMontantProps = Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> & { suffixe?: string }

/** Montant en FCFA entier : clavier numérique, suffixe « F » visible. */
export const SaisieMontant = forwardRef<HTMLInputElement, SaisieMontantProps>(function SaisieMontant(
  { className, suffixe = 'F', ...reste },
  ref,
) {
  return (
    <div className="relative">
      <input
        ref={ref}
        type="text"
        inputMode="numeric"
        pattern="[0-9 ]*"
        autoComplete="off"
        className={cx(classeControle, 'chiffres pr-10', className)}
        {...reste}
      />
      <span aria-hidden="true" className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-texte-doux">
        {suffixe}
      </span>
    </div>
  )
})
