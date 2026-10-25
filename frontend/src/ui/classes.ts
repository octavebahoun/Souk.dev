import { cx } from './cx'

/** Classes partagées : boutons (et liens qui en ont l'allure), contrôles de formulaire. */

export type VarianteBouton = 'primaire' | 'secondaire' | 'contour' | 'fantome' | 'danger'
export type TailleBouton = 'sm' | 'md' | 'lg'

export const baseBouton =
  'inline-flex shrink-0 items-center justify-center gap-2 rounded-moyen font-medium whitespace-nowrap no-underline select-none ' +
  'transition-[background-color,border-color,color,box-shadow,transform] duration-150 ease-souk ' +
  'active:translate-y-px disabled:pointer-events-none disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50'

export const variantesBouton: Record<VarianteBouton, string> = {
  primaire: 'bg-primaire text-sur-primaire shadow-1 hover:bg-primaire-survol active:bg-primaire-actif',
  secondaire: 'bg-primaire-doux text-sur-primaire-doux hover:bg-primaire-doux-survol',
  contour: 'border border-bordure-champ bg-surface text-titre shadow-1 hover:bg-surface-douce',
  fantome: 'text-texte hover:bg-surface-douce hover:text-titre',
  danger: 'bg-danger-solide text-sur-couleur shadow-1 hover:brightness-95',
}

// 44 px sur mobile (loi de Fitts), plus dense à partir de la tablette.
const tailles: Record<TailleBouton, string> = {
  sm: 'h-11 px-3 text-petit tablette:h-8',
  md: 'h-11 px-4 text-corps tablette:h-10',
  lg: 'h-12 px-5 text-corps-l',
}

export function classesBouton(variante: VarianteBouton = 'primaire', taille: TailleBouton = 'md', pleineLargeur = false): string {
  return cx(baseBouton, variantesBouton[variante], tailles[taille], pleineLargeur && 'w-full')
}

export const controle =
  'w-full rounded-moyen border border-bordure-champ bg-surface text-titre shadow-1 ' +
  'transition-[border-color,box-shadow] duration-150 ease-souk hover:border-texte-doux ' +
  'focus:border-lien focus:shadow-focus focus:outline-none focus-visible:outline-none ' +
  'aria-invalid:border-danger disabled:cursor-not-allowed disabled:bg-surface-douce disabled:text-texte-doux'

export const classeControle = cx(controle, 'h-11 px-3 tablette:h-10')
