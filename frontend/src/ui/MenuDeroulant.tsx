import { useEffect, useId, useRef, useState, type KeyboardEvent, type ReactNode } from 'react'
import { Link } from 'react-router'
import { AnimatePresence, m, useReducedMotion } from 'motion/react'
import { cx } from './cx'

export type ElementMenu =
  | { type: 'lien'; libelle: ReactNode; vers: string; icone?: ReactNode }
  | { type: 'action'; libelle: ReactNode; onClick: () => void; icone?: ReactNode; danger?: boolean }
  | { type: 'separateur' }
  | { type: 'contenu'; contenu: ReactNode }

type MenuDeroulantProps = {
  /** Contenu du bouton déclencheur (avatar, icône…). */
  declencheur: ReactNode
  libelle: string
  elements: ElementMenu[]
  alignement?: 'gauche' | 'droite'
  classeDeclencheur?: string
}

/** Menu au clavier : flèches haut et bas, Début, Fin, Échap ; fermé au clic dehors. */
export function MenuDeroulant({ declencheur, libelle, elements, alignement = 'droite', classeDeclencheur }: MenuDeroulantProps) {
  const [ouvert, setOuvert] = useState(false)
  const id = useId()
  const bloc = useRef<HTMLDivElement>(null)
  const bouton = useRef<HTMLButtonElement>(null)
  const liste = useRef<HTMLDivElement>(null)
  const reduit = useReducedMotion()

  useEffect(() => {
    if (!ouvert) return
    function dehors(evenement: MouseEvent) {
      if (!bloc.current?.contains(evenement.target as Node)) setOuvert(false)
    }
    document.addEventListener('mousedown', dehors)
    const minuteur = window.setTimeout(() => liste.current?.querySelector<HTMLElement>('[role="menuitem"]')?.focus(), 0)
    return () => {
      document.removeEventListener('mousedown', dehors)
      window.clearTimeout(minuteur)
    }
  }, [ouvert])

  function fermer(rendreFocus = true) {
    setOuvert(false)
    if (rendreFocus) bouton.current?.focus()
  }

  function clavier(evenement: KeyboardEvent<HTMLDivElement>) {
    const items = Array.from(liste.current?.querySelectorAll<HTMLElement>('[role="menuitem"]') ?? [])
    const position = items.indexOf(document.activeElement as HTMLElement)
    const cibles: Record<string, number> = {
      ArrowDown: position === items.length - 1 ? 0 : position + 1,
      ArrowUp: position <= 0 ? items.length - 1 : position - 1,
      Home: 0,
      End: items.length - 1,
    }
    if (evenement.key === 'Escape') {
      evenement.preventDefault()
      fermer()
    } else if (evenement.key === 'Tab') {
      setOuvert(false)
    } else if (cibles[evenement.key] !== undefined) {
      evenement.preventDefault()
      items[cibles[evenement.key]]?.focus()
    }
  }

  const classeItem = (danger?: boolean) =>
    cx(
      'flex min-h-11 w-full items-center gap-2.5 rounded-petit px-3 text-left text-corps no-underline transition-colors duration-150 tablette:min-h-9',
      'focus-visible:bg-surface-douce focus-visible:outline-none',
      danger ? 'text-danger hover:bg-fond-danger' : 'text-titre hover:bg-surface-douce',
    )

  return (
    <div ref={bloc} className="relative">
      <button
        ref={bouton}
        type="button"
        aria-label={libelle}
        aria-haspopup="menu"
        aria-expanded={ouvert}
        aria-controls={ouvert ? `${id}-menu` : undefined}
        onClick={() => setOuvert((valeur) => !valeur)}
        onKeyDown={(evenement) => {
          if (evenement.key === 'ArrowDown' && !ouvert) {
            evenement.preventDefault()
            setOuvert(true)
          }
        }}
        className={classeDeclencheur}
      >
        {/* Le nom du bouton vient d'aria-label : le contenu visible (initiales, flèche) est décoratif. */}
        <span aria-hidden="true" className="contents">
          {declencheur}
        </span>
      </button>
      <AnimatePresence>
        {ouvert ? (
          <m.div
            ref={liste}
            id={`${id}-menu`}
            role="menu"
            aria-label={libelle}
            onKeyDown={clavier}
            initial={reduit ? { opacity: 0 } : { opacity: 0, y: -4, scale: 0.98 }}
            animate={reduit ? { opacity: 1 } : { opacity: 1, y: 0, scale: 1 }}
            exit={reduit ? { opacity: 0 } : { opacity: 0, y: -4, scale: 0.98 }}
            transition={{ duration: 0.15, ease: [0.2, 0.7, 0.2, 1] }}
            className={cx(
              'absolute top-[calc(100%+8px)] z-40 flex w-64 origin-top flex-col gap-0.5 rounded-grand border border-bordure bg-surface p-1.5 shadow-3',
              alignement === 'droite' ? 'right-0' : 'left-0',
            )}
          >
            {elements.map((element, index) => {
              if (element.type === 'separateur') return <div key={index} role="separator" className="mx-1.5 my-1 h-px bg-bordure" />
              if (element.type === 'contenu') return <div key={index}>{element.contenu}</div>
              if (element.type === 'lien') {
                return (
                  <Link key={index} role="menuitem" tabIndex={-1} to={element.vers} onClick={() => fermer(false)} className={classeItem()}>
                    {element.icone}
                    {element.libelle}
                  </Link>
                )
              }
              return (
                <button
                  key={index}
                  type="button"
                  role="menuitem"
                  tabIndex={-1}
                  onClick={() => {
                    fermer(false)
                    element.onClick()
                  }}
                  className={classeItem(element.danger)}
                >
                  {element.icone}
                  {element.libelle}
                </button>
              )
            })}
          </m.div>
        ) : null}
      </AnimatePresence>
    </div>
  )
}
