import { act, fireEvent, render, screen } from '@testing-library/react'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { creerRoutes } from '../../app/router'
import { cheminLaravel, clientLocal } from '../../shared/client'
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
  return { ...reel, clientLocal: http }
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
    machine_nom: 'locale',
    suspension_admin_motif: null,
  }
}

function reponses(gardees: Sauvegarde[]) {
  vi.mocked(clientLocal.get).mockImplementation((url: string) => {
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
  vi.mocked(clientLocal.post).mockImplementation((url: string) => {
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
    vi.mocked(clientLocal.get).mockReset()
    vi.mocked(clientLocal.post).mockReset()
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

describe('commande pas encore payée dans « Mes copies »', () => {
  it('s’annule après confirmation, avec la facture en attente', async () => {
    const commande: Deploiement = {
      ...copie(),
      etat: 'attente_paiement',
      url: null,
      derniere_sauvegarde: null,
      facture_a_payer: { id: 9, numero: 'SD-2026-000009', montant_total: 3500, echeance_le: '2026-11-16' } as Deploiement['facture_a_payer'],
    }
    vi.mocked(clientLocal.get).mockReset().mockImplementation((url: string) => {
      if (url === cheminLaravel('/deployments')) {
        return Promise.resolve({
          data: { data: [commande], links: { first: null, last: null, prev: null, next: null }, meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 } },
        })
      }
      if (url === cheminLaravel('/session')) return Promise.resolve({ data: { id: 1, username: 'pharmacie', profil: 'client' } })
      return Promise.resolve({ data: null })
    })
    vi.mocked(clientLocal.delete).mockReset().mockResolvedValue({ data: null })

    const router = createMemoryRouter(creerRoutes(), { initialEntries: ['/copies'] })
    render(<RouterProvider router={router} />)
    fireEvent.click(await screen.findByRole('button', { name: 'Annuler la commande' }))

    expect(await screen.findByRole('dialog', { name: 'Annuler la commande de Pharmacie ?' })).toBeInTheDocument()
    expect(screen.getByText(/La facture SD-2026-000009 est annulée/)).toBeInTheDocument()
    await act(async () => {
      fireEvent.click(screen.getAllByRole('button', { name: 'Annuler la commande' }).at(-1)!)
    })

    expect(clientLocal.delete).toHaveBeenCalledWith(cheminLaravel('/deployments/1'))
  })
})
