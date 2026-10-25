import { useState } from 'react'
import { QueryClientProvider } from '@tanstack/react-query'
import { Outlet, useLocation } from 'react-router'
import { creerQueryClient } from './queryClient'
import { Apparitions } from './Apparitions'

export function Fournisseurs() {
  const [queryClient] = useState(creerQueryClient)
  const location = useLocation()
  return (
    <QueryClientProvider client={queryClient}>
      <Apparitions />
      <div key={location.pathname} className="page-transition">
        <Outlet />
      </div>
    </QueryClientProvider>
  )
}
