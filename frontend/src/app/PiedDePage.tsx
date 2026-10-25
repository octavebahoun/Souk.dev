import { Link } from 'react-router'
import { formater, t } from '../i18n'
import { Logo } from '../ui'

const colonnes = [
  {
    titre: t.pied.produit,
    liens: [
      { vers: '/store', libelle: t.navigation.store },
      { vers: '/publier', libelle: t.navigation.publier },
      { vers: '/copies', libelle: t.navigation.mesCopies },
    ],
  },
  {
    titre: t.pied.communaute,
    liens: [
      { vers: '/echange', libelle: t.navigation.echange },
      { vers: '/devs', libelle: t.navigation.devs },
      { vers: '/evenements', libelle: t.navigation.evenements },
    ],
  },
]

/** Pied des pages publiques (accueil, store, échange). L'espace connecté n'en a pas. */
export function PiedDePage() {
  return (
    <footer className="border-t border-bordure bg-surface pb-[calc(var(--spacing-barre-onglets)+env(safe-area-inset-bottom))] tablette:pb-0">
      <div className="mx-auto grid w-full max-w-site gap-10 px-4 py-12 tablette:grid-cols-[1.4fr_1fr_1fr] tablette:px-6 bureau:px-8">
        <div className="flex max-w-xs flex-col gap-3">
          <Logo />
          <p className="m-0 text-petit text-texte-doux">{t.marque.accroche}. Publiez, déployez en un clic, faites-vous aider par la communauté.</p>
        </div>
        {colonnes.map((colonne) => (
          <div key={colonne.titre} className="flex flex-col gap-3">
            <h2 className="m-0 text-etiquette text-texte-doux uppercase">{colonne.titre}</h2>
            <ul className="m-0 flex list-none flex-col gap-2 p-0">
              {colonne.liens.map((lien) => (
                <li key={lien.vers}>
                  <Link to={lien.vers} className="text-petit text-texte no-underline hover:text-titre hover:underline">
                    {lien.libelle}
                  </Link>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </div>
      <div className="border-t border-bordure">
        <p className="mx-auto m-0 w-full max-w-site px-4 py-5 text-petit text-texte-doux tablette:px-6 bureau:px-8">
          {formater(t.pied.droits, { annee: new Date().getFullYear() })}
        </p>
      </div>
    </footer>
  )
}
