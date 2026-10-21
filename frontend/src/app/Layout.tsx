import { Link, NavLink, Outlet } from 'react-router'
import { MenuCompte } from './MenuCompte'

const liens = [
  { vers: '/echange', libelle: 'Échange' },
  { vers: '/store', libelle: 'Store' },
  { vers: '/devs', libelle: 'Trouver un dev' },
  { vers: '/evenements', libelle: 'Événements' },
  { vers: '/missions', libelle: 'Missions' },
]

function Fils() {
  return (
    <div className="fils-motif" aria-hidden="true">
      <svg viewBox="0 0 240 900" fill="none">
        <path d="M200 -20C40 80 210 160 50 280S190 430 40 560 200 720 60 900" stroke="#9AA3B2" strokeWidth="1.6" />
        <path d="M230 40C80 150 220 250 90 370S210 530 70 680 220 820 90 980" stroke="#B0B8C6" strokeWidth="1.3" />
        <path d="M160 -10C30 140 180 240 20 400S170 600 30 780" stroke="#8E97A8" strokeWidth="1.1" />
      </svg>
      <svg viewBox="0 0 240 900" fill="none">
        <path d="M200 -20C40 80 210 160 50 280S190 430 40 560 200 720 60 900" stroke="#9AA3B2" strokeWidth="1.6" />
        <path d="M230 40C80 150 220 250 90 370S210 530 70 680 220 820 90 980" stroke="#B0B8C6" strokeWidth="1.3" />
        <path d="M160 -10C30 140 180 240 20 400S170 600 30 780" stroke="#8E97A8" strokeWidth="1.1" />
      </svg>
    </div>
  )
}

export function Layout() {
  return (
    <div className="relative min-h-svh overflow-x-clip">
      <Fils />
      <header className="relative z-30 border-b border-line bg-canvas">
        <div className="mx-auto flex max-w-[1200px] flex-wrap items-center gap-x-4 px-4 py-2 min-[761px]:flex-nowrap min-[761px]:gap-x-7 min-[761px]:px-6">
          <Link to="/" className="order-1 inline-flex min-h-cible items-center text-[21px] font-bold tracking-tight text-ink no-underline">
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
                    ? 'inline-flex min-h-cible shrink-0 items-center rounded-lg bg-nav px-3.5 font-semibold whitespace-nowrap text-accent no-underline'
                    : 'inline-flex min-h-cible shrink-0 items-center rounded-lg px-3.5 font-medium whitespace-nowrap text-nav-texte no-underline hover:bg-page hover:text-ink'
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
