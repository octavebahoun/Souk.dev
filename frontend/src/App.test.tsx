import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import App from './App'
import { preparerCsrf } from './app/preparerCsrf'
import { creerRoutes } from './app/router'
import { client } from './shared/client'

vi.mock('./shared/client', async () => {
  const reel = await vi.importActual<typeof import('./shared/client')>('./shared/client')
  const http = {
    get: vi.fn().mockResolvedValue({ status: 204, data: null }),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  }
  return { ...reel, client: http, clientLocal: http }
})

describe('socle', () => {
  beforeEach(() => {
    vi.mocked(client.get).mockClear()
  })

  it('affiche l’accueil et demande le cookie CSRF', async () => {
    const { container } = render(<App />)

    expect(await screen.findByRole('heading', { name: /Le marché/ })).toBeInTheDocument()
    await waitFor(() => expect(container.querySelector('[data-reveal-item]')).toHaveAttribute('data-revealed', 'true'))
    expect(client.get).toHaveBeenCalledWith('/sanctum/csrf-cookie')
  })

  it('ne fabrique aucune appli quand le Store réel est vide', async () => {
    const vide = { data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } }
    vi.mocked(client.get).mockImplementation(((url: string) => {
      if (url === '/api/apps' || url === '/api/discussions') {
        return Promise.resolve({ status: 200, data: vide })
      }
      return Promise.resolve({ status: 204, data: null })
    }) as never)

    render(<App />)

    expect(await screen.findByText('Aucune discussion publique publiée pour le moment.')).toBeInTheDocument()
    expect(screen.getByText('Aucune appli publiée')).toBeInTheDocument()
    expect(screen.queryByText('Boutique MoMo')).not.toBeInTheDocument()
  })

  it('ne bloque pas l’écran si le cookie CSRF est absent', async () => {
    vi.mocked(client.get).mockRejectedValueOnce(new Error('absent'))

    await expect(preparerCsrf()).resolves.toBeUndefined()
  })

  it('marque le lien Store quand on est sur le Store', async () => {
    const router = createMemoryRouter(creerRoutes(), { initialEntries: ['/echange'] })
    render(<RouterProvider router={router} />)

    await screen.findByRole('heading', { name: 'Échange' })
    fireEvent.click(within(screen.getByRole('navigation', { name: 'Navigation principale' })).getByRole('link', { name: 'Store' }))

    expect(await screen.findByRole('heading', { name: 'Store' })).toBeInTheDocument()
    const navigation = screen.getByRole('navigation', { name: 'Navigation principale' })
    expect(within(navigation).getByRole('link', { name: 'Store' })).toHaveAttribute('aria-current', 'page')
  })
})
