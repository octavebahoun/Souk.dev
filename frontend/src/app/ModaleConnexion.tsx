import { Bouton, LienBouton, Modale } from '../ui'

/**
 * Action réservée aux comptes connectés (proposer une mission, publier une discussion…) : un
 * visiteur l'apprend avant de remplir un formulaire, avec le chemin vers la connexion.
 */
export function ModaleConnexion({ ouverte, onFermer, action }: { ouverte: boolean; onFermer: () => void; action: string }) {
  return (
    <Modale
      ouverte={ouverte}
      onFermer={onFermer}
      taille="petite"
      titre={`Connectez-vous pour ${action}`}
      description="Un seul compte pour tous : GitHub pour les devs, un lien par e-mail pour les entreprises et les particuliers."
      pied={
        <>
          <Bouton variante="contour" onClick={onFermer}>
            Fermer
          </Bouton>
          <LienBouton to="/connexion">Se connecter</LienBouton>
        </>
      }
    >
      {null}
    </Modale>
  )
}
