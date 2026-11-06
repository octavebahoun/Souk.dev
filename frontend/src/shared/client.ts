import axios from 'axios'

// Production : l'API est sur un autre sous-domaine (https://api.$DOMAINE), figé au build.
// En développement, vide : le proxy Vite transmet les mêmes chemins à Laravel.
const baseApi = import.meta.env.VITE_API_BASE

export const clientLocal = axios.create({
  baseURL: baseApi || undefined,
  withCredentials: true,
  withXSRFToken: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
})

/** Chemin d'une route de l'API JSON (Laravel les préfixe avec /api). */
export function cheminLaravel(chemin: string): string {
  return `/api${chemin}`
}

