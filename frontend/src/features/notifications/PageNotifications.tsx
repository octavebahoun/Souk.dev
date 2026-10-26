import { useState } from 'react'
import { useInfiniteQuery, useQueryClient } from '@tanstack/react-query'
import { Settings } from 'lucide-react'
import { useTitrePage } from '../../app/titre'
import { Bouton, Carte, EnTetePage, EtatErreur, EtatVide, LienBouton, Onglets, SqueletteLignes } from '../../ui'
import { listerNotifications, toutLire, type PageNotifications } from './api'
import { LigneNotification } from './LigneNotification'

type Filtre = 'toutes' | 'non_lues'

/** Toutes les notifications du compte, les non lues d'abord marquées ; tenues à jour par la cloche. */
export function PageNotifications() {
  useTitrePage('Notifications')
  const cache = useQueryClient()
  const [filtre, setFiltre] = useState<Filtre>('toutes')
  const liste = useInfiniteQuery({
    queryKey: ['notifications', 'liste', filtre],
    queryFn: ({ pageParam }) => listerNotifications({ filtre, page: pageParam }),
    initialPageParam: 1,
    getNextPageParam: (derniere: PageNotifications) => (derniere.meta.current_page < derniere.meta.last_page ? derniere.meta.current_page + 1 : undefined),
  })
  const notifications = liste.data?.pages.flatMap((page) => page.data) ?? []
  const nonLues = liste.data?.pages[0]?.non_lues ?? 0

  async function toutMarquer() {
    await toutLire().catch(() => undefined)
    await cache.invalidateQueries({ queryKey: ['notifications'] })
  }

  return (
    <div className="flex flex-col gap-8">
      <EnTetePage
        titre="Notifications"
        description="Correctifs, messages de mission, factures, suspensions et reversements. Chaque type se règle dans Souk.dev et par e-mail."
        actions={
          <LienBouton to="/notifications/reglages" variante="contour" icone={<Settings aria-hidden="true" className="size-4" />}>
            Réglages
          </LienBouton>
        }
      />

      <Onglets
        libelle="Filtrer les notifications"
        valeur={filtre}
        onChange={setFiltre}
        onglets={[
          { valeur: 'toutes', libelle: 'Toutes' },
          { valeur: 'non_lues', libelle: 'Non lues', compteur: nonLues || undefined },
        ]}
      >
        <Carte className="flex flex-col gap-2 p-2 tablette:p-3">
          {nonLues > 0 ? (
            <div className="flex flex-wrap items-center justify-between gap-2 px-3 pt-1">
              <p className="m-0 text-petit text-texte-doux">{nonLues > 1 ? `${nonLues} notifications non lues` : '1 notification non lue'}</p>
              <Bouton variante="fantome" taille="sm" className="ml-auto" onClick={() => void toutMarquer()}>
                Tout marquer comme lu
              </Bouton>
            </div>
          ) : null}
          {liste.isPending ? <SqueletteLignes lignes={5} className="p-3" /> : null}
          {liste.isError ? <EtatErreur onReessayer={() => void liste.refetch()} /> : null}
          {liste.isSuccess && notifications.length === 0 ? (
            <EtatVide
              illustration="vide-notifications"
              titre={filtre === 'non_lues' ? 'Tout est lu' : 'Pas encore de notification'}
              description="Un correctif proposé, un message de mission, une facture ou un reversement apparaîtra ici, et par e-mail si vous le souhaitez."
            />
          ) : null}
          {notifications.length > 0 ? (
            <ul className="m-0 flex list-none flex-col gap-0.5 p-0">
              {notifications.map((notification) => (
                <li key={notification.id}>
                  <LigneNotification notification={notification} />
                </li>
              ))}
            </ul>
          ) : null}
          {liste.hasNextPage ? (
            <Bouton variante="contour" className="self-center" chargement={liste.isFetchingNextPage} onClick={() => void liste.fetchNextPage()}>
              Voir plus
            </Bouton>
          ) : null}
        </Carte>
      </Onglets>
    </div>
  )
}
