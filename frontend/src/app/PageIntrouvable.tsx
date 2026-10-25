import { EtatVide, LienBouton } from '../ui'
import { useTitrePage } from './titre'

export function PageIntrouvable() {
  useTitrePage('Page introuvable')
  return (
    <div className="mx-auto w-full max-w-site px-4 py-8">
      <EtatVide
        niveau={1}
        illustration="erreur-404"
        titre="Cette page n'existe pas"
        description="Le lien est peut-être ancien, ou l'adresse contient une faute de frappe."
        action={<LienBouton to="/">Retour à l'accueil</LienBouton>}
        actionSecondaire={
          <LienBouton to="/store" variante="contour">
            Voir le Store
          </LienBouton>
        }
      />
    </div>
  )
}
