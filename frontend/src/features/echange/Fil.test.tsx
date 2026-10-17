import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { routes } from '../../app/router'
import { cheminApi, client } from '../../shared/client'
import type { Canal, Discussion, Etiquette } from './api'

vi.mock('../../shared/client', async () => {
  const reel = await vi.importActual<typeof import('../../shared/client')>('../../shared/client')
  return {
    ...reel,
    client: {
      get: vi.fn(),
      post: vi.fn(),
    },
  }
})

const canaux: Canal[] = [
  { slug: 'laravel', nom: 'Laravel', description: null, pays: null },
  { slug: 'laravel-benin', nom: 'Laravel Bénin', description: null, pays: 'BJ' },
]

const etiquettes: Etiquette[] = ['bug', 'question', 'tuto', 'projet', 'entraide']

function discussion(id: number, titre: string): Discussion {
  return {
    id,
    titre,
    texte: 'Un texte de discussion assez long pour l’aperçu.',
    auteur: {
      id: 1,
      nom: 'Afi Dossou',
      username: 'afi-dossou',
      avatar_url: null,
      github_url: null,
      profil: 'dev',
      pays: 'BJ',
    },
    canal: 'laravel',
    app_id: null,
    depot_url: null,
    demo_url: null,
    etiquettes: ['bug'],
    bug: null,
    copie_test: null,
    analyse_ia: null,
    resolue: false,
    nb_messages: 2,
    cree_le: '2026-10-17T08:00:00Z',
  }
}

function reponses(discussions: Discussion[]) {
  vi.mocked(client.get).mockImplementation((url: string) => {
    if (url === cheminApi('/canaux')) return Promise.resolve({ data: canaux })
    if (url === cheminApi('/etiquettes')) return Promise.resolve({ data: etiquettes })
    if (url === cheminApi('/discussions')) {
      return Promise.resolve({
        data: {
          data: discussions,
          links: { first: null, last: null, prev: null, next: null },
          meta: { current_page: 1, last_page: 1, per_page: 20, total: discussions.length },
        },
      })
    }
    return Promise.reject(new Error(`url inattendue ${url}`))
  })
}

function rendu(url = '/echange') {
  const router = createMemoryRouter(routes, { initialEntries: [url] })
  render(<RouterProvider router={router} />)
  return router
}

describe('fil des discussions', () => {
  beforeEach(() => {
    vi.mocked(client.get).mockReset()
  })

  it('écrit le filtre étiquette dans l’URL', async () => {
    reponses([discussion(1, 'Callback MoMo')])
    const router = rendu()

    fireEvent.click(await screen.findByRole('button', { name: 'bug' }))

    await waitFor(() => {
      expect(router.state.location.search).toContain('etiquette=bug')
    })
    expect(client.get).toHaveBeenCalledWith(
      cheminApi('/discussions'),
      expect.objectContaining({ params: expect.objectContaining({ etiquette: 'bug' }) }),
    )
  })

  it('affiche l’état vide quand les filtres ne ramènent rien', async () => {
    reponses([])
    rendu('/echange?etiquette=bug')

    expect(await screen.findByText(/Aucune discussion pour ces filtres/)).toBeInTheDocument()
    expect(screen.getAllByRole('button', { name: 'Effacer les filtres' }).length).toBeGreaterThan(0)
  })

  it('propose de réessayer quand le chargement échoue', async () => {
    vi.mocked(client.get).mockImplementation((url: string) => {
      if (url === cheminApi('/discussions')) return Promise.reject(new Error('réseau'))
      if (url === cheminApi('/canaux')) return Promise.resolve({ data: canaux })
      if (url === cheminApi('/etiquettes')) return Promise.resolve({ data: etiquettes })
      return Promise.reject(new Error(url))
    })
    rendu()

    fireEvent.click(await screen.findByRole('button', { name: 'Réessayer' }))

    await waitFor(() => {
      const appels = vi.mocked(client.get).mock.calls.filter((appel) => appel[0] === cheminApi('/discussions'))
      expect(appels.length).toBeGreaterThan(1)
    })
  })
})
