import { fireEvent, render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, type AxiosResponse } from 'axios'
import { describe, expect, it, vi } from 'vitest'
import { AnalyseSoukAi } from './AnalyseSoukAi'

vi.mock('./api', async () => {
  const reel = await vi.importActual<typeof import('./api')>('./api')
  return {
    ...reel,
    demanderAnalyse: vi.fn(() =>
      Promise.reject(
        new AxiosError('Too Many Requests', '429', undefined, undefined, {
          status: 429,
          data: { message: '3 analyses par 24 heures : la prochaine sera possible dans 2 h 05.' },
        } as AxiosResponse),
      ),
    ),
  }
})

describe('AnalyseSoukAi', () => {
  it('ne part qu’avec le consentement et dit quand réessayer au-delà de la limite', async () => {
    render(
      <QueryClientProvider client={new QueryClient()}>
        <AnalyseSoukAi discussionId={7} analyse={null} auteur />
      </QueryClientProvider>,
    )

    fireEvent.click(screen.getByRole('button', { name: 'Demander l’avis de Souk AI' }))
    const lancer = screen.getByRole('button', { name: 'Lancer l’analyse' })
    expect(lancer).toBeDisabled()
    fireEvent.click(screen.getByLabelText('J’accepte que ma fiche et mon code partent chez DeepSeek'))
    fireEvent.click(lancer)

    expect(await screen.findByText('3 analyses par 24 heures : la prochaine sera possible dans 2 h 05.')).toBeInTheDocument()
  })
})
