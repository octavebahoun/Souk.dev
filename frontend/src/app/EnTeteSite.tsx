import { useState, type FormEvent } from 'react'
import { Link, NavLink, useNavigate } from 'react-router'
import { ChevronDown, Gauge, LogOut, Package, Search, Server, UserRound } from 'lucide-react'
import { t } from '../i18n'
import { Avatar, BoutonTheme, cx, LienBouton, Logo, MenuDeroulant, Squelette, type ElementMenu } from '../ui'
import { nomPays } from '../features/echange/texte'
import { Cloche } from '../features/notifications/Cloche'
import { useCompte, useDeconnexion } from './compte'
import { liensPrincipaux } from './navigation'

type EnTeteSiteProps = {
  /** Sur le hero bleu nuit de l'accueil : fond transparent, texte clair. */
  surFondSombre?: boolean
}

function Recherche({ surFondSombre }: { surFondSombre: boolean }) {
  const [terme, setTerme] = useState('')
  const naviguer = useNavigate()

  function chercher(evenement: FormEvent) {
    evenement.preventDefault()
    const q = terme.trim()
    void naviguer(q ? `/store?q=${encodeURIComponent(q)}` : '/store')
  }

  return (
    <form role="search" onSubmit={chercher} className="relative hidden bureau:block">
      <label htmlFor="recherche-site" className="pour-lecteur">
        {t.navigation.rechercher}
      </label>
      <Search aria-hidden="true" className={cx('pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2', surFondSombre ? 'text-sur-couleur/70' : 'text-texte-indice')} />
      <input
        id="recherche-site"
        type="search"
        value={terme}
        onChange={(evenement) => setTerme(evenement.target.value)}
        placeholder="Rechercher une appli…"
        className={cx(
          'h-9 w-64 rounded-moyen border pr-3 pl-9 text-petit transition-[border-color,box-shadow,width] duration-200 ease-souk focus:w-80 focus:shadow-focus focus:outline-none focus-visible:outline-none',
          surFondSombre
            ? 'border-sur-couleur/20 bg-sur-couleur/10 text-sur-couleur placeholder:text-sur-couleur/70 focus:border-sur-couleur/60'
            : 'border-bordure bg-surface-douce text-titre focus:border-lien focus:bg-surface',
        )}
      />
    </form>
  )
}

function MenuDuCompte() {
  const { data: compte, isPending } = useCompte()
  const deconnecter = useDeconnexion()

  if (isPending) return <Squelette className="size-9 rounded-pastille" />
  if (!compte) return null

  const role = compte.profil === 'client' ? t.navigation.entreprise : t.navigation.dev
  const lieu = compte.pays ? ` · ${nomPays(compte.pays)}` : ''
  const elements: ElementMenu[] = [
    {
      type: 'contenu',
      contenu: (
        <div className="flex items-center gap-3 px-3 pt-2 pb-3">
          <Avatar nom={compte.nom} secours={compte.username} url={compte.avatar_url} taille="md" />
          <div className="min-w-0">
            <p className="m-0 truncate font-medium text-titre">{compte.nom || compte.username || compte.email}</p>
            <p className="m-0 truncate text-petit text-texte-doux">
              {role}
              {lieu}
            </p>
          </div>
        </div>
      ),
    },
    { type: 'separateur' },
    ...(compte.username ? [{ type: 'lien' as const, libelle: t.navigation.monProfil, vers: `/devs/${compte.username}`, icone: <UserRound aria-hidden="true" className="size-4 text-texte-doux" /> }] : []),
    { type: 'lien', libelle: t.navigation.tableau, vers: '/tableau', icone: <Gauge aria-hidden="true" className="size-4 text-texte-doux" /> },
    { type: 'lien', libelle: t.navigation.mesCopies, vers: '/copies', icone: <Server aria-hidden="true" className="size-4 text-texte-doux" /> },
    { type: 'lien', libelle: t.navigation.mesApplis, vers: '/publier', icone: <Package aria-hidden="true" className="size-4 text-texte-doux" /> },
    { type: 'separateur' },
    { type: 'action', libelle: t.navigation.seDeconnecter, onClick: () => void deconnecter(), danger: true, icone: <LogOut aria-hidden="true" className="size-4" /> },
  ]

  return (
    <MenuDeroulant
      libelle={t.navigation.menuCompte}
      elements={elements}
      classeDeclencheur="flex h-11 items-center gap-1.5 rounded-pastille p-1 pr-2 transition-colors duration-150 hover:bg-surface-douce tablette:h-10"
      declencheur={
        <>
          <Avatar nom={compte.nom} secours={compte.username} url={compte.avatar_url} taille="sm" />
          <ChevronDown aria-hidden="true" className="hidden size-4 text-texte-doux tablette:block" />
        </>
      }
    />
  )
}

export function EnTeteSite({ surFondSombre = false }: EnTeteSiteProps) {
  const { data: compte, isPending } = useCompte()

  return (
    <header
      className={cx(
        'sticky top-0 z-30 border-b backdrop-blur',
        surFondSombre ? 'border-sur-couleur/10 bg-nuit/85 text-sur-couleur' : 'border-bordure bg-surface/90',
      )}
    >
      <div className="mx-auto flex h-16 w-full max-w-site items-center gap-6 px-4 tablette:px-6 bureau:px-8">
        <Link to="/" className="shrink-0 rounded-moyen no-underline" aria-label="Souk.dev, accueil">
          <Logo surFondSombre={surFondSombre} />
        </Link>

        <nav aria-label={t.navigation.principale} className="hidden items-center gap-1 tablette:flex">
          {liensPrincipaux.map((lien) => (
            <NavLink
              key={lien.vers}
              to={lien.vers}
              end={lien.fin}
              className={({ isActive }) =>
                cx(
                  'inline-flex h-9 items-center rounded-moyen px-3 text-corps font-medium no-underline transition-colors duration-150',
                  surFondSombre
                    ? isActive ? 'bg-sur-couleur/15 text-sur-couleur' : 'text-sur-couleur/80 hover:bg-sur-couleur/10 hover:text-sur-couleur'
                    : isActive ? 'bg-surface-accent text-sur-primaire-doux' : 'text-texte hover:bg-surface-douce hover:text-titre',
                )
              }
            >
              {lien.libelle}
            </NavLink>
          ))}
        </nav>

        <div className="ml-auto flex items-center gap-2">
          <Recherche surFondSombre={surFondSombre} />
          <BoutonTheme
            className={cx(
              'max-tablette:hidden',
              surFondSombre ? 'text-sur-couleur/80 hover:bg-sur-couleur/10 hover:text-sur-couleur' : 'text-texte-doux hover:bg-surface-douce hover:text-titre',
            )}
          />
          {isPending ? null : compte ? (
            <>
              {compte.profil === 'dev' ? (
                <LienBouton to="/publier" taille="sm" className="max-bureau:hidden">
                  {t.navigation.publier}
                </LienBouton>
              ) : null}
              <Cloche surFondSombre={surFondSombre} />
              <MenuDuCompte />
            </>
          ) : (
            <>
              <LienBouton to="/connexion" variante={surFondSombre ? 'fantome' : 'contour'} taille="sm" className={cx(surFondSombre && 'text-sur-couleur hover:bg-sur-couleur/10 hover:text-sur-couleur')}>
                {t.navigation.seConnecter}
              </LienBouton>
              <LienBouton to="/connexion?profil=dev" taille="sm" className="max-bureau:hidden">
                {t.navigation.publier}
              </LienBouton>
            </>
          )}
        </div>
      </div>
    </header>
  )
}
