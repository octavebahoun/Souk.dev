type AvatarProps = {
  nom: string | null
  username: string | null
  avatarUrl: string | null
}

function initiales(nom: string | null, username: string | null): string {
  const source = (nom || username || '?').trim()
  const morceaux = source.split(/[\s._-]+/).filter(Boolean)
  const lettres = morceaux.length >= 2 ? `${morceaux[0][0]}${morceaux[1][0]}` : source.slice(0, 2)
  return lettres.toUpperCase()
}

export function Avatar({ nom, username, avatarUrl }: AvatarProps) {
  if (avatarUrl) {
    return (
      <img
        src={avatarUrl}
        alt=""
        className="size-6 rounded-full object-cover"
      />
    )
  }

  return (
    <span
      aria-hidden="true"
      className="grid size-6 place-items-center rounded-full bg-nav text-[10px] font-bold text-accent"
    >
      {initiales(nom, username)}
    </span>
  )
}
