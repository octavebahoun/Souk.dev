import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { routes } from '../../app/router'
import { cheminLaravel, client } from '../../shared/client'
import type { Discussion, Message } from './api'

vi.mock('../../shared/client', async () => {
  const reel = await vi.importActual<typeof import('../../shared/client')>('../../shared/client')
  const http = {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  }
  return { ...reel, client: http, clientLocal: http }
})

function page<T>(data: T[], total = data.length) {
  return {
    data,
    links: { first: null, last: null, prev: null, next: null },
    meta: { current_page: 1, last_page: 1, per_page: 20, total },
  }
}

function discussion(): Discussion {
  return {
    id: 12,
    titre: 'Le paiement MoMo échoue',
    texte: 'Le callback renvoie 500.',
    auteur: {
      id: 1,
      nom: 'Afi Dossou',
      username: 'afi-dossou',
      avatar_url: null,
      github_url: null,
      profil: 'dev',
      pays: 'BJ',
    },
    canal: 'mobile-money',
    app_id: null,
    depot_url: 'https://github.com/afi-dossou/boutique-momo',
    demo_url: null,
    etiquettes: ['bug'],
    bug: {
      erreur_obtenue: 'callback 500',
      comportement_attendu: 'commande payée',
      techno: 'laravel',
      code: null,
      difficulte: 'moyen',
    },
    copie_test: null,
    analyse_ia: null,
    resolue: false,
    nb_messages: 0,
    cree_le: '2026-10-17T08:00:00Z',
  }
}

function message(id: number): Message {
  return {
    id,
    texte: 'Regarde TrustProxies.',
    auteur: {
      id: 2,
      nom: 'Yao Koffi',
      username: 'yao-koffi',
      avatar_url: null,
      github_url: null,
      profil: 'dev',
      pays: 'BJ',
    },
    cree_le: '2026-10-17T09:00:00Z',
  }
}

function reponses(options: { discussion?: Discussion | Error; messages?: Message[]; moi?: { id: number } | null }) {
  vi.mocked(client.get).mockImplementation((url: string) => {
    if (url === cheminLaravel('/discussions/12')) {
      if (options.discussion instanceof Error) return Promise.reject(options.discussion)
      return Promise.resolve({ data: options.discussion ?? discussion() })
    }
    if (url === cheminLaravel('/discussions/12/messages')) {
      return Promise.resolve({ data: page(options.messages ?? []) })
    }
    if (url === cheminLaravel('/discussions/12/correctifs')) return Promise.resolve({ data: page([]) })
    if (url === cheminLaravel('/me')) {
      if (!options.moi) return Promise.reject(new Error('non connecté'))
      return Promise.resolve({ data: options.moi })
    }
    return Promise.reject(new Error(`url inattendue ${url}`))
  })
}

function rendu() {
  const router = createMemoryRouter(routes, { initialEntries: ['/echange/12'] })
  render(<RouterProvider router={router} />)
}

describe('page d’une discussion', () => {
  beforeEach(() => {
    vi.mocked(client.get).mockReset()
    vi.mocked(client.patch).mockReset()
  })

  it('propose de réessayer quand la discussion ne charge pas', async () => {
    reponses({ discussion: new Error('réseau') })
    rendu()

    fireEvent.click(await screen.findByRole('button', { name: 'Réessayer' }))

    await waitFor(() => {
      const appels = vi.mocked(client.get).mock.calls.filter((appel) => appel[0] === cheminLaravel('/discussions/12'))
      expect(appels.length).toBeGreaterThan(1)
    })
  })

  it('affiche l’état vide quand il n’y a aucun message', async () => {
    reponses({ messages: [] })
    rendu()

    expect(await screen.findByRole('heading', { name: 'Le paiement MoMo échoue' })).toBeInTheDocument()
    expect(await screen.findByText('Aucun message pour l’instant.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Marquer comme résolue' })).not.toBeInTheDocument()
  })

  it('montre le bouton résolue à l’auteur et envoie le PATCH', async () => {
    reponses({ moi: { id: 1 }, messages: [message(3)] })
    vi.mocked(client.patch).mockResolvedValue({ data: { ...discussion(), resolue: true } })
    rendu()

    fireEvent.click(await screen.findByRole('button', { name: 'Marquer comme résolue' }))

    await waitFor(() => {
      expect(client.patch).toHaveBeenCalledWith(cheminLaravel('/discussions/12'), { resolue: true })
    })
    expect(await screen.findByRole('button', { name: 'Rouvrir' })).toBeInTheDocument()
  })
})
