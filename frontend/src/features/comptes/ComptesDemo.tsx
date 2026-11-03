import { useState } from 'react'
import { messageAction } from '../echange/api'
import { connexionDemo } from './api'
import { Alerte, Bouton } from '../../ui'

const comptes = [
  { compte: 'soukdemo-awa', nom: 'Awa Dossou', role: 'dev', destination: '/echange' },
  { compte: 'soukdemo-pharmacie', nom: 'Pharmacie du Port', role: 'client', destination: '/store' },
  { compte: 'soukdemo-admin', nom: 'Équipe Souk.dev', role: 'admin', destination: '/admin/finances' },
] as const

/**
 * Développement seulement (serveur Vite) : ouvrir la session d'un compte du jeu de démo, sans
 * e-mail ni GitHub. Absent du build de production, et l'API y refuse la route.
 */
export function ComptesDemo() {
  const [enCours, setEnCours] = useState<string | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)

  async function ouvrir(compte: string, destination: string) {
    setEnCours(compte)
    setErreur(null)
    try {
      await connexionDemo(compte)
      window.location.assign(destination)
    } catch (erreurHttp) {
      setErreur(messageAction(erreurHttp, 'Session de démo impossible : chargez le jeu de démo (php artisan db:seed --class=DemoSeeder).'))
      setEnCours(null)
    }
  }

  return (
    <section aria-labelledby="titre-demo" className="flex flex-col gap-3 rounded-moyen border border-dashed border-bordure-champ p-4">
      <div className="flex flex-col gap-0.5">
        <h2 id="titre-demo" className="m-0 text-corps font-semibold text-titre">
          Comptes de démo
        </h2>
        <p className="m-0 text-petit text-texte-doux">Développement seulement : une session sans e-mail ni GitHub.</p>
      </div>
      <div className="flex flex-col gap-2">
        {comptes.map((item) => (
          <Bouton key={item.compte} variante="contour" pleineLargeur chargement={enCours === item.compte} disabled={enCours !== null} onClick={() => void ouvrir(item.compte, item.destination)}>
            {item.nom} <span className="font-normal text-texte-doux">· {item.role}</span>
          </Bouton>
        ))}
      </div>
      {erreur ? <Alerte ton="danger">{erreur}</Alerte> : null}
    </section>
  )
}
