import type { ReactNode } from 'react'

const tons = {
  bug: 'bg-danger-bg text-danger',
  question: 'bg-question-bg text-question',
  tuto: 'bg-success-bg text-success',
  projet: 'bg-ia-bg text-projet',
  entraide: 'bg-chip text-nav-texte',
  resolue: 'bg-success-bg text-success',
  nouveau: 'bg-accent text-canvas',
  ia: 'border border-ia-line bg-ia-bg text-ia',
  facile: 'bg-success-bg text-success',
  moyen: 'bg-moyen-bg text-warning',
  difficile: 'bg-difficile-bg text-difficile',
} as const

type BadgeProps = {
  ton: keyof typeof tons
  children: ReactNode
}

export function Badge({ ton, children }: BadgeProps) {
  return (
    <span className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[12.5px] font-semibold ${tons[ton]}`}>
      {children}
    </span>
  )
}
