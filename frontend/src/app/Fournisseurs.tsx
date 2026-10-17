import { useState } from 'react'
import { QueryClientProvider } from '@tanstack/react-query'
import { Outlet } from 'react-router'
import { creerQueryClient } from './queryClient'

export function Fournisseurs() {
  const [queryClient] = useState(creerQueryClient)
  return (
    <QueryClientProvider client={queryClient}>
      <Outlet />
    </QueryClientProvider>
  )
}
