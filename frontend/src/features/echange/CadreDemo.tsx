import { useCallback, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { ExternalLink, Play, Square } from 'lucide-react'
import { Alerte, Bouton, Carte, EnTeteCarte, Etapes, Modale, classesBouton, type Etape } from '../../ui'
import {
  arreterDeploiement,
  lancerCopieTest,
  messageAction,
  type Deploiement,
  type Discussion,
} from './api'

const etapes = [
  ['en_file', 'En file'],
  ['construction', 'Construction'],
  ['demarrage', 'Démarrage'],
  ['en_ligne', 'En ligne'],
] as const

/** Étapes de la copie de test, pour le composant Etapes du système. */
function etapesCopie(copie: Deploiement): Etape[] {
  const rang = etapes.findIndex(([etat]) => etat === copie.etat)
  return etapes.map(([etat, libelle], index) => ({
    id: etat,
    libelle,
    etat:
      copie.etat === 'echec' && index === Math.max(0, rang)
        ? 'echec'
        : rang === -1
          ? copie.etat === 'en_ligne' ? 'terminee' : 'a_venir'
          : index < rang || (index === rang && etat === 'en_ligne')
            ? 'terminee'
            : index === rang
              ? 'en_cours'
              : 'a_venir',
  }))
}

function adresseVisible(url: string): string {
  try {
    const lue = new URL(url)
    return `${lue.host}${lue.pathname === '/' ? '' : lue.pathname}`
  } catch {
    return url
  }
}

function messageCopie(copie: Deploiement): string {
  if (copie.etat === 'echec') return copie.erreur || 'La copie a échoué.'
  if (copie.etat === 'arrete') return 'Copie arrêtée.'
  if (copie.etat === 'arret_en_cours') return 'Arrêt de la copie en cours.'
  if (copie.etat === 'en_ligne') return 'La copie tourne.'
  return 'La copie avance. La page se met à jour toute seule.'
}

type CadreDemoProps = {
  discussion: Discussion
  auteur: boolean
}

export function CadreDemo({ discussion, auteur }: CadreDemoProps) {
  const client = useQueryClient()
  const [confirmer, setConfirmer] = useState(false)
  const [confirmerArret, setConfirmerArret] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)
  const fermer = useCallback(() => setConfirmer(false), [])
  const copie = discussion.copie_test
  const demo = discussion.demo_url
  const cadre = demo || (copie?.etat === 'en_ligne' ? copie.url : null)
  const peutLancer = auteur && !copie && (discussion.depot_url !== null || discussion.app_id !== null)
  const peutArreter =
    auteur && copie !== null && copie.etat !== 'echec' && copie.etat !== 'arrete' && copie.etat !== 'arret_en_cours'

  const lancer = useMutation({
    mutationFn: () => lancerCopieTest(discussion.id),
    onSuccess: (deploiement) => {
      client.setQueryData<Discussion>(['discussion', discussion.id], (actuelle) =>
        actuelle ? { ...actuelle, copie_test: deploiement } : actuelle,
      )
      setConfirmer(false)
      setErreur(null)
    },
    onError: (recue) => setErreur(messageAction(recue, '3 copies de test par heure.')),
  })

  const arreter = useMutation({
    mutationFn: () => arreterDeploiement(copie?.id ?? 0),
    onSuccess: () => {
      void client.invalidateQueries({ queryKey: ['discussion', discussion.id] })
      setConfirmerArret(false)
      setErreur(null)
    },
    onError: (recue) => setErreur(messageAction(recue, '3 copies de test par heure.')),
  })

  if (!discussion.depot_url && !cadre && !copie && !peutLancer) return null

  return (
    <Carte as="section" aria-labelledby="titre-projet" className="flex flex-col gap-4">
      <EnTeteCarte
        titre={<span id="titre-projet">Le projet</span>}
        action={
          discussion.depot_url ? (
            <a href={discussion.depot_url} target="_blank" rel="noreferrer" className={classesBouton('contour', 'sm')}>
              <span className="max-w-56 truncate font-mono">{adresseVisible(discussion.depot_url)}</span>
              <ExternalLink aria-hidden="true" className="size-3.5" />
              <span className="pour-lecteur"> (nouvel onglet)</span>
            </a>
          ) : undefined
        }
      />

      {!cadre ? (
        <p className="m-0 rounded-moyen border border-dashed border-bordure bg-surface-douce px-4 py-6 text-center text-petit text-texte-doux">
          Aucun lien d’application en ligne n’a été renseigné dans cette discussion.
        </p>
      ) : (
        <div className="overflow-hidden rounded-moyen border border-bordure">
          <div className="flex items-center gap-2 border-b border-bordure bg-surface-douce px-3 py-2">
            <span className="min-w-0 flex-1 truncate rounded-petit bg-surface px-2.5 py-0.5 font-mono text-petit text-texte">{adresseVisible(cadre)}</span>
            <span className="text-petit text-texte-doux">Démo externe · isolée</span>
          </div>
          <iframe title="Démo isolée" src={cadre} sandbox="allow-scripts allow-forms" referrerPolicy="no-referrer" className="h-72 w-full max-w-full border-0 bg-surface" />
        </div>
      )}

      {cadre || peutLancer ? (
        <div className="flex flex-wrap items-center gap-2">
          {cadre ? (
            <a href={cadre} target="_blank" rel="noreferrer" className={classesBouton('contour', 'md')}>
              Ouvrir dans un onglet
              <ExternalLink aria-hidden="true" className="size-4" />
              <span className="pour-lecteur"> (nouvel onglet)</span>
            </a>
          ) : null}
          {peutLancer ? (
            <Bouton variante="secondaire" icone={<Play aria-hidden="true" className="size-4" />} onClick={() => setConfirmer(true)}>
              Lancer une copie de test
            </Bouton>
          ) : null}
        </div>
      ) : null}

      {copie ? (
        <div className="flex flex-col gap-3 rounded-moyen border border-bordure bg-surface-douce p-4" role="status">
          <h3 className="m-0 text-corps font-semibold text-titre">Copie de test</h3>
          <Etapes libelle="Étapes de la copie" etapes={etapesCopie(copie)} orientation="horizontale" />
          <p className="m-0 text-petit text-texte">{messageCopie(copie)}</p>
          <div className="flex flex-wrap gap-2">
            {copie.etat === 'en_ligne' && copie.url ? (
              <a href={copie.url} target="_blank" rel="noreferrer" className={classesBouton('primaire', 'md')}>
                Ouvrir la copie
                <ExternalLink aria-hidden="true" className="size-4" />
                <span className="pour-lecteur"> (nouvel onglet)</span>
              </a>
            ) : null}
            {peutArreter ? (
              <Bouton variante="contour" className="text-danger" icone={<Square aria-hidden="true" className="size-4" />} onClick={() => setConfirmerArret(true)}>
                Arrêter la copie
              </Bouton>
            ) : null}
          </div>
        </div>
      ) : null}

      {erreur ? <Alerte ton="danger">{erreur}</Alerte> : null}

      <Modale
        ouverte={confirmerArret}
        onFermer={() => setConfirmerArret(false)}
        taille="petite"
        titre="Arrêter la copie de test ?"
        description="Ses conteneurs s’arrêtent et ses données sont supprimées. Vous pourrez en relancer une autre."
        pied={
          <>
            <Bouton variante="contour" onClick={() => setConfirmerArret(false)}>
              Annuler
            </Bouton>
            <Bouton variante="danger" chargement={arreter.isPending} onClick={() => arreter.mutate()}>
              Arrêter la copie
            </Bouton>
          </>
        }
      >
        {null}
      </Modale>

      <Modale
        ouverte={confirmer}
        onFermer={fermer}
        taille="petite"
        titre="Lancer une copie de test ?"
        description="Souk.dev lance une copie à partir du dépôt ou de l’appli jointe. Rien ne part tout seul : 3 copies par heure."
        pied={
          <>
            <Bouton variante="contour" onClick={fermer}>
              Annuler
            </Bouton>
            <Bouton chargement={lancer.isPending} onClick={() => lancer.mutate()}>
              Lancer la copie
            </Bouton>
          </>
        }
      >
        {null}
      </Modale>
    </Carte>
  )
}
