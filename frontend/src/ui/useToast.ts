import { createContext, useContext, type ReactNode } from 'react'

export type Toast = { id: number; ton: 'succes' | 'erreur' | 'info'; message: ReactNode; action?: { libelle: string; onClick: () => void } }

export type ApiToast = {
  succes: (message: ReactNode, action?: Toast['action']) => void
  erreur: (message: ReactNode, action?: Toast['action']) => void
  info: (message: ReactNode, action?: Toast['action']) => void
}

export const ContexteToasts = createContext<ApiToast | null>(null)

export function useToast(): ApiToast {
  const api = useContext(ContexteToasts)
  if (!api) throw new Error('useToast doit être utilisé sous <FournisseurToasts>.')
  return api
}
