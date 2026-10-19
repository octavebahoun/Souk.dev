import { useState } from 'react'

type AvatarProps = {
  nom: string | null
  username: string | null
  avatarUrl: string | null
  taille?: 'petit' | 'moyen'
}

function initiales(nom: string | null, username: string | null): string {
  const source = (nom || username || '?').trim()
  const morceaux = source.split(/[\s._-]+/).filter(Boolean)
  const lettres = morceaux.length >= 2 ? `${morceaux[0][0]}${morceaux[1][0]}` : source.slice(0, 2)
  return lettres.toUpperCase()
}

function Pastille({ nom, username, taille }: AvatarProps) {
  const mesure = taille === 'moyen' ? 'size-9 text-xs' : 'size-6 text-[9.5px]'
  return (
    <span aria-hidden="true" className={`grid ${mesure} shrink-0 place-items-center rounded-full bg-nav font-bold text-accent`}>
      {initiales(nom, username)}
    </span>
  )
}

export function Avatar({ nom, username, avatarUrl, taille = 'petit' }: AvatarProps) {
  const [suivi, setSuivi] = useState({ url: avatarUrl, rate: false })
  if (suivi.url !== avatarUrl) setSuivi({ url: avatarUrl, rate: false })

  const mesure = taille === 'moyen' ? 'size-9' : 'size-6'
  if (!avatarUrl || suivi.rate) return <Pastille nom={nom} username={username} avatarUrl={null} taille={taille} />

  return (
    <img
      src={avatarUrl}
      alt=""
      className={`${mesure} shrink-0 rounded-full object-cover`}
      onError={() => setSuivi({ url: avatarUrl, rate: true })}
    />
  )
}
