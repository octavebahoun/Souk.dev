import { useId } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTitrePage } from '../../app/titre'
import { Badge, Carte, EnTetePage, EtatErreur, Interrupteur, SqueletteLignes, cx, useToast } from '../../ui'
import { messageErreur } from '../paiements/api'
import { lirePreferences, modifierPreference, type PreferencesNotifications, type ReglageNotification } from './api'
import { presentationNotification } from './presentation'

/** Réglages par type : dans Souk.dev et par e-mail, appliqués dès le clic. */
export function ReglagesNotifications() {
  useTitrePage('Réglages des notifications')
  const cache = useQueryClient()
  const toast = useToast()
  const reglages = useQuery({ queryKey: ['preferences-notifications'], queryFn: lirePreferences })

  const modification = useMutation({
    scope: { id: 'preferences-notifications' },
    mutationFn: modifierPreference,
    onMutate: async (reglage) => {
      await cache.cancelQueries({ queryKey: ['preferences-notifications'] })
      const avant = cache.getQueryData<PreferencesNotifications>(['preferences-notifications'])
      cache.setQueryData<PreferencesNotifications>(['preferences-notifications'], (actuels) =>
        actuels ? { types: actuels.types.map((type) => (type.type === reglage.type ? { ...type, ...reglage } : type)) } : actuels,
      )
      return { avant }
    },
    onError: (erreur, _reglage, contexte) => {
      cache.setQueryData(['preferences-notifications'], contexte?.avant)
      toast.erreur(messageErreur(erreur, 'Le réglage n’a pas pu être enregistré. Réessayez dans un instant.'))
    },
    onSuccess: (reponse) => cache.setQueryData(['preferences-notifications'], reponse),
  })

  return (
    <div className="flex flex-col gap-8">
      <EnTetePage
        titre="Réglages des notifications"
        description="Choisissez, type par type, ce qui arrive dans Souk.dev et ce qui part par e-mail."
        filAriane={[{ libelle: 'Notifications', vers: '/notifications' }, { libelle: 'Réglages' }]}
      />
      {reglages.isPending ? <SqueletteLignes lignes={6} /> : null}
      {reglages.isError ? <EtatErreur onReessayer={() => void reglages.refetch()} /> : null}
      {reglages.data ? (
        <Carte className="flex flex-col p-0">
          {reglages.data.types.map((reglage, index) => (
            <LigneReglage
              key={reglage.type}
              reglage={reglage}
              premiere={index === 0}
              onChange={(canal, actif) => modification.mutate({ type: reglage.type, application: reglage.application, email: reglage.email, [canal]: actif })}
            />
          ))}
        </Carte>
      ) : null}
    </div>
  )
}

function LigneReglage({ reglage, premiere, onChange }: { reglage: ReglageNotification; premiere: boolean; onChange: (canal: 'application' | 'email', actif: boolean) => void }) {
  const id = useId()
  const { icone: Icone, classe } = presentationNotification[reglage.type]
  return (
    <section aria-labelledby={`${id}-titre`} className={cx('flex flex-col gap-3 p-4 tablette:flex-row tablette:items-start tablette:gap-6 tablette:p-5', !premiere && 'border-t border-bordure')}>
      <div className="flex min-w-0 flex-1 gap-3">
        <span aria-hidden="true" className={cx('grid size-10 shrink-0 place-items-center rounded-moyen', classe)}>
          <Icone className="size-5" />
        </span>
        <div className="flex min-w-0 flex-col gap-0.5">
          <h2 id={`${id}-titre`} className="m-0 text-corps font-semibold text-titre">
            {reglage.libelle}
          </h2>
          <p className="m-0 text-petit text-texte-doux">{reglage.description}</p>
        </div>
      </div>
      <div role="group" aria-labelledby={`${id}-titre`} className="flex flex-col tablette:w-64 tablette:shrink-0">
        <Interrupteur libelle="Dans Souk.dev" actif={reglage.application} onChange={(actif) => onChange('application', actif)} />
        {reglage.email_obligatoire ? (
          // Pas d'interrupteur grisé : l'e-mail part toujours, on le dit en clair.
          <div className="flex min-h-11 flex-col justify-center gap-1 py-1.5">
            <div className="flex items-center justify-between gap-4">
              <span className="text-corps text-titre">Par e-mail</span>
              <Badge ton="info">Toujours envoyé</Badge>
            </div>
            <p className="m-0 text-petit text-texte-doux">Il vous prévient avant la suppression des données.</p>
          </div>
        ) : (
          <Interrupteur libelle="Par e-mail" actif={reglage.email} onChange={(actif) => onChange('email', actif)} />
        )}
      </div>
    </section>
  )
}
