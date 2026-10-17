import type { ReactNode } from 'react'

const tons = {
  bug: 'bg-danger-bg text-danger',
  question: 'bg-[#DBEAFE] text-[#1E40AF]',
  tuto: 'bg-success-bg text-success',
  projet: 'bg-[#EDE9FE] text-[#5B21B6]',
  entraide: 'bg-[#EEF2F9] text-[#334155]',
  resolue: 'bg-success-bg text-success',
  nouveau: 'bg-accent text-canvas',
  ia: 'border border-[#DDD6FE] bg-[#F5F3FF] text-[#6D28D9]',
  facile: 'bg-success-bg text-success',
  moyen: 'bg-warning-bg text-warning',
  difficile: 'bg-[#FFE4E6] text-[#BE123C]',
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
