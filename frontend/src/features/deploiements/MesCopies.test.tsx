import { act, fireEvent, render, screen } from '@testing-library/react'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { creerRoutes } from '../../app/router'
import { cheminLaravel, client } from '../../shared/client'
import type { Sauvegarde } from '../catalogue/api'
import type { Deploiement } from '../paiements/api'
import { DELAI_ECHEC_SAUVEGARDE_MS } from './MesCopies'

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

function sauvegarde(identifiant: string, type: Sauvegarde['type'] = 'quotidienne'): Sauvegarde {
  return { identifiant, type, cree_le: '2026-10-26T03:30:00Z', taille: 12 * 1024 * 1024 }
}

function copie(): Deploiement {
  return {
    id: 1,
    app_id: 7,
    app_nom: 'Pharmacie',
    taille: 'petite',
    type: 'client',
    etat: 'en_ligne',
    url: 'http://pharmacie.localhost',
    erreur: null,
    cree_le: '2026-10-20T08:00:00Z',
    abonnement: null,
    facture_a_payer: null,
    version: 1,
    version_disponible: null,
    mise_a_jour: null,
    derniere_sauvegarde: sauvegarde('20261026-033000'),
  }
}

function reponses(gardees: Sauvegarde[]) {
  vi.mocked(client.get).mockImplementation((url: string) => {
    if (url === cheminLaravel('/deployments')) {
      const liste = [copie()]
      return Promise.resolve({
        data: {
          data: liste,
          links: { first: null, last: null, prev: null, next: null },
          meta: { current_page: 1, last_page: 1, per_page: 20, total: liste.length },
        },
      })
    }
    if (url === cheminLaravel('/deployments/1/sauvegardes')) {
      return Promise.resolve({ data: { data: gardees } })
    }
    return Promise.reject(new Error(`url inattendue ${url}`))
  })
  vi.mocked(client.post).mockImplementation((url: string) => {
    if (url === cheminLaravel('/deployments/1/sauvegardes')) return Promise.resolve({ data: null })
    return Promise.reject(new Error(`url inattendue ${url}`))
  })
}

async function rendu() {
  const router = createMemoryRouter(creerRoutes(), { initialEntries: ['/copies'] })
  render(<RouterProvider router={router} />)
  await screen.findByRole('heading', { name: 'Mes copies' })
  await screen.findByRole('article', { name: 'Pharmacie' })
  return router
}

describe('sauvegardes de « Mes copies »', () => {
  beforeEach(() => {
    vi.mocked(client.get).mockReset()
    vi.mocked(client.post).mockReset()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('liste chaque sauvegarde gardée avec son lien d’export', async () => {
    reponses([sauvegarde('20261026-033000'), sauvegarde('20261025-033000')])
    await rendu()

    expect(await screen.findByText('Voir les 2 sauvegardes gardées')).toBeInTheDocument()
    const exports = screen.getAllByRole('link', { name: 'Exporter' })
    expect(exports).toHaveLength(3)
    expect(exports.map((lien) => lien.getAttribute('href'))).toEqual([
      expect.stringContaining('/deployments/1/sauvegardes/20261026-033000'),
      expect.stringContaining('/deployments/1/sauvegardes/20261026-033000'),
      expect.stringContaining('/deployments/1/sauvegardes/20261025-033000'),
    ])
  })

  it('une demande sans nouvelle sauvegarde affiche une erreur et rend le bouton', async () => {
    reponses([sauvegarde('20261026-033000')])
    await rendu()

    vi.useFakeTimers()
    fireEvent.click(screen.getByRole('button', { name: 'Sauvegarder maintenant' }))
    await act(async () => {})
    expect(screen.getByText(/Sauvegarde demandée/)).toBeInTheDocument()

    // Le travail n’écrit rien : après le délai, l’erreur remplace l’attente et le bouton revient.
    act(() => {
      vi.advanceTimersByTime(DELAI_ECHEC_SAUVEGARDE_MS)
    })
    expect(screen.getByText(/La sauvegarde n’a pas abouti/)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Sauvegarder maintenant' })).toBeInTheDocument()
    expect(screen.queryByText(/Sauvegarde demandée/)).not.toBeInTheDocument()
  })
})
