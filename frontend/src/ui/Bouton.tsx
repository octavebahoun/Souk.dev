import { forwardRef, type ButtonHTMLAttributes, type ComponentProps, type ReactNode } from 'react'
import { Link } from 'react-router'
import { LoaderCircle } from 'lucide-react'
import { baseBouton, classesBouton, variantesBouton, type TailleBouton, type VarianteBouton } from './classes'
import { cx } from './cx'

type Commun = {
  variante?: VarianteBouton
  taille?: TailleBouton
  icone?: ReactNode
  iconeFin?: ReactNode
  pleineLargeur?: boolean
}

type BoutonProps = Commun &
  ButtonHTMLAttributes<HTMLButtonElement> & {
    /** Action en cours : indicateur, bouton inactif, annoncé aux lecteurs d'écran. */
    chargement?: boolean
  }

export const Bouton = forwardRef<HTMLButtonElement, BoutonProps>(function Bouton(
  { variante, taille, icone, iconeFin, pleineLargeur, chargement = false, disabled, className, children, type = 'button', ...reste },
  ref,
) {
  return (
    <button
      ref={ref}
      type={type}
      disabled={disabled || chargement}
      aria-busy={chargement || undefined}
      className={cx(classesBouton(variante, taille, pleineLargeur), className)}
      {...reste}
    >
      {chargement ? <LoaderCircle aria-hidden="true" className="size-4 animate-tourne" /> : icone}
      {children}
      {iconeFin}
    </button>
  )
})

type LienBoutonProps = Commun & ComponentProps<typeof Link>

/** Lien de navigation qui a l'allure d'un bouton (même variantes, mêmes tailles). */
export function LienBouton({ variante, taille, icone, iconeFin, pleineLargeur, className, children, ...reste }: LienBoutonProps) {
  return (
    <Link className={cx(classesBouton(variante, taille, pleineLargeur), className)} {...reste}>
      {icone}
      {children}
      {iconeFin}
    </Link>
  )
}

type BoutonIconeProps = Omit<ButtonHTMLAttributes<HTMLButtonElement>, 'aria-label'> & {
  /** Obligatoire : le bouton n'a pas de texte visible. */
  libelle: string
  variante?: Exclude<VarianteBouton, 'primaire' | 'danger'>
  children: ReactNode
}

export const BoutonIcone = forwardRef<HTMLButtonElement, BoutonIconeProps>(function BoutonIcone(
  { libelle, variante = 'fantome', className, children, type = 'button', ...reste },
  ref,
) {
  return (
    <button
      ref={ref}
      type={type}
      aria-label={libelle}
      title={libelle}
      className={cx(baseBouton, variantesBouton[variante], 'size-11 p-0 tablette:size-9', className)}
      {...reste}
    >
      {children}
    </button>
  )
})
