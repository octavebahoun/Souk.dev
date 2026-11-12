import { useEffect } from 'react'
import { t } from '../i18n'

type OptionsPage = {
  /** Résumé de la page pour les moteurs de recherche et les aperçus de partage. */
  description?: string | null
  /** Image de l'aperçu de partage (adresse absolue ou chemin du site). */
  image?: string | null
  /** Page à ne pas indexer (introuvable, espace connecté). */
  sansIndex?: boolean
}

const DESCRIPTION_PAR_DEFAUT = document.querySelector<HTMLMetaElement>('meta[name="description"]')?.content ?? ''
const IMAGE_PAR_DEFAUT = document.querySelector<HTMLMetaElement>('meta[property="og:image"]')?.content ?? ''

function poser(selecteur: string, attribut: 'content' | 'href', valeur: string, creer?: () => HTMLElement): void {
  let balise = document.head.querySelector<HTMLElement>(selecteur)
  if (!balise && creer) {
    balise = creer()
    document.head.appendChild(balise)
  }
  balise?.setAttribute(attribut, valeur)
}

function meta(nom: string, valeur: string): HTMLElement {
  const balise = document.createElement('meta')
  balise.setAttribute('name', nom)
  balise.setAttribute('content', valeur)
  return balise
}

/**
 * Titre de l'onglet (« Page — Souk.dev ») et, avec lui, ce que lisent les moteurs de recherche
 * et les aperçus de partage : description, adresse canonique, titre et image de la page.
 */
export function useTitrePage(titre: string | null | undefined, options: OptionsPage = {}): void {
  const { description, image, sansIndex = false } = options

  useEffect(() => {
    const complet = titre ? `${titre} — ${t.marque.nom}` : `${t.marque.nom} — ${t.marque.accroche}`
    const resume = (description ?? '').replace(/\s+/g, ' ').trim().slice(0, 300) || DESCRIPTION_PAR_DEFAUT
    const adresse = `${window.location.origin}${window.location.pathname}`
    const apercu = image ? new URL(image, window.location.origin).href : IMAGE_PAR_DEFAUT

    document.title = complet
    poser('meta[name="description"]', 'content', resume)
    poser('link[rel="canonical"]', 'href', adresse)
    poser('meta[property="og:url"]', 'content', adresse)
    poser('meta[property="og:title"]', 'content', complet)
    poser('meta[property="og:description"]', 'content', resume)
    poser('meta[name="twitter:title"]', 'content', complet)
    poser('meta[name="twitter:description"]', 'content', resume)
    if (apercu) {
      poser('meta[property="og:image"]', 'content', apercu)
      poser('meta[name="twitter:image"]', 'content', apercu)
    }
    poser('meta[name="robots"]', 'content', sansIndex ? 'noindex, nofollow' : 'index, follow', () => meta('robots', 'index, follow'))
  }, [titre, description, image, sansIndex])
}
