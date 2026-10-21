import { Link } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { listerMissions } from './api'
import { lireMoi } from '../echange/api'

export function Missions() {
  const missions = useQuery({ queryKey: ['missions'], queryFn: listerMissions })
  const moi = useQuery({ queryKey: ['moi'], queryFn: lireMoi })
  const liste = missions.data?.data ?? []

  return (
    <main className="mx-auto flex max-w-[1200px] flex-col gap-5 px-4 py-8 min-[761px]:px-6">
      <h1 className="m-0 text-[30px] font-bold">Missions</h1>
      <section className="rounded-[18px] border border-line bg-canvas p-6">
        <p className="m-0 max-w-[62ch] text-subtle">Personnaliser une appli avec son auteur, ou recruter un dev directement. Des missions privées, sans enchères.</p>
        <ul className="m-0 mt-4 grid list-none gap-4 p-0 min-[761px]:grid-cols-2">
          <li>
            <h2 className="m-0 text-lg">Sur mesure</h2>
            <p className="m-0 text-sm text-muted">Faites adapter une appli du Store à votre activité, directement avec son auteur.</p>
          </li>
          <li>
            <h2 className="m-0 text-lg">Sans enchères</h2>
            <p className="m-0 text-sm text-muted">Vous choisissez le dev. Pas d’appel d’offres, pas de course au moins cher.</p>
          </li>
        </ul>
      </section>
      {missions.isPending ? <div role="status" className="squelette h-32 rounded-xl" /> : null}
      {missions.isError ? (
        <div>
          <p role="alert">Connectez-vous pour voir vos missions, ou réessayez.</p>
          <Link to="/connexion" className="inline-flex min-h-cible items-center rounded-controle bg-accent px-4 font-semibold text-canvas no-underline">Connexion</Link>
        </div>
      ) : null}
      {missions.isSuccess && liste.length === 0 ? <p className="rounded-xl border border-line bg-canvas p-8 text-center text-subtle">Aucune mission pour le moment.</p> : null}
      <ul className="m-0 flex list-none flex-col gap-3 p-0">
        {liste.map((mission) => {
          const role = moi.data?.id === mission.client.id ? 'Vous êtes le client' : moi.data?.id === mission.auteur.id ? 'Vous êtes le dev' : null
          return (
            <li key={mission.id} className="rounded-[14px] border border-line bg-canvas p-4">
              <div className="flex flex-wrap items-center gap-2">
                <h2 className="m-0 text-xl">{mission.message}</h2>
                <span className={`rounded-full px-2 py-0.5 text-sm font-semibold ${mission.statut === 'terminee' ? 'bg-[#DCFCE7] text-[#166534]' : 'bg-nav text-accent'}`}>
                  {mission.statut === 'terminee' ? 'Terminée' : 'En cours'}
                </span>
                {role ? <span className="rounded-full bg-chip px-2 py-0.5 text-sm">{role}</span> : null}
              </div>
              <p className="m-0 text-sm text-muted">
                Client {mission.client.nom || mission.client.username} · Dev {mission.auteur.nom || mission.auteur.username}
                {mission.budget != null ? ` · budget ${mission.budget} F` : ''}
                {mission.delai ? ` · délai ${mission.delai}` : ''}
              </p>
              {mission.app_id ? <Link to={`/store/${mission.app_id}`}>Voir l’appli</Link> : null}
            </li>
          )
        })}
      </ul>
    </main>
  )
}
