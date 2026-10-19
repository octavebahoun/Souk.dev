import axios from 'axios'

const apiUrl = import.meta.env.VITE_API_URL

const options = {
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
}

export const client = axios.create({
  baseURL: apiUrl || undefined,
  ...options,
})

// Toujours le proxy Vite vers Laravel, même si VITE_API_URL pointe vers Prism.
export const clientLocal = axios.create(options)

// Prism sert les chemins du contrat à la racine (/discussions).
// Laravel les préfixe avec /api. Les routes web (CSRF, connexion) ne passent pas ici.
export function cheminApi(chemin: string): string {
  if (apiUrl) return chemin
  return `/api${chemin}`
}

export function cheminLaravel(chemin: string): string {
  return `/api${chemin}`
}
