import type { Categorie } from './api'
import { teintes } from './teintes'

export function Fenetre({ nom, categorie }: { nom: string; categorie: Categorie }) {
  const teinte = teintes[categorie]
  return (
    <span className="flex aspect-video flex-col overflow-hidden rounded-t-[18px] p-3.5 pb-0" style={{ background: teinte.fond }}>
      <span className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-t-[9px] bg-canvas shadow-[0_2px_8px_rgba(15,23,42,0.08)]">
        <span className="flex h-5 items-center gap-1 px-2 text-[9.5px] text-white/90" style={{ background: teinte.texte }}>
          <i className="size-1.5 rounded-full bg-white/60" />
          <i className="size-1.5 rounded-full bg-white/60" />
          <i className="size-1.5 rounded-full bg-white/60" />
          <span className="ml-1 truncate font-mono">{nom}</span>
        </span>
        <span className="flex flex-1 flex-col gap-1.5 p-2">
          <span className="h-5 rounded" style={{ background: teinte.fond }} />
          <span className="grid grid-cols-3 gap-1">
            <span className="h-6 rounded" style={{ background: teinte.fond }} />
            <span className="h-6 rounded" style={{ background: teinte.fond }} />
            <span className="h-6 rounded" style={{ background: teinte.fond }} />
          </span>
          <span className="h-1.5 w-[72%] rounded bg-[#E2E8F0]" />
          <span className="mt-auto self-end rounded-full px-2 text-[9px] font-bold text-white" style={{ background: teinte.texte }}>
            Déployer
          </span>
        </span>
      </span>
    </span>
  )
}
