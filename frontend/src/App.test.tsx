import { fireEvent, render, screen } from '@testing-library/react'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import App from './App'
import { preparerCsrf } from './app/preparerCsrf'
import { routes } from './app/router'
import { client } from './shared/client'

vi.mock('./shared/client', async () => {
  const reel = await vi.importActual<typeof import('./shared/client')>('./shared/client')
  return {
    ...reel,
    client: {
      get: vi.fn().mockResolvedValue({ status: 204 }),
    },
  }
})

describe('socle', () => {
  beforeEach(() => {
    vi.mocked(client.get).mockClear()
  })

  it('affiche l’accueil et demande le cookie CSRF', async () => {
    render(<App />)

    expect(await screen.findByRole('heading', { name: 'Accueil' })).toBeInTheDocument()
    expect(client.get).toHaveBeenCalledWith('/sanctum/csrf-cookie')
  })

  it('ne bloque pas l’écran si le cookie CSRF est absent', async () => {
    vi.mocked(client.get).mockRejectedValueOnce(new Error('absent'))

    await expect(preparerCsrf()).resolves.toBeUndefined()
  })

  it('marque le lien Store quand on est sur le Store', () => {
    const router = createMemoryRouter(routes, { initialEntries: ['/echange'] })
    render(<RouterProvider router={router} />)

    fireEvent.click(screen.getByRole('link', { name: 'Store' }))

    expect(screen.getByRole('heading', { name: 'Store' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Store' })).toHaveAttribute('aria-current', 'page')
  })
})
