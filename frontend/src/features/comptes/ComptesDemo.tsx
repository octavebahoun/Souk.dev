import { useState } from 'react'
import { ArrowRight, FlaskConical } from 'lucide-react'
import { messageAction } from '../echange/api'
import { connexionDemo } from './api'
import { useComptesDemo, type CompteDemo } from './demo'
import { Alerte, Avatar, cx } from '../../ui'

function destination(compte: CompteDemo): string {
  if (compte.est_admin) return '/admin/finances'
  return compte.profil === 'client' ? '/tableau' : '/echange'
}

/**
 * Comptes de démonstration, quand la plateforme les ouvre (mode jury du concours, ou poste de
 * développement) : une session en un clic, sans e-mail ni GitHub. Données fictives.
 */
export function ComptesDemo({ retour }: { retour?: string | null }) {
  const demo = useComptesDemo()
  const [enCours, setEnCours] = useState<string | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)

  if (!demo.data) return null

  async function ouvrir(compte: CompteDemo) {
    setEnCours(compte.username)
    setErreur(null)
    try {
      await connexionDemo(compte.username)
      window.location.assign(retour ?? destination(compte))
    } catch (erreurHttp) {
      setErreur(messageAction(erreurHttp, 'Ce compte de démonstration ne s’ouvre pas. Réessayez dans un instant.'))
      setEnCours(null)
    }
  }

  const groupes = [
    { titre: 'Développeurs', comptes: demo.data.comptes.filter((compte) => compte.profil === 'dev' && !compte.est_admin) },
    { titre: 'Entreprises et particuliers', comptes: demo.data.comptes.filter((compte) => compte.profil === 'client') },
    { titre: 'Équipe (développement seulement)', comptes: demo.data.comptes.filter((compte) => compte.est_admin) },
  ].filter((groupe) => groupe.comptes.length > 0)

  return (
    <section id="comptes-demo" aria-labelledby="titre-demo" className="flex scroll-mt-24 flex-col gap-3 rounded-moyen border border-primaire bg-surface-accent p-4">
      <div className="flex flex-col gap-1">
        <h2 id="titre-demo" className="m-0 flex items-center gap-2 text-corps font-semibold text-titre">
          <FlaskConical aria-hidden="true" className="size-4 text-sur-primaire-doux" />
          Comptes de démonstration
        </h2>
        <p className="m-0 text-petit text-texte">
          Pour le jury et les curieux : entrez en un clic, sans e-mail ni GitHub. Données fictives{demo.data.paiementSimule ? ', paiement simulé : aucun argent ne bouge' : ''}.
        </p>
      </div>
      {groupes.map((groupe) => (
        <div key={groupe.titre} className="flex flex-col gap-1.5">
          <p className="m-0 text-etiquette text-texte-doux uppercase">{groupe.titre}</p>
          <ul className="m-0 flex list-none flex-col gap-1.5 p-0">
            {groupe.comptes.map((compte) => (
              <li key={compte.username}>
                <button
                  type="button"
                  disabled={enCours !== null}
                  onClick={() => void ouvrir(compte)}
                  className={cx(
                    'flex min-h-11 w-full items-center gap-3 rounded-moyen border border-bordure bg-surface px-3 py-2 text-left transition-colors duration-150 hover:border-bordure-champ hover:bg-surface-douce disabled:cursor-wait disabled:opacity-70',
                    enCours === compte.username && 'border-primaire',
                  )}
                >
                  <Avatar nom={compte.nom} url={compte.avatar_url} taille="sm" />
                  <span className="flex min-w-0 flex-1 flex-col leading-tight">
                    <span className="truncate text-corps font-medium text-titre">{compte.nom}</span>
                    {compte.bio ? <span className="line-clamp-1 text-petit text-texte-doux">{compte.bio}</span> : null}
                  </span>
                  <ArrowRight aria-hidden="true" className="size-4 shrink-0 text-texte-doux" />
                  <span className="pour-lecteur">{enCours === compte.username ? ' (ouverture en cours)' : ''}</span>
                </button>
              </li>
            ))}
          </ul>
        </div>
      ))}
      {erreur ? <Alerte ton="danger">{erreur}</Alerte> : null}
    </section>
  )
}
