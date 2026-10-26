import { useState } from 'react'
import { QueryClientProvider } from '@tanstack/react-query'
import { LazyMotion } from 'motion/react'
import { Outlet, ScrollRestoration } from 'react-router'
import { FournisseurToasts } from '../ui'
import { chargerAnimations } from '../ui/animations'
import { creerQueryClient } from './queryClient'
import { Apparitions } from './Apparitions'

export function Fournisseurs() {
  const [queryClient] = useState(creerQueryClient)
  return (
    <QueryClientProvider client={queryClient}>
      <LazyMotion features={chargerAnimations} strict>
        <FournisseurToasts>
          <Apparitions />
          <ScrollRestoration />
          <Outlet />
        </FournisseurToasts>
      </LazyMotion>
    </QueryClientProvider>
  )
}
