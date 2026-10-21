import type { Categorie } from './api'

export const teintes: Record<Categorie, { fond: string; texte: string }> = {
  commerce: { fond: '#FEF3C7', texte: '#92400E' },
  gestion: { fond: '#E8EEFD', texte: '#1F4FE0' },
  education: { fond: '#F3E8FF', texte: '#6D28D9' },
  sante: { fond: '#DCFCE7', texte: '#166534' },
  finance: { fond: '#E0F2FE', texte: '#075985' },
  association: { fond: '#FFEDD5', texte: '#9A3412' },
  restauration: { fond: '#FCE7F3', texte: '#9D174D' },
  autre: { fond: '#EEF2F9', texte: '#334155' },
}
