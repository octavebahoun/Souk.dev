import { useEffect, useId, useRef, type ReactNode } from 'react'

type ModalProps = {
  titre: string
  onFermer: () => void
  children: ReactNode
}

export function Modal({ titre, onFermer, children }: ModalProps) {
  const titreId = useId()
  const boite = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const avant = document.activeElement
    const racine = boite.current
    const champ = racine?.querySelector<HTMLElement>('input, textarea, select')
    ;(champ ?? racine?.querySelector<HTMLElement>('button'))?.focus()

    function focusables(): HTMLElement[] {
      if (!racine) return []
      return [...racine.querySelectorAll<HTMLElement>('button, input, select, textarea, a[href]')].filter(
        (element) => !element.hasAttribute('disabled'),
      )
    }

    function surTouche(evenement: KeyboardEvent) {
      if (evenement.key === 'Escape') {
        evenement.preventDefault()
        onFermer()
        return
      }
      if (evenement.key !== 'Tab') return
      const elements = focusables()
      if (elements.length === 0) return
      const premier = elements[0]
      const dernier = elements[elements.length - 1]
      const actif = document.activeElement
      if (evenement.shiftKey && (actif === premier || !racine?.contains(actif))) {
        evenement.preventDefault()
        dernier.focus()
      } else if (!evenement.shiftKey && actif === dernier) {
        evenement.preventDefault()
        premier.focus()
      }
    }

    document.addEventListener('keydown', surTouche)
    return () => {
      document.removeEventListener('keydown', surTouche)
      if (avant instanceof HTMLElement) avant.focus()
    }
  }, [onFermer])

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 p-4"
      onMouseDown={onFermer}
    >
      <div
        ref={boite}
        role="dialog"
        aria-modal="true"
        aria-labelledby={titreId}
        className="flex max-h-[calc(100vh-2rem)] w-full max-w-[520px] flex-col gap-4 overflow-auto rounded-2xl bg-canvas p-6 shadow-[0_20px_50px_rgba(15,23,42,0.25)]"
        onMouseDown={(evenement) => evenement.stopPropagation()}
      >
        <div className="flex items-center justify-between gap-3">
          <h2 id={titreId} className="m-0 text-xl font-bold text-ink">
            {titre}
          </h2>
          <button
            type="button"
            aria-label="Fermer"
            onClick={onFermer}
            className="grid min-h-cible min-w-cible place-items-center rounded-controle bg-page text-ink"
          >
            <span aria-hidden="true">×</span>
          </button>
        </div>
        {children}
      </div>
    </div>
  )
}
