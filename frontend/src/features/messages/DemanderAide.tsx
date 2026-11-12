import { useState } from 'react'
import { Link } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { UserPlus } from 'lucide-react'
import { Avatar, Bouton, Squelette } from '../../ui'
import { listerDevs } from '../catalogue/api'
import { InviterDev } from './InviterDev'

type DemanderAideProps = {
  discussion: { id: number; titre: string }
  /** Techno du bug : les devs qui la maîtrisent sont proposés en premier. */
  techno?: string | null
  moiId: number | null
}

/**
 * Pour l'auteur d'un bug : plutôt que d'attendre que quelqu'un passe, demander de l'aide à un dev
 * précis. L'invitation part avec le bug joint ; le dev accepte ou décline.
 */
export function DemanderAide({ discussion, techno, moiId }: DemanderAideProps) {
  const [cible, setCible] = useState<{ username: string; nom: string } | null>(null)
  const [ouvertures, setOuvertures] = useState(0)
  const devs = useQuery({
    queryKey: ['devs', 'aide', techno ?? 'tous'],
    queryFn: () => listerDevs(techno ? { competence: techno, disponible: 'true' } : { disponible: 'true' }),
    staleTime: 60_000,
  })
  const proposes = (devs.data?.data ?? []).filter((dev) => dev.id !== moiId && dev.username).slice(0, 3)

  return (
    <section aria-labelledby="titre-aide" className="flex flex-col gap-3 rounded-moyen border border-bordure bg-surface p-4">
      <div className="flex flex-col gap-1">
        <h3 id="titre-aide" className="m-0 flex items-center gap-2 text-corps font-semibold text-titre">
          <UserPlus aria-hidden="true" className="size-4 text-sur-primaire-doux" />
          Demander de l’aide à un dev
        </h3>
        <p className="m-0 text-petit text-texte-doux">
          {techno ? `Des devs disponibles qui connaissent ${techno}.` : 'Des devs disponibles en ce moment.'} Votre bug est joint à l’invitation ; chacun accepte ou décline.
        </p>
      </div>

      {devs.isPending ? <Squelette className="h-12 rounded-moyen" /> : null}
      {devs.isSuccess && proposes.length === 0 ? (
        <p className="m-0 text-petit text-texte-doux">
          Personne à proposer pour l’instant. <Link to="/devs">Chercher un dev</Link>
        </p>
      ) : null}

      <ul className="m-0 flex list-none flex-col gap-2 p-0">
        {proposes.map((dev) => {
          const nom = dev.nom || dev.username || 'Dev'
          return (
            <li key={dev.id} className="flex items-center gap-2.5">
              <Avatar nom={nom} url={dev.avatar_url} taille="sm" />
              <Link to={`/devs/${dev.username}`} className="min-w-0 flex-1 truncate text-petit font-medium text-titre no-underline hover:underline">
                {nom}
              </Link>
              <Bouton
                variante="contour"
                taille="sm"
                onClick={() => {
                  setOuvertures((nombre) => nombre + 1)
                  setCible({ username: dev.username ?? '', nom })
                }}
              >
                Demander son aide
              </Bouton>
            </li>
          )
        })}
      </ul>
      {proposes.length > 0 ? (
        <Link to={techno ? `/devs?competence=${encodeURIComponent(techno)}` : '/devs'} className="text-petit font-medium">
          Voir d’autres devs
        </Link>
      ) : null}

      {cible ? <InviterDev key={ouvertures} ouverte onFermer={() => setCible(null)} username={cible.username} nom={cible.nom} discussion={discussion} /> : null}
    </section>
  )
}
