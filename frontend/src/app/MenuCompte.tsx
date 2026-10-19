import { useEffect, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { lireMoi } from '../features/echange/api'
import { nomPays } from '../features/echange/texte'
import { clientLocal } from '../shared/client'
import { preparerCsrf } from './preparerCsrf'

function initiales(nom: string | null, username: string | null): string {
  const source = (nom || username || '?').trim()
  const mots = source.split(/\s+/).filter(Boolean)
  const premiere = mots[0]?.[0] ?? '?'
  const seconde = mots[1]?.[0]
  if (seconde) return `${premiere}${seconde}`.toUpperCase()
  return source.slice(0, 2).toUpperCase()
}

export function MenuCompte() {
  const moi = useQuery({ queryKey: ['moi'], queryFn: lireMoi })
  const [ouvert, setOuvert] = useState(false)
  const bloc = useRef<HTMLDivElement>(null)
  const cache = useQueryClient()
  const naviguer = useNavigate()

  useEffect(() => {
    if (!ouvert) return
    function dehors(evenement: MouseEvent) {
      if (!bloc.current?.contains(evenement.target as Node)) setOuvert(false)
    }
    function touche(evenement: KeyboardEvent) {
      if (evenement.key === 'Escape') setOuvert(false)
    }
    document.addEventListener('mousedown', dehors)
    document.addEventListener('keydown', touche)
    return () => {
      document.removeEventListener('mousedown', dehors)
      document.removeEventListener('keydown', touche)
    }
  }, [ouvert])

  if (moi.isPending) {
    return <span aria-hidden="true" className="size-11 rounded-full bg-page" />
  }

  if (!moi.data) {
    return (
      <Link
        to="/connexion"
        className="inline-flex min-h-cible items-center rounded-lg px-3.5 font-medium text-nav-texte no-underline hover:bg-page hover:text-ink"
      >
        Connexion
      </Link>
    )
  }

  const compte = moi.data
  const lieu = compte.pays ? nomPays(compte.pays) : null
  const role = compte.profil === 'client' ? 'Entreprise' : 'Dev'

  async function sortir() {
    await preparerCsrf()
    await clientLocal.post('/logout').catch(() => undefined)
    await cache.invalidateQueries({ queryKey: ['moi'] })
    setOuvert(false)
    void naviguer('/')
  }

  return (
    <div ref={bloc} className="relative">
      <button
        type="button"
        aria-label="Menu du compte"
        aria-haspopup="menu"
        aria-expanded={ouvert}
        onClick={() => setOuvert((valeur) => !valeur)}
        className="grid size-11 place-items-center rounded-full border-0 bg-accent text-sm font-semibold text-canvas"
      >
        {initiales(compte.nom, compte.username)}
      </button>
      {ouvert ? (
        <div
          role="menu"
          className="absolute top-[calc(100%+8px)] right-0 z-40 flex min-w-[230px] flex-col gap-0.5 rounded-xl border border-line bg-canvas p-1.5 shadow-[0_10px_30px_rgba(15,23,42,0.12)]"
        >
          <div className="mb-1 border-b border-chip px-3 pt-2 pb-2.5">
            <div className="font-bold">{compte.nom || compte.username || 'Compte'}</div>
            <div className="text-[13px] text-muted">
              {role}
              {lieu ? ` · ${lieu}` : ''}
            </div>
          </div>
          {compte.username ? (
            <Link role="menuitem" to={`/devs/${compte.username}`} onClick={() => setOuvert(false)} className="menu-lien">
              Mon profil
            </Link>
          ) : (
            <span className="px-3 py-2 text-sm text-muted">Pseudo GitHub pas encore choisi</span>
          )}
          <Link role="menuitem" to="/tableau" onClick={() => setOuvert(false)} className="menu-lien">
            Tableau de bord
          </Link>
          <Link role="menuitem" to="/copies" onClick={() => setOuvert(false)} className="menu-lien">
            Mes copies
          </Link>
          <Link role="menuitem" to="/publier" onClick={() => setOuvert(false)} className="menu-lien">
            Publier une appli
          </Link>
          <div role="separator" className="mx-1.5 my-1 h-px bg-line" />
          <button type="button" role="menuitem" onClick={() => void sortir()} className="menu-lien sortir">
            Se déconnecter
          </button>
        </div>
      ) : null}
    </div>
  )
}
