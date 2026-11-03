import { useState } from 'react'
import { NavLink } from 'react-router'
import { Home, LayoutGrid, LogIn, LogOut, Menu, MessagesSquare, UserRound, Gauge } from 'lucide-react'
import { t } from '../i18n'
import { cx, Modale, SelecteurTheme } from '../ui'
import { useCompte, useDeconnexion } from './compte'
import { liensEspace, liensPrincipaux, liensVisibles } from './navigation'

const classeOnglet = ({ isActive }: { isActive: boolean }) =>
  cx(
    'flex h-full flex-1 flex-col items-center justify-center gap-1 text-micro font-medium no-underline transition-colors duration-150',
    isActive ? 'text-primaire dark:text-lien' : 'text-texte-doux',
  )

/**
 * Destinations principales à portée du pouce sur mobile (moins de 768 px).
 * Le reste (devs, événements, thème, compte) est dans « Menu ».
 */
export function BarreOnglets() {
  const { data: compte } = useCompte()
  const [menu, setMenu] = useState(false)
  const deconnecter = useDeconnexion()

  return (
    <>
      <nav
        aria-label={t.navigation.onglets}
        className="fixed inset-x-0 bottom-0 z-30 flex h-[calc(var(--spacing-barre-onglets)+env(safe-area-inset-bottom))] border-t border-bordure bg-surface/95 pb-[env(safe-area-inset-bottom)] backdrop-blur tablette:hidden"
      >
        {compte ? null : (
          <NavLink to="/" end className={classeOnglet}>
            <Home aria-hidden="true" className="size-5" />
            {t.navigation.accueil}
          </NavLink>
        )}
        <NavLink to="/store" className={classeOnglet}>
          <LayoutGrid aria-hidden="true" className="size-5" />
          {t.navigation.store}
        </NavLink>
        <NavLink to="/echange" className={classeOnglet}>
          <MessagesSquare aria-hidden="true" className="size-5" />
          {t.navigation.echange}
        </NavLink>
        {compte ? (
          <NavLink to="/tableau" className={classeOnglet}>
            <Gauge aria-hidden="true" className="size-5" />
            {t.navigation.espace}
          </NavLink>
        ) : null}
        <button type="button" onClick={() => setMenu(true)} aria-haspopup="dialog" className={classeOnglet({ isActive: false })}>
          <Menu aria-hidden="true" className="size-5" />
          {t.commun.menu}
        </button>
      </nav>

      <Modale ouverte={menu} onFermer={() => setMenu(false)} titre={t.commun.menu}>
        <div className="flex flex-col gap-5">
          <nav aria-label={t.commun.menu} className="flex flex-col">
            {[...liensPrincipaux, ...(compte ? liensVisibles(liensEspace, compte) : [])].map((lien) => (
              <NavLink
                key={lien.vers}
                to={lien.vers}
                end={lien.fin}
                onClick={() => setMenu(false)}
                className={({ isActive }) =>
                  cx(
                    'flex min-h-12 items-center gap-3 rounded-moyen px-3 text-corps-l no-underline',
                    isActive ? 'bg-surface-accent text-sur-primaire-doux' : 'text-titre hover:bg-surface-douce',
                  )
                }
              >
                <lien.icone aria-hidden="true" className="size-5 text-texte-doux" />
                {lien.libelle}
              </NavLink>
            ))}
            {compte?.username ? (
              <NavLink to={`/devs/${compte.username}`} onClick={() => setMenu(false)} className="flex min-h-12 items-center gap-3 rounded-moyen px-3 text-corps-l text-titre no-underline hover:bg-surface-douce">
                <UserRound aria-hidden="true" className="size-5 text-texte-doux" />
                {t.navigation.monProfil}
              </NavLink>
            ) : null}
          </nav>
          <div className="flex flex-col gap-2">
            <p className="m-0 text-etiquette text-texte-doux uppercase">{t.theme.libelle}</p>
            <SelecteurTheme />
          </div>
          {compte ? (
            <button
              type="button"
              onClick={() => {
                setMenu(false)
                void deconnecter()
              }}
              className="flex min-h-12 items-center gap-3 rounded-moyen px-3 text-corps-l text-danger hover:bg-fond-danger"
            >
              <LogOut aria-hidden="true" className="size-5" />
              {t.navigation.seDeconnecter}
            </button>
          ) : (
            <NavLink to="/connexion" onClick={() => setMenu(false)} className="flex min-h-12 items-center justify-center gap-2 rounded-moyen bg-primaire px-3 text-corps-l font-medium text-sur-primaire no-underline">
              <LogIn aria-hidden="true" className="size-5" />
              {t.navigation.seConnecter}
            </NavLink>
          )}
        </div>
      </Modale>
    </>
  )
}
