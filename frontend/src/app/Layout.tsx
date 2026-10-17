import { Link, NavLink, Outlet } from 'react-router'

const liens = [
  { vers: '/echange', libelle: 'Échange' },
  { vers: '/store', libelle: 'Store' },
  { vers: '/devs', libelle: 'Trouver un dev' },
  { vers: '/evenements', libelle: 'Événements' },
  { vers: '/missions', libelle: 'Missions' },
]

export function Layout() {
  return (
    <div className="min-h-svh">
      <header className="relative z-30 border-b border-line bg-canvas">
        <div className="mx-auto flex max-w-[1200px] flex-wrap items-center gap-x-7 gap-y-3 px-6 py-2.5">
          <Link
            to="/"
            className="text-[21px] font-bold tracking-tight text-ink no-underline"
          >
            Souk<span className="text-accent">.dev</span>
          </Link>
          <nav aria-label="Navigation principale" className="flex flex-1 flex-wrap gap-1">
            {liens.map((lien) => (
              <NavLink
                key={lien.vers}
                to={lien.vers}
                className={({ isActive }) =>
                  isActive
                    ? 'rounded-lg bg-nav px-3.5 py-2 font-semibold text-accent no-underline'
                    : 'rounded-lg px-3.5 py-2 font-medium text-ink no-underline'
                }
              >
                {lien.libelle}
              </NavLink>
            ))}
          </nav>
          <Link
            to="/connexion"
            className="inline-flex min-h-cible items-center rounded-lg px-3.5 font-medium text-ink no-underline"
          >
            Connexion
          </Link>
        </div>
      </header>
      <Outlet />
    </div>
  )
}
