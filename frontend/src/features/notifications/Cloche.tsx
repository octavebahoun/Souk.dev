import { useEffect, useId, useRef, useState } from 'react'
import { Link } from 'react-router'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Bell } from 'lucide-react'
import { useCompte } from '../../app/compte'
import { useCanalPrive } from '../../app/tempsReelPrive'
import { Bouton, SqueletteLignes, cx } from '../../ui'
import { listerNotifications, toutLire, type Notification } from './api'
import { LigneNotification } from './LigneNotification'

/**
 * Cloche de l'en-tête : le nombre de non lues, les dernières notifications, tenues à jour en
 * direct (canal privé du compte) et relues toutes les deux minutes sans Reverb.
 */
export function Cloche({ surFondSombre = false }: { surFondSombre?: boolean }) {
  const compte = useCompte()
  const cache = useQueryClient()
  const id = useId()
  const [ouvert, setOuvert] = useState(false)
  const [annonce, setAnnonce] = useState('')
  const bloc = useRef<HTMLDivElement>(null)
  const bouton = useRef<HTMLButtonElement>(null)
  const panneau = useRef<HTMLDivElement>(null)

  const apercu = useQuery({
    queryKey: ['notifications', 'apercu'],
    queryFn: () => listerNotifications({ per_page: 6 }),
    enabled: Boolean(compte.data),
    refetchInterval: 120_000,
  })

  useCanalPrive<Notification>(
    compte.data ? `utilisateur.${compte.data.id}` : null,
    'notification.creee',
    (notification) => {
      setAnnonce(`Nouvelle notification : ${notification.titre}`)
      void cache.invalidateQueries({ queryKey: ['notifications'] })
    },
    () => void cache.invalidateQueries({ queryKey: ['notifications'] }),
  )

  useEffect(() => {
    if (!ouvert) return
    function dehors(evenement: MouseEvent) {
      if (!bloc.current?.contains(evenement.target as Node)) setOuvert(false)
    }
    function echap(evenement: globalThis.KeyboardEvent) {
      if (evenement.key !== 'Escape') return
      evenement.preventDefault()
      setOuvert(false)
      bouton.current?.focus()
    }
    document.addEventListener('mousedown', dehors)
    document.addEventListener('keydown', echap)
    const minuteur = window.setTimeout(() => panneau.current?.querySelector<HTMLElement>('a, button')?.focus(), 0)
    return () => {
      document.removeEventListener('mousedown', dehors)
      document.removeEventListener('keydown', echap)
      window.clearTimeout(minuteur)
    }
  }, [ouvert])

  async function toutMarquer() {
    await toutLire().catch(() => undefined)
    await cache.invalidateQueries({ queryKey: ['notifications'] })
  }

  if (!compte.data) return null
  const nonLues = apercu.data?.non_lues ?? 0
  const liste = apercu.data?.data ?? []

  return (
    <div
      ref={bloc}
      // Le focus quitte la cloche (Tab au-delà du panneau) : le panneau se ferme, il ne cache rien.
      onBlur={(evenement) => {
        if (ouvert && evenement.relatedTarget instanceof Node && !evenement.currentTarget.contains(evenement.relatedTarget)) setOuvert(false)
      }}
      className="relative"
    >
      <button
        ref={bouton}
        type="button"
        aria-expanded={ouvert}
        aria-controls={ouvert ? `${id}-panneau` : undefined}
        aria-label={nonLues > 0 ? `Notifications, ${nonLues} non ${nonLues > 1 ? 'lues' : 'lue'}` : 'Notifications'}
        onClick={() => setOuvert((valeur) => !valeur)}
        className={cx(
          'relative grid size-11 place-items-center rounded-pastille transition-colors duration-150 tablette:size-10',
          surFondSombre ? 'text-sur-couleur/80 hover:bg-sur-couleur/10 hover:text-sur-couleur' : 'text-texte-doux hover:bg-surface-douce hover:text-titre',
        )}
      >
        <Bell aria-hidden="true" className="size-5" />
        {nonLues > 0 ? (
          <span aria-hidden="true" className="absolute top-1 right-0.5 grid h-[18px] min-w-[18px] place-items-center rounded-pastille bg-danger-solide px-1 text-[11px] leading-none font-semibold text-sur-couleur">
            {nonLues > 9 ? '9+' : nonLues}
          </span>
        ) : null}
      </button>
      <p aria-live="polite" className="pour-lecteur">
        {annonce}
      </p>
      {ouvert ? (
        <div
          ref={panneau}
          id={`${id}-panneau`}
          role="dialog"
          aria-labelledby={`${id}-titre`}
          className="fixed inset-x-2 top-[68px] z-40 flex max-h-[calc(100dvh-84px)] flex-col overflow-hidden rounded-grand border border-bordure bg-surface text-texte shadow-3 tablette:absolute tablette:inset-x-auto tablette:top-[calc(100%+8px)] tablette:right-0 tablette:w-[400px]"
        >
          <div className="flex min-h-14 items-center justify-between gap-2 border-b border-bordure px-4">
            <h2 id={`${id}-titre`} className="m-0 text-corps-l font-semibold text-titre">
              Notifications
            </h2>
            {nonLues > 0 ? (
              <Bouton variante="fantome" taille="sm" onClick={() => void toutMarquer()}>
                Tout marquer comme lu
              </Bouton>
            ) : null}
          </div>
          <div className="min-h-0 flex-1 overflow-y-auto p-1.5">
            {apercu.isPending ? <SqueletteLignes lignes={4} className="p-3" /> : null}
            {apercu.isError ? <p className="m-0 px-3 py-6 text-center text-petit text-texte-doux">Les notifications n’ont pas pu être chargées. Réessayez dans un instant.</p> : null}
            {apercu.isSuccess && liste.length === 0 ? (
              <p className="m-0 px-4 py-8 text-center text-petit text-texte-doux">Rien de nouveau. Correctifs, messages de mission, factures et reversements arrivent ici.</p>
            ) : null}
            {liste.length > 0 ? (
              <ul className="m-0 flex list-none flex-col gap-0.5 p-0">
                {liste.map((notification) => (
                  <li key={notification.id}>
                    <LigneNotification notification={notification} compacte onOuvrir={() => setOuvert(false)} />
                  </li>
                ))}
              </ul>
            ) : null}
          </div>
          <div className="flex min-h-12 items-center justify-between gap-2 border-t border-bordure px-4">
            <Link to="/notifications" onClick={() => setOuvert(false)} className="inline-flex min-h-11 items-center text-petit font-medium">
              Tout voir
            </Link>
            <Link to="/notifications/reglages" onClick={() => setOuvert(false)} className="inline-flex min-h-11 items-center text-petit font-medium text-texte-doux">
              Réglages
            </Link>
          </div>
        </div>
      ) : null}
    </div>
  )
}
