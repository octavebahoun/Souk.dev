import { NavLink, Outlet } from 'react-router'
import { t } from '../i18n'
import { cx } from '../ui'
import { useCompte } from './compte'
import { liensEspace, liensVisibles } from './navigation'

/**
 * Espace connecté (tableau de bord, applis, copies, paiements…) : navigation latérale sur
 * bureau, rangée d'onglets défilante en haut sur tablette et mobile.
 */
export function LayoutEspace() {
  const { data: compte } = useCompte()
  const liens = liensVisibles(liensEspace, compte ?? null)

  return (
    <div className="mx-auto w-full max-w-espace px-4 py-6 tablette:px-6 tablette:py-8 bureau:grid bureau:grid-cols-[15rem_1fr] bureau:gap-10 bureau:px-8">
      <nav aria-label={t.navigation.espace} className="-mx-4 mb-6 overflow-x-auto px-4 bureau:mx-0 bureau:mb-0 bureau:overflow-visible bureau:px-0">
        <p className="m-0 mb-3 hidden text-etiquette text-texte-doux uppercase bureau:block">{t.navigation.espace}</p>
        <ul className="m-0 flex list-none gap-1 p-0 bureau:sticky bureau:top-24 bureau:flex-col">
          {liens.map((lien) => (
            <li key={lien.vers} className="shrink-0">
              <NavLink
                to={lien.vers}
                end={lien.fin}
                className={({ isActive }) =>
                  cx(
                    'flex h-11 items-center gap-2.5 rounded-moyen px-3 text-corps font-medium whitespace-nowrap no-underline transition-colors duration-150 bureau:h-10',
                    isActive ? 'bg-surface-accent text-sur-primaire-doux' : 'text-texte hover:bg-surface-douce hover:text-titre',
                  )
                }
              >
                <lien.icone aria-hidden="true" className="size-4.5 shrink-0" />
                {lien.libelle}
              </NavLink>
            </li>
          ))}
        </ul>
      </nav>
      <div className="min-w-0">
        <Outlet />
      </div>
    </div>
  )
}
