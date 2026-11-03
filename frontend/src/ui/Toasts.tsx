import { useCallback, useMemo, useRef, useState, type ReactNode } from 'react'
import { AnimatePresence, m, useReducedMotion } from 'motion/react'
import { CircleAlert, CircleCheck, Info, X } from 'lucide-react'
import { t } from '../i18n'
import { cx } from './cx'
import { ContexteToasts, type ApiToast, type Toast } from './useToast'




const DUREE = 5000

const styles: Record<Toast['ton'], { icone: ReactNode; classe: string }> = {
  succes: { icone: <CircleCheck aria-hidden="true" className="size-5 text-succes" />, classe: '' },
  erreur: { icone: <CircleAlert aria-hidden="true" className="size-5 text-danger" />, classe: '' },
  info: { icone: <Info aria-hidden="true" className="size-5 text-info" />, classe: '' },
}

/**
 * Résultat d'une action, annoncé aux lecteurs d'écran. Jamais pour une erreur bloquante :
 * celle-ci reste affichée dans l'écran, à côté de ce qu'il faut corriger.
 */
export function FournisseurToasts({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([])
  const compteur = useRef(0)
  const minuteurs = useRef(new Map<number, number>())
  const reduit = useReducedMotion()

  const retirer = useCallback((id: number) => {
    window.clearTimeout(minuteurs.current.get(id))
    minuteurs.current.delete(id)
    setToasts((liste) => liste.filter((toast) => toast.id !== id))
  }, [])

  const programmer = useCallback(
    (id: number) => {
      minuteurs.current.set(id, window.setTimeout(() => retirer(id), DUREE))
    },
    [retirer],
  )

  const ajouter = useCallback(
    (ton: Toast['ton'], message: ReactNode, action?: Toast['action']) => {
      compteur.current += 1
      const id = compteur.current
      setToasts((liste) => [...liste.slice(-2), { id, ton, message, action }])
      programmer(id)
    },
    [programmer],
  )

  const api = useMemo<ApiToast>(
    () => ({
      succes: (message, action) => ajouter('succes', message, action),
      erreur: (message, action) => ajouter('erreur', message, action),
      info: (message, action) => ajouter('info', message, action),
    }),
    [ajouter],
  )

  return (
    <ContexteToasts.Provider value={api}>
      {children}
      <div
        role="status"
        aria-live="polite"
        className="pointer-events-none fixed inset-x-4 bottom-[calc(var(--spacing-barre-onglets)+0.75rem+env(safe-area-inset-bottom))] z-[60] flex flex-col items-center gap-2 tablette:inset-x-auto tablette:right-6 tablette:bottom-6 tablette:items-end"
      >
        <AnimatePresence initial={false}>
          {toasts.map((toast) => (
            <m.div
              key={toast.id}
              layout={!reduit}
              initial={reduit ? { opacity: 0 } : { opacity: 0, y: 16, scale: 0.98 }}
              animate={reduit ? { opacity: 1 } : { opacity: 1, y: 0, scale: 1 }}
              exit={reduit ? { opacity: 0 } : { opacity: 0, x: 24 }}
              transition={{ duration: 0.2, ease: [0.2, 0.7, 0.2, 1] }}
              onMouseEnter={() => window.clearTimeout(minuteurs.current.get(toast.id))}
              onMouseLeave={() => programmer(toast.id)}
              className={cx(
                'pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-grand border border-bordure bg-surface px-4 py-3 text-corps text-titre shadow-3',
                styles[toast.ton].classe,
              )}
            >
              <span className="mt-0.5">{styles[toast.ton].icone}</span>
              <div className="flex min-w-0 flex-1 flex-col gap-1">
                <div>{toast.message}</div>
                {toast.action ? (
                  <button
                    type="button"
                    onClick={() => {
                      toast.action?.onClick()
                      retirer(toast.id)
                    }}
                    className="self-start font-medium text-lien hover:underline"
                  >
                    {toast.action.libelle}
                  </button>
                ) : null}
              </div>
              <button
                type="button"
                aria-label={t.commun.fermer}
                onClick={() => retirer(toast.id)}
                className="-m-2 grid size-11 shrink-0 place-items-center rounded-petit text-texte-doux hover:bg-surface-douce hover:text-titre tablette:size-8"
              >
                <X aria-hidden="true" className="size-4" />
              </button>
            </m.div>
          ))}
        </AnimatePresence>
      </div>
    </ContexteToasts.Provider>
  )
}

