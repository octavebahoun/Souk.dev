import { useEffect, useState } from 'react'
import { RouterProvider } from 'react-router'
import { preparerCsrf } from './app/preparerCsrf'
import { creerRouter } from './app/router'

export default function App() {
  const [router] = useState(creerRouter)

  useEffect(() => {
    void preparerCsrf()
  }, [])

  return <RouterProvider router={router} />
}
