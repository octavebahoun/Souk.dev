import type { ReactNode } from 'react'

type EmptyStateProps = {
  message: string
  children?: ReactNode
}

export function EmptyState({ message, children }: EmptyStateProps) {
  return (
    <div className="flex flex-col items-center gap-3 rounded-carte border border-dashed border-[#C7D3EA] bg-canvas px-5 py-10 text-center text-subtle">
      <p className="m-0">{message}</p>
      {children}
    </div>
  )
}
