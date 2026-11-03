import axios from 'axios'

const apiUrl = import.meta.env.VITE_API_URL
// Production : l'API est sur un autre sous-domaine (https://api.$DOMAINE), figé au build.
const baseApi = import.meta.env.VITE_API_BASE

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
// En production, vers l'API absolue (mêmes chemins /api/…, cookies cross-sous-domaines).
export const clientLocal = axios.create({
  baseURL: baseApi || undefined,
  ...options,
})

// Prism sert les chemins du contrat à la racine (/discussions).
// Laravel les préfixe avec /api. Les routes web (CSRF, connexion) ne passent pas ici.
export function cheminApi(chemin: string): string {
  if (apiUrl) return chemin
  return `/api${chemin}`
}

export function cheminLaravel(chemin: string): string {
  return `/api${chemin}`
}
