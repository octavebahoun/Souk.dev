import { fireEvent, render, screen } from '@testing-library/react'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { creerRoutes } from '../../app/router'
import { cheminLaravel, clientLocal } from '../../shared/client'
import type { RedactionSoukdev } from './api'

vi.mock('../../shared/client', async () => {
  const reel = await vi.importActual<typeof import('../../shared/client')>('../../shared/client')
  const http = { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() }
  return { ...reel, clientLocal: http }
})

const redaction: RedactionSoukdev = {
  soukdev_json: '{\n    "version": 1,\n    "service_web": { "nom": "web", "port": 80 }\n}\n',
  deductions: ['Service web : « web », port 80, lus dans docker-compose.yml.'],
  a_verifier: ['APP_URL vaut « http://localhost » : en ligne, la copie n’est pas sur localhost.'],
  lien_github: 'https://github.com/awa/boutique/new/main?filename=soukdev.json&value=%7B%7D',
}

describe('soukdev.json écrit par Souk.dev', () => {
  beforeEach(() => {
    vi.mocked(clientLocal.get).mockReset().mockImplementation((url: string) => {
      if (url === cheminLaravel('/me')) return Promise.resolve({ data: { id: 1, username: 'awa', profil: 'dev' } })
      return Promise.resolve({ data: null })
    })
    vi.mocked(clientLocal.post).mockReset().mockImplementation((url: string) => {
      if (url === cheminLaravel('/apps/soukdev-json')) return Promise.resolve({ data: redaction })
      return Promise.reject(new Error(`url inattendue ${url}`))
    })
  })

  it('montre le fichier proposé, ce qui reste à vérifier et le lien vers GitHub', async () => {
    render(<RouterProvider router={createMemoryRouter(creerRoutes(), { initialEntries: ['/publier'] })} />)

    fireEvent.change(await screen.findByLabelText('Adresse du dépôt'), { target: { value: 'https://github.com/awa/boutique' } })
    fireEvent.click(screen.getByRole('button', { name: 'Souk.dev l’écrit pour vous' }))

    expect(await screen.findByRole('heading', { name: 'Votre soukdev.json, écrit par Souk.dev' })).toBeInTheDocument()
    expect(clientLocal.post).toHaveBeenCalledWith(cheminLaravel('/apps/soukdev-json'), { depot_url: 'https://github.com/awa/boutique' })
    expect(screen.getByText(/"service_web"/)).toBeInTheDocument()
    expect(screen.getByText(redaction.a_verifier[0])).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Ajouter à mon dépôt sur GitHub/ })).toHaveAttribute('href', redaction.lien_github)

    // Un autre dépôt : la proposition de l'ancien disparaît.
    fireEvent.change(screen.getByLabelText('Adresse du dépôt'), { target: { value: 'https://github.com/awa/autre' } })
    expect(screen.queryByRole('heading', { name: 'Votre soukdev.json, écrit par Souk.dev' })).not.toBeInTheDocument()
  })
})
