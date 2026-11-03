import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { describe, expect, it } from 'vitest'
import type { Discussion } from './api'
import { CadreDemo } from './CadreDemo'

function discussion(partiel: Partial<Discussion>): Discussion {
  return {
    id: 12,
    titre: 'Le paiement MoMo échoue',
    texte: 'Le callback renvoie 500.',
    auteur: { id: 1, nom: 'Afi Dossou', username: 'afi-dossou', avatar_url: null, github_url: null, profil: 'dev', pays: 'BJ' },
    canal: 'mobile-money',
    app_id: null,
    depot_url: null,
    demo_url: null,
    etiquettes: ['question'],
    bug: null,
    copie_test: null,
    analyse_ia: null,
    resolue: false,
    nb_messages: 0,
    cree_le: '2026-10-17T08:00:00Z',
    ...partiel,
  }
}

function afficher(sujet: Discussion) {
  render(
    <QueryClientProvider client={new QueryClient()}>
      <CadreDemo discussion={sujet} auteur={false} />
    </QueryClientProvider>,
  )
}

describe('CadreDemo', () => {
  it('montre la démo dans un cadre isolé : scripts et formulaires, sans accès au site ni à ses cookies', () => {
    afficher(discussion({ demo_url: 'https://demo.exemple.dev/boutique' }))

    const cadre = screen.getByTitle('Démo isolée')
    expect(cadre).toHaveAttribute('src', 'https://demo.exemple.dev/boutique')
    // Sans allow-same-origin, la démo n'atteint ni le DOM de Souk.dev ni son stockage ;
    // sans allow-top-navigation, elle ne peut pas emmener le visiteur ailleurs.
    expect(cadre).toHaveAttribute('sandbox', 'allow-scripts allow-forms')
    expect(cadre).toHaveAttribute('referrerpolicy', 'no-referrer')
  })

  it('montre la copie de test en ligne quand la discussion n’a pas de démo', () => {
    afficher(
      discussion({
        depot_url: 'https://github.com/afi-dossou/boutique-momo',
        copie_test: { id: 3, etat: 'en_ligne', url: 'http://dtest3.localhost', erreur: null } as Discussion['copie_test'],
      }),
    )

    expect(screen.getByTitle('Démo isolée')).toHaveAttribute('src', 'http://dtest3.localhost')
  })
})
