import { useState } from 'react'
import { QueryClientProvider } from '@tanstack/react-query'
import { Outlet, ScrollRestoration } from 'react-router'
import { FournisseurToasts } from '../ui'
import { creerQueryClient } from './queryClient'
import { Apparitions } from './Apparitions'

export function Fournisseurs() {
  const [queryClient] = useState(creerQueryClient)
  return (
    <QueryClientProvider client={queryClient}>
      <FournisseurToasts>
        <Apparitions />
        <ScrollRestoration />
        <Outlet />
      </FournisseurToasts>
    </QueryClientProvider>
  )
}
