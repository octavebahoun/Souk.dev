import { describe, expect, it } from 'vitest'
import type { Machine } from './api'
import { formaterMemoire, partUtilisee, presentationEtat, texteCopies, texteMemoire } from './etatServeurs'

function machine(partiel: Partial<Machine> = {}): Machine {
  return {
    id: 1,
    nom: 'locale',
    hote_docker: null,
    etat: 'active',
    joignable: true,
    version_docker: '28.0.1',
    memoire_totale_mo: 16384,
    capacite_mo: 8192,
    memoire_reservee_mo: 2048,
    memoire_libre_mo: 6144,
    copies: 2,
    sonde_erreur: null,
    sondee_le: '2026-10-28T10:00:00+00:00',
    ...partiel,
  } as Machine
}

describe('formaterMemoire', () => {
  it('affiche les mégaoctets sous 1 Go', () => {
    expect(formaterMemoire(512)).toBe('512 Mo')
  })

  it('affiche les gigaoctets au-dessus', () => {
    expect(formaterMemoire(8192)).toBe('8 Go')
    expect(formaterMemoire(1228)).toBe('1,2 Go')
  })
})

describe('texteMemoire', () => {
  it('annonce le libre et la capacité', () => {
    expect(texteMemoire(machine())).toBe('6 Go libres sur 8 Go')
  })

  it('dit quand aucune limite n’est déclarée', () => {
    expect(texteMemoire(machine({ capacite_mo: null, memoire_totale_mo: null, memoire_libre_mo: null }))).toBe('Sans limite déclarée · 2 Go réservés')
  })

  it('annonce le libre sondé sans limite déclarée', () => {
    expect(texteMemoire(machine({ capacite_mo: null, memoire_totale_mo: 16384, memoire_libre_mo: 14336 }))).toBe('environ 14 Go libres (mémoire sondée)')
  })
})

describe('partUtilisee', () => {
  it('vaut 0 sans réservation, 1 quand plein', () => {
    expect(partUtilisee(machine({ memoire_reservee_mo: 0, memoire_libre_mo: 8192 }))).toBe(0)
    expect(partUtilisee(machine({ memoire_reservee_mo: 8192, memoire_libre_mo: 0 }))).toBe(1)
  })

  it('reste muette sans limite déclarée', () => {
    expect(partUtilisee(machine({ capacite_mo: null, memoire_totale_mo: null, memoire_libre_mo: null }))).toBeNull()
  })

  it('jauge sur la mémoire sondée sans limite déclarée', () => {
    expect(partUtilisee(machine({ capacite_mo: null, memoire_totale_mo: 8192, memoire_libre_mo: 6144 }))).toBeCloseTo(0.25, 5)
  })
})

describe('presentationEtat', () => {
  it('signale l’injoignable avant la maintenance', () => {
    expect(presentationEtat(machine({ joignable: false, etat: 'maintenance' }))).toEqual({ ton: 'danger', libelle: 'Injoignable' })
    expect(presentationEtat(machine({ etat: 'maintenance' }))).toEqual({ ton: 'alerte', libelle: 'En maintenance' })
    expect(presentationEtat(machine())).toEqual({ ton: 'succes', libelle: 'En service' })
  })
})

describe('texteCopies', () => {
  it('accorde', () => {
    expect(texteCopies(0)).toBe('Aucune copie')
    expect(texteCopies(1)).toBe('1 copie')
    expect(texteCopies(3)).toBe('3 copies')
  })
})
