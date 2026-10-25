import { useEffect, useId, useRef, type KeyboardEvent, type ReactNode } from 'react'
import { createPortal } from 'react-dom'
import { AnimatePresence, motion, useReducedMotion } from 'motion/react'
import { X } from 'lucide-react'
import { t } from '../i18n'
import { BoutonIcone } from './Bouton'
import { cx } from './cx'

const focalisables =
  'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

type ModaleProps = {
  ouverte: boolean
  onFermer: () => void
  titre: ReactNode
  description?: ReactNode
  /** Boutons du bas : l'action principale à droite, l'annulation à gauche. */
  pied?: ReactNode
  taille?: 'petite' | 'moyenne' | 'grande'
  children?: ReactNode
}

const largeurs = { petite: 'tablette:max-w-md', moyenne: 'tablette:max-w-lg', grande: 'tablette:max-w-2xl' }

/**
 * Fenêtre modale : titre annoncé, focus piégé dedans, Échap pour fermer, focus rendu
 * au déclencheur, défilement de la page bloqué. Sur mobile, elle monte du bas.
 */
export function Modale({ ouverte, onFermer, titre, description, pied, taille = 'moyenne', children }: ModaleProps) {
  const id = useId()
  const panneau = useRef<HTMLDivElement>(null)
  const declencheur = useRef<HTMLElement | null>(null)
  const reduit = useReducedMotion()

  useEffect(() => {
    if (!ouverte) return
    declencheur.current = document.activeElement as HTMLElement | null
    const debordement = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    const minuteur = window.setTimeout(() => {
      const premier = panneau.current?.querySelector<HTMLElement>('[data-autofocus]') ?? panneau.current?.querySelector<HTMLElement>(focalisables)
      ;(premier ?? panneau.current)?.focus()
    }, 0)
    return () => {
      window.clearTimeout(minuteur)
      document.body.style.overflow = debordement
      declencheur.current?.focus?.()
    }
  }, [ouverte])

  function clavier(evenement: KeyboardEvent<HTMLDivElement>) {
    if (evenement.key === 'Escape') {
      evenement.stopPropagation()
      onFermer()
      return
    }
    if (evenement.key !== 'Tab' || !panneau.current) return
    const elements = Array.from(panneau.current.querySelectorAll<HTMLElement>(focalisables))
    if (elements.length === 0) return
    const premier = elements[0]
    const dernier = elements[elements.length - 1]
    if (evenement.shiftKey && document.activeElement === premier) {
      evenement.preventDefault()
      dernier.focus()
    } else if (!evenement.shiftKey && document.activeElement === dernier) {
      evenement.preventDefault()
      premier.focus()
    }
  }

  return createPortal(
    <AnimatePresence>
      {ouverte ? (
        <div className="fixed inset-0 z-50 flex items-end justify-center tablette:items-center tablette:p-6" onKeyDown={clavier}>
          <motion.div
            aria-hidden="true"
            className="absolute inset-0 bg-voile"
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.2 }}
            onClick={onFermer}
          />
          <motion.div
            ref={panneau}
            role="dialog"
            aria-modal="true"
            aria-labelledby={`${id}-titre`}
            aria-describedby={description ? `${id}-description` : undefined}
            tabIndex={-1}
            className={cx(
              'relative flex max-h-[92svh] w-full flex-col rounded-t-xl border border-bordure bg-surface shadow-4 focus:outline-none tablette:rounded-grand',
              largeurs[taille],
            )}
            initial={reduit ? { opacity: 0 } : { opacity: 0, y: 24, scale: 0.98 }}
            animate={reduit ? { opacity: 1 } : { opacity: 1, y: 0, scale: 1 }}
            exit={reduit ? { opacity: 0 } : { opacity: 0, y: 16, scale: 0.98 }}
            transition={{ duration: 0.22, ease: [0.2, 0.7, 0.2, 1] }}
          >
            <div className="flex items-start justify-between gap-4 border-b border-bordure px-5 py-4">
              <div className="flex min-w-0 flex-col gap-1">
                <h2 id={`${id}-titre`} className="m-0 text-titre-3">
                  {titre}
                </h2>
                {description ? (
                  <p id={`${id}-description`} className="m-0 text-petit text-texte-doux">
                    {description}
                  </p>
                ) : null}
              </div>
              <BoutonIcone libelle={t.commun.fermer} onClick={onFermer} className="-mt-1 -mr-2">
                <X aria-hidden="true" className="size-5" />
              </BoutonIcone>
            </div>
            {children ? <div className="overflow-y-auto px-5 py-5">{children}</div> : null}
            {pied ? (
              <div className="flex flex-col-reverse gap-2 border-t border-bordure px-5 py-4 tablette:flex-row tablette:justify-end">{pied}</div>
            ) : null}
          </motion.div>
        </div>
      ) : null}
    </AnimatePresence>,
    document.body,
  )
}
