import { fireEvent, render, screen } from '@testing-library/react'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { describe, expect, it, vi } from 'vitest'
import { creerRoutes } from '../../app/router'
import { cheminLaravel, clientLocal } from '../../shared/client'
import type { CompteAdmin } from './api'

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

function compte(id: number, nom: string): CompteAdmin {
  return {
    id,
    nom,
    username: nom.toLowerCase(),
    email: `${nom.toLowerCase()}@exemple.fr`,
    profil: 'dev',
    est_admin: true,
    cree_le: '2026-10-20T08:00:00Z',
    suspendu_le: null,
    suspension_motif: null,
  }
}

function reponses() {
  vi.mocked(clientLocal.get).mockImplementation((url: string) => {
    if (url === cheminLaravel('/session')) {
      return Promise.resolve({ data: { id: 1, profil: 'dev', est_admin: true } })
    }
    if (url === cheminLaravel('/admin/signalements')) {
      return Promise.resolve({
        data: {
          data: [],
          links: { first: null, last: null, prev: null, next: null },
          meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 },
        },
      })
    }
    if (url === cheminLaravel('/admin/comptes')) {
      const liste = [compte(1, 'Moi'), compte(2, 'Autre')]
      return Promise.resolve({
        data: {
          data: liste,
          links: { first: null, last: null, prev: null, next: null },
          meta: { current_page: 1, last_page: 1, per_page: 20, total: liste.length },
        },
      })
    }
    return Promise.reject(new Error(`url inattendue ${url}`))
  })
}

describe('modération des comptes', () => {
  it('ne désactive Suspendre que pour son propre compte, même admin', async () => {
    reponses()
    const router = createMemoryRouter(creerRoutes(), { initialEntries: ['/admin/moderation'] })
    render(<RouterProvider router={router} />)
    fireEvent.click(await screen.findByRole('tab', { name: 'Comptes' }))

    // Le tableau rend chaque ligne deux fois (table + cartes mobile).
    const boutons = await screen.findAllByRole('button', { name: 'Suspendre' })
    const bloques = boutons.filter((bouton) => bouton.hasAttribute('disabled'))
    const libres = boutons.filter((bouton) => !bouton.hasAttribute('disabled'))
    expect(bloques).toHaveLength(2)
    expect(libres).toHaveLength(2)
    for (const bloque of bloques) {
      expect(bloque.getAttribute('title')).toBe('L’équipe ne suspend pas son propre compte.')
    }
  })
})
