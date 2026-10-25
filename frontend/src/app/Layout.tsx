import { Link, NavLink, Outlet } from 'react-router'
import { MenuCompte } from './MenuCompte'

const liens = [
  { vers: '/echange', libelle: 'Échange' },
  { vers: '/store', libelle: 'Store' },
  { vers: '/devs', libelle: 'Trouver un dev' },
  { vers: '/evenements', libelle: 'Événements' },
  { vers: '/missions', libelle: 'Missions' },
]

export function Layout() {
  return (
    <div className="relative min-h-svh overflow-x-clip">
      <header className="relative z-30 border-b border-line bg-canvas">
        <div className="mx-auto flex w-full flex-wrap items-center gap-x-4 px-4 py-2 min-[761px]:flex-nowrap min-[761px]:gap-x-9 min-[761px]:px-10 min-[761px]:py-3.5">
          <Link to="/" className="order-1 inline-flex min-h-cible items-center text-[21px] font-bold tracking-tight text-ink no-underline min-[761px]:min-h-[52px] min-[1100px]:text-[23px]">
            Souk<span className="text-accent">.dev</span>
          </Link>
          <div className="order-2 ml-auto min-[761px]:order-3 min-[761px]:ml-0">
            <MenuCompte />
          </div>
          <nav aria-label="Navigation principale" className="order-3 -mx-4 flex w-[calc(100%+2rem)] gap-1 overflow-x-auto px-4 pb-1 min-[761px]:order-2 min-[761px]:mx-0 min-[761px]:w-auto min-[761px]:flex-1 min-[761px]:px-0 min-[761px]:pb-0">
            {liens.map((lien) => (
              <NavLink
                key={lien.vers}
                to={lien.vers}
                end={lien.vers !== '/echange'}
                className={({ isActive }) =>
                  isActive
                    ? 'inline-flex min-h-cible shrink-0 items-center rounded-lg bg-nav px-3.5 font-semibold text-[15px] whitespace-nowrap text-accent no-underline min-[761px]:min-h-[52px] min-[1100px]:text-[17px]'
                    : 'inline-flex min-h-cible shrink-0 items-center rounded-lg px-3.5 font-medium text-[15px] whitespace-nowrap text-nav-texte no-underline hover:bg-page hover:text-ink min-[761px]:min-h-[52px] min-[1100px]:text-[17px]'
                }
              >
                {lien.libelle}
              </NavLink>
            ))}
          </nav>
        </div>
      </header>
      <div className="relative z-10">
        <Outlet />
      </div>
    </div>
  )
}
