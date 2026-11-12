import { useEffect } from 'react'
import { Outlet, useLocation, useNavigate } from 'react-router'
import { t } from '../i18n'
import { BandeauHorsLigne, cx } from '../ui'
import { BarreOnglets } from './BarreOnglets'
import { EnTeteSite } from './EnTeteSite'
import { PiedDePage } from './PiedDePage'
import { useCompte } from './compte'
import { prendreRetour } from './retour'

/**
 * Coquille des pages : lien d'évitement, bandeau hors ligne, en-tête, contenu, pied de page,
 * et barre d'onglets en bas sur mobile.
 */
export function Layout({ avecPied = true }: { avecPied?: boolean }) {
  const { data: compte } = useCompte()
  const { pathname } = useLocation()
  const naviguer = useNavigate()

  // La connexion ramène sur le Store ou l'Échange : si la personne venait d'ailleurs (une
  // commande en cours, un correctif à proposer), elle y retourne.
  useEffect(() => {
    if (!compte || (pathname !== '/store' && pathname !== '/echange')) return
    const retour = prendreRetour()
    if (retour && retour !== pathname) void naviguer(retour, { replace: true })
  }, [compte, pathname, naviguer])

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
