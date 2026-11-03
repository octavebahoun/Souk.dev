import { Monitor, Moon, Sun } from 'lucide-react'
import { t } from '../i18n'
import { cx } from './cx'
import { useTheme, type ChoixTheme } from './useTheme'


const options: Array<{ valeur: ChoixTheme; libelle: string; icone: typeof Sun }> = [
  { valeur: 'clair', libelle: t.theme.clair, icone: Sun },
  { valeur: 'sombre', libelle: t.theme.sombre, icone: Moon },
  { valeur: 'systeme', libelle: t.theme.systeme, icone: Monitor },
]

/** Sélecteur clair / sombre / système : boutons radio natifs (flèches du clavier). */
export function SelecteurTheme({ className }: { className?: string }) {
  const [choix, changer] = useTheme()
  return (
    <fieldset className={cx('m-0 grid grid-cols-3 gap-1 rounded-moyen border-0 bg-surface-douce p-1', className)}>
      <legend className="pour-lecteur">{t.theme.libelle}</legend>
      {options.map(({ valeur, libelle, icone: Icone }) => {
        const actif = valeur === choix
        return (
          <label
            key={valeur}
            className={cx(
              'flex h-11 cursor-pointer items-center justify-center gap-1.5 rounded-petit text-petit font-medium transition-colors duration-150 tablette:h-9',
              'has-[:focus-visible]:outline-3 has-[:focus-visible]:outline-offset-1 has-[:focus-visible]:outline-lien',
              actif ? 'bg-surface text-titre shadow-1' : 'text-texte-doux hover:text-titre',
            )}
          >
            <input type="radio" name="souk-theme" value={valeur} checked={actif} onChange={() => changer(valeur)} className="pour-lecteur" />
            <Icone aria-hidden="true" className="size-4" />
            {libelle}
          </label>
        )
      })}
    </fieldset>
  )
}

const suivant: Record<ChoixTheme, ChoixTheme> = { clair: 'sombre', sombre: 'systeme', systeme: 'clair' }

/** Bouton de l'en-tête : passe de clair à sombre, puis au réglage du système. */
export function BoutonTheme({ className }: { className?: string }) {
  const [choix, changer] = useTheme()
  const actuel = options.find((option) => option.valeur === choix) ?? options[2]
  const Icone = actuel.icone
  const libelle = `${t.theme.libelle} : ${actuel.libelle.toLowerCase()}. Changer de thème`
  return (
    <button
      type="button"
      onClick={() => changer(suivant[choix])}
      aria-label={libelle}
      title={libelle}
      className={cx('grid size-11 place-items-center rounded-moyen transition-colors duration-150 tablette:size-9', className)}
    >
      <Icone aria-hidden="true" className="size-5" />
    </button>
  )
}

