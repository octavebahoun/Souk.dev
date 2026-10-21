import { useEffect, useState } from 'react'
import { RouterProvider } from 'react-router'
import { preparerCsrf } from './app/preparerCsrf'
import { creerRouter, routes } from './app/router'
import { brancherComptes } from './features/comptes/brancher'

brancherComptes(routes)

export default function App() {
  const [router] = useState(creerRouter)

  useEffect(() => {
    void preparerCsrf()
  }, [])

  return <RouterProvider router={router} />
}
