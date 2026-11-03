import { ShieldAlert, ShieldCheck } from 'lucide-react'
import { Badge } from '../../ui'
import type { Appli } from './api'

/** Badge de l'analyse de sécurité d'une appli (carte du Store, fiche). */
export function BadgeSecurite({ statut }: { statut: Appli['securite']['statut'] }) {
  if (statut === 'verifie') {
    return <Badge ton="succes" icone={<ShieldCheck aria-hidden="true" className="size-3.5" />}>Security Checked</Badge>
  }
  if (statut === 'problemes') {
    return <Badge ton="alerte" icone={<ShieldAlert aria-hidden="true" className="size-3.5" />}>Problèmes détectés</Badge>
  }
  return <Badge>{statut === 'en_cours' ? 'Analyse en cours' : 'Analyse impossible'}</Badge>
}
