import { clientLocal } from '../shared/client'

// Cookie XSRF-TOKEN, toujours via le proxy vers Laravel (pas vers Prism).
// Un échec ne bloque pas l'écran : la prochaine écriture renverra 419.
export function preparerCsrf(): Promise<void> {
  return clientLocal
    .get('/sanctum/csrf-cookie')
    .then(() => undefined)
    .catch(() => undefined)
}
