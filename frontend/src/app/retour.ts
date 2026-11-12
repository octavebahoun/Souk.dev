/**
 * Page où ramener quelqu'un après sa connexion (GitHub ou lien reçu par e-mail). La connexion
 * quitte le site : le chemin attend dans le navigateur, une heure au plus.
 */
const CLE = 'soukdev.retour'
const DUREE_MS = 60 * 60 * 1000

/** Seulement un chemin du site (« /store/1/deployer »), jamais une adresse externe. */
export function cheminSur(chemin: string | null | undefined): string | null {
  if (!chemin || !chemin.startsWith('/') || chemin.startsWith('//') || chemin.includes('\\') || chemin.startsWith('/connexion')) return null
  return chemin
}

export function retenirRetour(chemin: string | null | undefined): void {
  const sur = cheminSur(chemin)
  try {
    if (sur) localStorage.setItem(CLE, JSON.stringify({ chemin: sur, expire: Date.now() + DUREE_MS }))
  } catch {
    // Stockage indisponible (navigation privée) : la connexion mène à la page par défaut.
  }
}

/** Le chemin retenu, lu une seule fois. */
export function prendreRetour(): string | null {
  try {
    const brut = localStorage.getItem(CLE)
    if (!brut) return null
    localStorage.removeItem(CLE)
    const lu = JSON.parse(brut) as { chemin?: unknown; expire?: unknown }
    if (typeof lu.chemin !== 'string' || typeof lu.expire !== 'number' || lu.expire < Date.now()) return null
    return cheminSur(lu.chemin)
  } catch {
    return null
  }
}

/** Lien vers la connexion qui ramène ici ensuite. */
export function lienConnexion(retour: string, profil?: 'dev' | 'client'): string {
  const params = new URLSearchParams({ retour })
  if (profil) params.set('profil', profil)
  return `/connexion?${params.toString()}`
}
