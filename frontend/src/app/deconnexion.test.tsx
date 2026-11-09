import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { describe, expect, it, vi } from 'vitest'
import { creerRoutes } from './router'

let connecte = true
vi.mock('../shared/client', async () => {
  const reel = await vi.importActual<typeof import('../shared/client')>('../shared/client')
  const vide = { data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 }, non_lues: 0 }
  const moi = { id: 1, nom: 'Awa Test', username: 'awa', email: 'a@b.c', profil: 'dev', pays: 'BJ', avatar_url: null, est_admin: false,
    souk_score: { total: 1 }, reputation: { niveau: 'debutant', xp: 0 } }
  const http = {
    get: vi.fn((url: string) => {
      if (url === '/api/me') return connecte ? Promise.resolve({ status: 200, data: moi }) : Promise.reject(Object.assign(new Error('401'), { isAxiosError: true, response: { status: 401 } }))
      return Promise.resolve({ status: 200, data: vide })
    }),
    post: vi.fn(async (url: string) => { if (url === '/logout') connecte = false; return { status: 204, data: null } }),
    patch: vi.fn(), delete: vi.fn(),
  }
  return { ...reel, clientLocal: http }
})

describe('déconnexion', () => {
  for (const depart of ['/store', '/tableau']) {
    it(`rend l’en-tête au visiteur, depuis ${depart}`, async () => {
      connecte = true
      const router = createMemoryRouter(creerRoutes(), { initialEntries: [depart] })
      render(<RouterProvider router={router} />)
      fireEvent.click(await screen.findByRole('button', { name: /menu du compte/i }))
      fireEvent.click(await screen.findByRole('menuitem', { name: /Se déconnecter/ }))
      await waitFor(() => expect(router.state.location.pathname).toBe('/'))
      const entete = screen.getAllByRole('banner')[0]
      await waitFor(() => expect(within(entete).getByRole('link', { name: 'Se connecter' })).toBeInTheDocument())
      expect(within(entete).queryByRole('button', { name: /menu du compte/i })).toBeNull()
      expect(within(entete).queryByRole('button', { name: /notifications/i })).toBeNull()
    })
  }
})
