import { Link, useSearchParams } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { listerDevs } from './api'
import { nomPays } from '../echange/texte'
import { Avatar } from '../../shared/ui/Avatar'

const technos = ['laravel', 'php', 'react', 'vue', 'node', 'mysql', 'postgresql']

export function Recherche() {
  const [params, setParams] = useSearchParams()
  const filtres = {
    pays: params.get('pays') ?? '',
    competence: params.get('competence') ?? '',
    disponible: params.get('disponible') === '1',
  }
  const devs = useQuery({
    queryKey: ['devs', filtres],
    queryFn: () => listerDevs({
      pays: filtres.pays || undefined,
      competence: filtres.competence || undefined,
      disponible: filtres.disponible || undefined,
    }),
  })
  function changer(cle: string, valeur: string) {
    const suivant = new URLSearchParams(params)
    if (valeur) suivant.set(cle, valeur)
    else suivant.delete(cle)
    setParams(suivant)
  }

  const liste = devs.data?.data ?? []

  return (
    <main className="mx-auto flex max-w-[1200px] flex-col gap-5 px-4 py-8 min-[761px]:px-6">
      <h1 className="m-0 text-[30px] font-bold">Trouver un dev</h1>
      <p className="m-0 text-muted">Classés par Souk Score. Les filtres suivent le contrat : pays, techno, disponibilité.</p>
      <div className="flex flex-wrap gap-3">
        <select aria-label="Pays" className="champ-select" value={filtres.pays} onChange={(e) => changer('pays', e.target.value)}>
          <option value="">Tous les pays</option>
          {['BJ', 'CM', 'CI', 'SN', 'TG'].map((code) => <option key={code} value={code}>{nomPays(code)}</option>)}
        </select>
        <select aria-label="Compétence" className="champ-select" value={filtres.competence} onChange={(e) => changer('competence', e.target.value)}>
          <option value="">Toutes les technos</option>
          {technos.map((techno) => <option key={techno} value={techno}>{techno}</option>)}
        </select>
        <button type="button" aria-pressed={filtres.disponible} onClick={() => changer('disponible', filtres.disponible ? '' : '1')} className="min-h-cible rounded-controle border border-line bg-canvas px-3 font-semibold">
          {filtres.disponible ? 'Disponibles seulement' : 'Tous les devs'}
        </button>
      </div>
      {devs.isPending ? <div role="status" className="squelette h-40 rounded-xl" /> : null}
      {devs.isError ? <p role="alert">La liste des devs n’est pas encore servie par Laravel. Branchez Prism, ou réessayez.</p> : null}
      {devs.isSuccess && liste.length === 0 ? (
        <div className="rounded-xl border border-dashed border-line p-8 text-center">
          <p>Aucun dev ne correspond.</p>
          <button type="button" onClick={() => setParams({})} className="min-h-cible rounded-controle bg-accent px-4 font-semibold text-canvas">Réinitialiser les filtres</button>
        </div>
      ) : null}
      <ul className="m-0 grid list-none gap-3 p-0 min-[761px]:grid-cols-2">
        {liste.map((dev) => (
          <li key={dev.id} className="flex flex-col gap-3 rounded-[14px] border border-line bg-canvas p-4">
            <div className="flex items-center gap-3">
              <Avatar nom={dev.nom} username={dev.username} avatarUrl={dev.avatar_url} taille="moyen" />
              <div>
                <Link to={`/devs/${dev.username}`} className="font-bold no-underline">{dev.nom || dev.username}</Link>
                <p className="m-0 text-sm text-muted">{dev.pays ? nomPays(dev.pays) : ''} · score {dev.souk_score.total}</p>
              </div>
            </div>
            <p className="m-0 line-clamp-2 text-sm text-subtle">{dev.bio || 'Pas encore de bio.'}</p>
            <div className="flex flex-wrap gap-1">
              {dev.competences.slice(0, 4).map((techno) => <span key={techno} className="rounded-full bg-chip px-2 text-sm">{techno}</span>)}
            </div>
            {dev.username ? (
              <Link to={`/devs/${dev.username}`} className="inline-flex min-h-cible w-fit items-center rounded-controle bg-ink px-4 font-semibold text-canvas no-underline">Proposer une mission</Link>
            ) : null}
          </li>
        ))}
      </ul>
    </main>
  )
}
