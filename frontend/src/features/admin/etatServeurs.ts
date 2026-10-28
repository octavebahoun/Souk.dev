import type { Machine } from './api'
import type { TonBadge } from '../../ui'

/** 512 → « 512 Mo », 8192 → « 8 Go », 1228 → « 1,2 Go ». */
export function formaterMemoire(mo: number): string {
  if (mo < 1024) return `${mo} Mo`
  const go = mo / 1024
  return `${go.toLocaleString('fr-FR', { maximumFractionDigits: 1 })} Go`
}

export function texteMemoire(machine: Machine): string {
  if (machine.memoire_libre_mo === null || machine.capacite_mo === null) {
    return `Sans limite déclarée · ${formaterMemoire(machine.memoire_reservee_mo)} réservés`
  }
  return `${formaterMemoire(machine.memoire_libre_mo)} libres sur ${formaterMemoire(machine.capacite_mo)}`
}

export function partUtilisee(machine: Machine): number | null {
  if (machine.memoire_libre_mo === null || machine.capacite_mo === null || machine.capacite_mo === 0) return null
  return Math.min(1, Math.max(0, 1 - machine.memoire_libre_mo / machine.capacite_mo))
}

export function tonJauge(machine: Machine): 'succes' | 'alerte' | 'danger' | 'neutre' {
  const part = partUtilisee(machine)
  if (part === null) return 'neutre'
  if (part >= 0.9) return 'danger'
  if (part >= 0.7) return 'alerte'
  return 'succes'
}

export function presentationEtat(machine: Machine): { ton: TonBadge; libelle: string } {
  if (!machine.joignable) return { ton: 'danger', libelle: 'Injoignable' }
  if (machine.etat === 'maintenance') return { ton: 'alerte', libelle: 'En maintenance' }
  return { ton: 'succes', libelle: 'En service' }
}

export function texteCopies(nombre: number): string {
  return nombre === 0 ? 'Aucune copie' : `${nombre} copie${nombre > 1 ? 's' : ''}`
}
