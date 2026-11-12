import { useEffect, useRef } from 'react'
import { NavLink, Outlet, useLocation } from 'react-router'
import { t } from '../i18n'
import { AccesRefuse, Alerte, LienBouton, cx } from '../ui'
import { useCompte } from './compte'
import { lienConnexion } from './retour'
import { useTitrePage } from './titre'
import { liensEspace, liensVisibles } from './navigation'

/**
 * Espace connecté (tableau de bord, applis, copies, paiements…) : navigation latérale sur
 * bureau, rangée d'onglets défilante en haut sur tablette et mobile.
 */
export function LayoutEspace() {
  const { data: compte, isSuccess } = useCompte()
  // Un visiteur l'apprend avant de remplir un formulaire (publier une appli…), pas à l'envoi.
  const visiteur = isSuccess && compte === null
  const liens = liensVisibles(liensEspace, compte ?? null)
  const navigation = useRef<HTMLUListElement>(null)
  const { pathname } = useLocation()
  // L'API refuse déjà (403) : l'interface d'administration ne s'affiche pas non plus à un autre compte.
  const adminRefuse = pathname.startsWith('/admin') && isSuccess && compte !== null && compte.est_admin !== true

  // Sur mobile et tablette, la rangée d'onglets défile : la page active reste visible.
  useEffect(() => {
    const actif = navigation.current?.querySelector<HTMLElement>('[aria-current="page"]')
    if (actif && typeof actif.scrollIntoView === 'function') actif.scrollIntoView({ block: 'nearest', inline: 'center' })
  }, [pathname, liens.length])

  return (
    <div className="mx-auto w-full max-w-espace px-4 py-6 tablette:px-6 tablette:py-8 bureau:grid bureau:grid-cols-[15rem_1fr] bureau:gap-10 bureau:px-8">
      <nav aria-label={t.navigation.espace} className="sans-barre -mx-4 mb-6 overflow-x-auto px-4 bureau:mx-0 bureau:mb-0 bureau:overflow-visible bureau:px-0">
        <p className="m-0 mb-3 hidden text-etiquette text-texte-doux uppercase bureau:block">{t.navigation.espace}</p>
        <ul ref={navigation} className="m-0 flex list-none gap-1 p-0 bureau:sticky bureau:top-24 bureau:flex-col">
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
      <div className="flex min-w-0 flex-col gap-6">
        {compte?.suspendu_le ? (
          <Alerte ton="danger" titre="Votre compte est suspendu">
            Motif : {compte.suspension_motif ?? 'non précisé'}. Vos données sont gardées. Pour contester, répondez à l’e-mail reçu.
          </Alerte>
        ) : null}
        {visiteur ? (
          <EspaceVisiteur />
        ) : adminRefuse ? (
          <AccesRefuse description="Cette page est réservée à l’équipe de Souk.dev." action={<LienBouton to="/tableau">Revenir à mon tableau de bord</LienBouton>} />
        ) : (
          <Outlet />
        )}
      </div>
    </div>
  )
}

function EspaceVisiteur() {
  const { pathname } = useLocation()
  useTitrePage(pathname === '/publier' ? 'Publier une appli' : 'Connexion requise')

  // Publier se comprend avant de créer un compte : ce qu'il faut, ce qui se passe, ce que ça rapporte.
  if (pathname === '/publier') {
    return <PublierVisiteur />
  }

  return (
    <AccesRefuse
      description="Cet espace est celui de votre compte : connectez-vous pour y accéder. GitHub pour les devs, un lien par e-mail pour les entreprises et les particuliers."
      action={<LienBouton to={lienConnexion(pathname)}>Se connecter</LienBouton>}
    />
  )
}

const etapesPublier = [
  { titre: 'Liez un dépôt GitHub public', texte: 'Collez son adresse. Pas de fichier Docker ni de soukdev.json ? Souk.dev les écrit pour vous, vous n’avez qu’à valider le commit.' },
  { titre: 'Souk.dev analyse le dépôt', texte: 'Un secret oublié dans le code bloque la publication. Les dépendances à failles connues sont listées dans un rapport, visible sur la fiche.' },
  { titre: 'Fixez votre prix', texte: 'Gratuit, mensuel ou payé une fois. Il vous revient en entier : Souk.dev ne prend aucune commission dessus et ne gagne que sur l’hébergement.' },
  { titre: 'Les clients déploient en un clic', texte: 'Chaque client reçoit sa copie isolée, à son nom. Vous êtes reversé en Mobile Money et vos mises à jour leur sont proposées.' },
]

function PublierVisiteur() {
  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-2">
        <h1 className="m-0 text-titre-1 text-titre">Publier une appli sur le Store</h1>
        <p className="m-0 max-w-prose text-corps-l text-texte">
          Votre dépôt GitHub devient un produit que n’importe quelle entreprise peut déployer et payer en Mobile Money. Voici comment ça se passe.
        </p>
      </div>
      <ol className="m-0 grid list-none gap-4 p-0 tablette:grid-cols-2">
        {etapesPublier.map((etape, index) => (
          <li key={etape.titre} className="flex gap-3 rounded-moyen border border-bordure bg-surface p-4 shadow-1">
            <span aria-hidden="true" className="chiffres grid size-8 shrink-0 place-items-center rounded-pastille bg-surface-accent font-semibold text-sur-primaire-doux">
              {index + 1}
            </span>
            <div className="flex flex-col gap-1">
              <h2 className="m-0 text-corps font-semibold text-titre">{etape.titre}</h2>
              <p className="m-0 text-petit text-texte">{etape.texte}</p>
            </div>
          </li>
        ))}
      </ol>
      <div className="flex flex-col items-start gap-2 rounded-moyen border border-bordure bg-surface-douce p-4">
        <p className="m-0 text-corps text-texte">
          <strong className="font-semibold text-titre">Ce qu’il vous faut :</strong> un compte GitHub, un dépôt public qui démarre avec Docker (ou une pile courante : Node, PHP, Python, site
          statique), un lien de démo en https et une à cinq captures d’écran.
        </p>
        <LienBouton to={lienConnexion('/publier', 'dev')}>Se connecter avec GitHub pour publier</LienBouton>
      </div>
    </div>
  )
}
