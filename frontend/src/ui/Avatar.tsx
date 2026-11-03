import { useState } from 'react'
import { cx } from './cx'
import { initiales } from './format'

const tailles = {
  xs: 'size-6 text-micro',
  sm: 'size-8 text-micro',
  md: 'size-10 text-petit',
  lg: 'size-14 text-corps-l',
  xl: 'size-20 text-titre-2',
}

// Teintes de la palette pour les initiales, choisies à partir du nom (stables d'une page à l'autre).
const teintes = [
  'bg-primaire-doux text-sur-primaire-doux',
  'bg-fond-succes text-succes',
  'bg-fond-ia text-ia',
  'bg-fond-alerte text-alerte',
  'bg-fond-info text-info',
]


function teinte(cle: string): string {
  let somme = 0
  for (const caractere of cle) somme = (somme * 31 + caractere.charCodeAt(0)) >>> 0
  return teintes[somme % teintes.length]
}

type AvatarProps = {
  nom: string | null | undefined
  secours?: string | null
  url?: string | null
  taille?: keyof typeof tailles
  className?: string
}

/** Photo de profil, ou initiales sur une teinte de la palette. Décoratif : le nom est écrit à côté. */
export function Avatar({ nom, secours, url, taille = 'md', className }: AvatarProps) {
  const [rate, setRate] = useState<string | null>(null)
  const classe = cx('inline-grid shrink-0 place-items-center overflow-hidden rounded-pastille font-semibold', tailles[taille], className)

  if (url && rate !== url) {
    return <img src={url} alt="" loading="lazy" decoding="async" onError={() => setRate(url)} className={cx(classe, 'object-cover')} />
  }

  return (
    <span aria-hidden="true" className={cx(classe, teinte(nom || secours || '?'))}>
      {initiales(nom, secours)}
    </span>
  )
}
