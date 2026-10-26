import { describe, expect, it } from 'vitest'
import { formaterDate, formaterFcfa, formaterNote, formaterPeriode } from './format'

describe('formats', () => {
  it('écrit une note sur 5 avec une décimale au plus', () => {
    expect(formaterNote(4.5)).toBe('4,5')
    expect(formaterNote(5)).toBe('5')
  })

  it('écrit les montants en francs CFA entiers', () => {
    expect(formaterFcfa(22000)).toBe('22 000 F')
  })

  it('lit une date seule comme un jour du calendrier et écrit « 1er »', () => {
    expect(formaterDate('2026-11-01')).toBe('1er novembre 2026')
    expect(formaterDate('2026-11-05')).toBe('5 novembre 2026')
  })

  it('écrit une période sur un mois', () => {
    expect(formaterPeriode('2026-11-05', '2026-12-04')).toBe('du 5 novembre au 4 décembre 2026')
    expect(formaterPeriode('2026-12-01', '2027-01-01')).toBe('du 1er décembre 2026 au 1er janvier 2027')
  })
})
