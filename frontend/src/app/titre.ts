import { useEffect } from 'react'
import { t } from '../i18n'

/** Titre de l'onglet du navigateur : « Page — Souk.dev ». */
export function useTitrePage(titre: string | null | undefined): void {
  useEffect(() => {
    document.title = titre ? `${titre} — ${t.marque.nom}` : `${t.marque.nom} — ${t.marque.accroche}`
  }, [titre])
}
