import { Outlet } from 'react-router'
import { t } from '../i18n'
import { BandeauHorsLigne, cx } from '../ui'
import { BarreOnglets } from './BarreOnglets'
import { EnTeteSite } from './EnTeteSite'
import { PiedDePage } from './PiedDePage'

/**
 * Coquille des pages : lien d'évitement, bandeau hors ligne, en-tête, contenu, pied de page,
 * et barre d'onglets en bas sur mobile.
 */
export function Layout({ avecPied = true }: { avecPied?: boolean }) {
  return (
    <div className="flex min-h-svh flex-col">
      <a
        href="#contenu"
        className="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[70] focus:rounded-moyen focus:bg-surface focus:px-4 focus:py-2 focus:shadow-3"
      >
        {t.navigation.allerAuContenu}
      </a>
      <BandeauHorsLigne />
      <EnTeteSite />
      <main
        id="contenu"
        tabIndex={-1}
        className={cx('flex-1 focus:outline-none', !avecPied && 'pb-[calc(var(--spacing-barre-onglets)+env(safe-area-inset-bottom))] tablette:pb-0')}
      >
        <Outlet />
      </main>
      {avecPied ? <PiedDePage /> : null}
      <BarreOnglets />
    </div>
  )
}
