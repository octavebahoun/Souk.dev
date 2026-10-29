import { describe, expect, it } from 'vitest'
import { cheminCible, estGrave, libellesCibles, libellesMotifs, libellesStatuts } from './presentationModeration'

describe('présentation de la modération', () => {
  it('nomme chaque motif, cible et statut en français', () => {
    expect(libellesMotifs).toEqual({
      spam: 'Spam',
      abus: 'Abus',
      hameconnage: 'Hameçonnage',
      minage: 'Minage',
      contenu_illegal: 'Contenu illégal',
      autre: 'Autre',
    })
    expect(libellesCibles.discussion).toBe('Discussion')
    expect(libellesCibles.copie).toBe('Copie')
    expect(libellesStatuts).toEqual({ ouvert: 'Ouvert', traite: 'Traité', rejete: 'Rejeté' })
  })

  it('marque graves les motifs qui préviennent l’équipe aussitôt', () => {
    expect(estGrave('minage')).toBe(true)
    expect(estGrave('hameconnage')).toBe(true)
    expect(estGrave('contenu_illegal')).toBe(true)
    expect(estGrave('spam')).toBe(false)
    expect(estGrave('autre')).toBe(false)
  })

  it('mène à la cible quand elle a une page', () => {
    expect(cheminCible('discussion', 12)).toBe('/echange/12')
    expect(cheminCible('appli', 7)).toBe('/store/7')
    expect(cheminCible('message', 3)).toBeNull()
    expect(cheminCible('copie', 9)).toBeNull()
    expect(cheminCible('compte', 4)).toBeNull()
  })
})
