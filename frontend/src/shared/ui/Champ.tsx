import type { ReactNode } from 'react'

type ChampProps = {
  id: string
  label: string
  erreur?: string
  facultatif?: boolean
  children: ReactNode
}

export const classeChamp =
  'min-h-cible w-full rounded-controle border border-line bg-canvas px-3 text-ink outline-none'

export function Champ({ id, label, erreur, facultatif, children }: ChampProps) {
  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="text-sm font-semibold text-ink">
        {label}
        {facultatif ? <span className="font-medium text-muted"> (facultatif)</span> : null}
      </label>
      {children}
      {erreur ? (
        <p id={`${id}-erreur`} className="m-0 text-[13.5px] text-danger">
          {erreur}
        </p>
      ) : null}
    </div>
  )
}
