import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Gauge } from 'lucide-react'
import type { components } from '../../shared/types/api'
import { messageAction } from '../echange/api'
import { formules } from '../paiements/formules'
import { modifierAppli } from './api'
import { Alerte, Bouton, Carte, EnTeteCarte, useToast } from '../../ui'

type Taille = components['schemas']['Taille']
type Mesure = components['schemas']['MesureMemoire']

/**
 * Mémoire réellement consommée, mesurée par la plateforme après la publication : elle justifie la
 * formule d'hébergement. L'auteur passe à la formule recommandée en un clic (nouveaux clients).
 */
export function MesureMemoire({ appId, taille, mesure, estAuteur }: { appId: number; taille: Taille; mesure: Mesure; estAuteur: boolean }) {
  const cache = useQueryClient()
  const toast = useToast()
  const recommandee = mesure.taille_recommandee
  const changement = useMutation({
    mutationFn: (nouvelle: Taille) => modifierAppli(appId, { taille: nouvelle }),
    onSuccess: async (_reponse, nouvelle) => {
      await cache.invalidateQueries({ queryKey: ['appli', appId] })
      toast.succes(`Formule ${formules[nouvelle].nom} appliquée aux prochaines copies.`)
    },
    onError: (erreur) => toast.erreur(messageAction(erreur, 'La formule n’a pas pu être changée.')),
  })

  // Une mesure ratée ou en cours n'intéresse que l'auteur.
  if (mesure.statut !== 'terminee' && !estAuteur) return null

  return (
    <Carte as="section" aria-labelledby="titre-memoire" className="flex flex-col gap-4">
      <EnTeteCarte
        titre={
          <span id="titre-memoire" className="flex items-center gap-2">
            <Gauge aria-hidden="true" className="size-5 text-sur-primaire-doux" />
            Mémoire mesurée
          </span>
        }
        description="Souk.dev fait tourner l’appli quelques minutes après sa publication pour connaître la mémoire qu’elle consomme."
      />
      {mesure.statut === 'en_cours' ? (
        <p role="status" className="m-0 text-corps text-texte">
          Mesure en cours : le résultat apparaît ici dans quelques minutes.
        </p>
      ) : null}
      {mesure.statut === 'echec' ? <Alerte ton="alerte">La mesure n’a pas abouti : la formule que vous avez choisie reste appliquée.</Alerte> : null}
      {mesure.statut === 'terminee' && mesure.memoire_max_mo !== null ? (
        <dl className="m-0 grid gap-3 tablette:grid-cols-2">
          <div className="flex flex-col gap-0.5 rounded-moyen bg-surface-douce p-3">
            <dt className="text-petit text-texte-doux">Pic de mémoire</dt>
            <dd className="chiffres m-0 text-titre-3 text-titre">{mesure.memoire_max_mo.toLocaleString('fr-FR')} Mo</dd>
          </div>
          <div className="flex flex-col gap-0.5 rounded-moyen bg-surface-douce p-3">
            <dt className="text-petit text-texte-doux">Formule recommandée</dt>
            <dd className="m-0 text-titre-3 text-titre">{recommandee ? `${formules[recommandee].nom} (${formules[recommandee].memoire})` : '—'}</dd>
          </div>
        </dl>
      ) : null}
      {estAuteur && mesure.statut === 'terminee' && recommandee && recommandee !== taille ? (
        <Alerte ton="info" titre={`Votre appli est en formule ${formules[taille].nom}`}>
          <p className="m-0">
            La mesure conseille la formule {formules[recommandee].nom}. Le changement vaut pour les prochaines copies ; les clients déjà installés gardent leur formule et leur prix.
          </p>
          <Bouton variante="contour" taille="sm" className="mt-3" chargement={changement.isPending} onClick={() => changement.mutate(recommandee)}>
            Passer en formule {formules[recommandee].nom}
          </Bouton>
        </Alerte>
      ) : null}
    </Carte>
  )
}
