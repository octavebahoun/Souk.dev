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
  soukdev_etat: 'nouveau',
  fichiers: [
    { chemin: 'Dockerfile', contenu: 'FROM nginx:1.27-alpine\nCOPY . /usr/share/nginx/html\n', lien_github: 'https://github.com/awa/boutique/new/main?filename=Dockerfile&value=FROM' },
  ],
  deductions: ['Service web : « web », port 80, lus dans docker-compose.yml.'],
  a_verifier: ['APP_URL vaut « http://localhost » : en ligne, la copie n’est pas sur localhost.'],
  lien_github: 'https://github.com/awa/boutique/new/main?filename=soukdev.json&value=%7B%7D',
}

describe('soukdev.json écrit par Souk.dev', () => {
  beforeEach(() => {
    vi.mocked(clientLocal.get).mockReset().mockImplementation((url: string) => {
      if (url === cheminLaravel('/session')) return Promise.resolve({ data: { id: 1, username: 'awa', profil: 'dev' } })
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
    fireEvent.click(screen.getByRole('button', { name: 'Souk.dev les écrit pour vous' }))

    expect(await screen.findByRole('heading', { name: 'Vos 2 fichiers, écrits par Souk.dev' })).toBeInTheDocument()
    expect(clientLocal.post).toHaveBeenCalledWith(cheminLaravel('/apps/soukdev-json'), { depot_url: 'https://github.com/awa/boutique' })
    expect(screen.getByText(/"service_web"/)).toBeInTheDocument()
    expect(screen.getByText(redaction.a_verifier[0])).toBeInTheDocument()
    expect(screen.getByText(/COPY \. \/usr\/share\/nginx\/html/)).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Ajouter Dockerfile sur GitHub (nouvel onglet)' })).toHaveAttribute('href', redaction.fichiers[0].lien_github)
    expect(screen.getByRole('link', { name: 'Ajouter soukdev.json sur GitHub (nouvel onglet)' })).toHaveAttribute('href', redaction.lien_github)

    // Un autre dépôt : la proposition de l'ancien disparaît.
    fireEvent.change(screen.getByLabelText('Adresse du dépôt'), { target: { value: 'https://github.com/awa/autre' } })
    expect(screen.queryByRole('heading', { name: 'Vos 2 fichiers, écrits par Souk.dev' })).not.toBeInTheDocument()
  })

  it('propose les fichiers manquants quand la vérification dit qu’il manque le docker-compose.yml, et garde le soukdev.json', async () => {
    const absent = 'docker-compose.yml est absent à la racine du dépôt.'
    vi.mocked(clientLocal.post).mockImplementation((url: string) => {
      if (url === cheminLaravel('/apps/verification')) {
        return Promise.reject({ isAxiosError: true, response: { status: 422, data: { message: absent, errors: { depot_url: [absent] } } } })
      }
      if (url === cheminLaravel('/apps/soukdev-json')) {
        return Promise.resolve({ data: { ...redaction, soukdev_etat: 'garde', lien_github: null, a_verifier: [] } })
      }
      return Promise.reject(new Error(`url inattendue ${url}`))
    })
    render(<RouterProvider router={createMemoryRouter(creerRoutes(), { initialEntries: ['/publier'] })} />)

    fireEvent.change(await screen.findByLabelText('Adresse du dépôt'), { target: { value: 'https://github.com/awa/boutique' } })
    fireEvent.click(screen.getByRole('button', { name: 'Vérifier le dépôt' }))
    fireEvent.click(await screen.findByRole('button', { name: 'Écrire les fichiers manquants' }))

    expect(await screen.findByRole('heading', { name: 'Votre Dockerfile, écrit par Souk.dev' })).toBeInTheDocument()
    expect(screen.getByText('Votre soukdev.json est gardé tel quel : il ne manquait que les fichiers Docker.')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Ajouter Dockerfile sur GitHub (nouvel onglet)' })).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /soukdev\.json sur GitHub/ })).not.toBeInTheDocument()
  })
})
