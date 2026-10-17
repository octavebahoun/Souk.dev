import axios from 'axios'

const apiUrl = import.meta.env.VITE_API_URL

export const client = axios.create({
  baseURL: apiUrl || undefined,
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
})

// Prism sert les chemins du contrat à la racine (/discussions).
// Laravel les préfixe avec /api. Les routes web (CSRF, connexion) ne passent pas ici.
export function cheminApi(chemin: string): string {
  if (apiUrl) return chemin
  return `/api${chemin}`
}
