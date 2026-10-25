import type { AxiosError } from 'axios'
import { describe, expect, it } from 'vitest'
import { lireErreurHttp } from './api'

function erreurHttp(statut: number, data: unknown): AxiosError {
  return {
    isAxiosError: true,
    response: { status: statut, data },
  } as unknown as AxiosError
}

describe('lireErreurHttp', () => {
  it('masque les détails SQL des réponses serveur', () => {
    const resultat = lireErreurHttp(erreurHttp(500, {
      message: 'could not find driver (Connection: sqlite, SQL: select * from sessions)',
    }))

    expect(resultat.message).toBe('Le serveur a rencontré un problème. Réessayez dans quelques instants.')
    expect(resultat.message).not.toContain('sqlite')
  })

  it('accepte une page HTML renvoyée par le serveur sans planter', () => {
    const resultat = lireErreurHttp(erreurHttp(500, '<html>SQL exception</html>'))

    expect(resultat.message).toBe('Le serveur a rencontré un problème. Réessayez dans quelques instants.')
    expect(resultat.champs).toEqual({})
  })

  it('garde les messages de validation et les erreurs de champ', () => {
    const resultat = lireErreurHttp(erreurHttp(422, {
      message: 'Les données sont invalides.',
      errors: { email: ['Adresse invalide.'] },
    }))

    expect(resultat.message).toBe('Les données sont invalides.')
    expect(resultat.champs.email).toBe('Adresse invalide.')
  })
})
