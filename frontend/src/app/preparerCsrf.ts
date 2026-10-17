import { client } from '../shared/client'

// Demande le cookie XSRF-TOKEN. Prism répond 204 sans vrai cookie.
// Laravel n'a pas encore Sanctum : un 404 ne doit pas bloquer l'écran.
export function preparerCsrf(): Promise<void> {
  return client
    .get('/sanctum/csrf-cookie')
    .then(() => undefined)
    .catch(() => undefined)
}
