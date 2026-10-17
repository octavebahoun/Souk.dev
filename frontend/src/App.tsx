import { useEffect, useState } from 'react'
import { QueryClientProvider } from '@tanstack/react-query'
import { RouterProvider } from 'react-router'
import { preparerCsrf } from './app/preparerCsrf'
import { creerQueryClient } from './app/queryClient'
import { creerRouter } from './app/router'

export default function App() {
  const [queryClient] = useState(creerQueryClient)
  const [router] = useState(creerRouter)

  useEffect(() => {
    void preparerCsrf()
  }, [])

  return (
    <QueryClientProvider client={queryClient}>
      <RouterProvider router={router} />
    </QueryClientProvider>
  )
}
