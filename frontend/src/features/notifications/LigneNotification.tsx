import { Link } from 'react-router'
import { useQueryClient } from '@tanstack/react-query'
import { cx, formaterIlYa } from '../../ui'
import { lireNotification, type Notification } from './api'
import { presentationNotification } from './presentation'

/** Une notification : l'ouvrir la marque comme lue et mène à l'écran concerné. */
export function LigneNotification({ notification, compacte = false, onOuvrir }: { notification: Notification; compacte?: boolean; onOuvrir?: () => void }) {
  const cache = useQueryClient()
  const { icone: Icone, classe } = presentationNotification[notification.type]

  function ouvrir() {
    if (!notification.lue) {
      void lireNotification(notification.id)
        .then(() => cache.invalidateQueries({ queryKey: ['notifications'] }))
        .catch(() => undefined)
    }
    onOuvrir?.()
  }

  const contenu = (
    <>
      <span aria-hidden="true" className={cx('grid size-9 shrink-0 place-items-center rounded-moyen', classe)}>
        <Icone className="size-4" />
      </span>
      <span className="flex min-w-0 flex-1 flex-col gap-0.5">
        <span className={cx('text-corps text-titre', notification.lue ? 'font-medium' : 'font-semibold')}>
          {notification.titre}
          {notification.lue ? null : <span className="pour-lecteur"> (non lue)</span>}
        </span>
        <span className={cx('text-petit text-texte', compacte && 'line-clamp-2')}>{notification.texte}</span>
        <time dateTime={notification.cree_le} className="text-micro text-texte-doux">
          {formaterIlYa(notification.cree_le)}
        </time>
      </span>
      {notification.lue ? null : <span aria-hidden="true" className="mt-2 size-2 shrink-0 rounded-pastille bg-primaire" />}
    </>
  )
  const classes = cx(
    'flex w-full gap-3 rounded-moyen p-3 text-left no-underline transition-colors duration-150 hover:bg-surface-douce',
    !notification.lue && 'bg-surface-accent/40',
  )

  return notification.lien ? (
    <Link to={notification.lien} onClick={ouvrir} className={classes}>
      {contenu}
    </Link>
  ) : (
    <button type="button" onClick={ouvrir} className={classes}>
      {contenu}
    </button>
  )
}
