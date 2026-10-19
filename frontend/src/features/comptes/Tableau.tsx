import { Link } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { isAxiosError } from 'axios'
import { lireMoi } from '../echange/api'
import { lireTableau, type Compte } from './api'

const niveaux: Record<Compte['reputation']['niveau'], string> = {
  debutant: 'Débutant',
  contributeur: 'Contributeur',
  confirme: 'Confirmé',
  expert: 'Expert',
}

function fcfa(valeur: number): string {
  return `${new Intl.NumberFormat('fr-FR').format(valeur)} F`
}

function lettres(nom: string | null, username: string | null): string {
  const source = (nom || username || '?').trim()
  const mots = source.split(/\s+/).filter(Boolean)
  if (mots.length >= 2 && mots[0][0] && mots[1][0]) return `${mots[0][0]}${mots[1][0]}`.toUpperCase()
  return source.slice(0, 2).toUpperCase()
}

export function Tableau() {
  const prism = Boolean(import.meta.env.VITE_API_URL)
  const moi = useQuery({ queryKey: ['moi'], queryFn: lireMoi })
  const tableau = useQuery({
    queryKey: ['tableau'],
    queryFn: lireTableau,
    enabled: Boolean(moi.data) && prism,
  })
  const compte = moi.data

  if (moi.isPending || (compte && prism && tableau.isPending)) {
    return <div role="status" aria-label="Chargement du tableau" className="squelette mx-auto mt-8 h-64 max-w-[1200px] rounded-[18px]" />
  }

  if (!compte) {
    return (
      <div className="reseau bg-page px-4 py-10">
        <div className="mx-auto max-w-[1200px]">
          <h1 className="m-0 text-[30px] font-extrabold tracking-tight">Tableau de bord</h1>
          <p className="m-0 text-muted">Connectez-vous pour voir vos applis.</p>
          <Link to="/connexion" className="inline-flex min-h-cible items-center font-semibold">Connexion</Link>
        </div>
      </div>
    )
  }

  const totaux = tableau.data?.totaux

  return (
    <div className="reseau relative overflow-hidden bg-page">
      <Reseau />
      <div className="relative mx-auto flex max-w-[1200px] flex-col gap-6 px-4 py-7 min-[761px]:px-6">
        <div className="flex flex-wrap items-end justify-between gap-3">
          <div>
            <h1 className="m-0 text-[30px] leading-tight font-extrabold tracking-[-0.015em]">Tableau de bord</h1>
            <p className="m-0 text-muted">Vos applis, leurs visiteurs, leurs copies et ce qu’elles rapportent.</p>
          </div>
          <div className="flex flex-wrap gap-2.5">
            {compte.username ? (
              <Link to={`/devs/${compte.username}`} className="inline-flex min-h-cible items-center rounded-controle border border-line bg-canvas px-4 font-semibold text-ink no-underline">
                Mon profil public
              </Link>
            ) : null}
            <Link to="/publier" className="inline-flex min-h-cible items-center rounded-controle bg-accent px-[18px] font-semibold text-canvas no-underline">
              Publier une appli
            </Link>
          </div>
        </div>

        {compte.username ? (
          <Link
            to={`/devs/${compte.username}`}
            className="flex max-w-[560px] flex-wrap items-center gap-x-5 gap-y-3 rounded-[14px] border border-line bg-canvas px-[18px] py-3.5 text-ink no-underline"
          >
            <span className="grid size-11 place-items-center rounded-full bg-accent text-[15px] font-bold text-canvas">
              {lettres(compte.nom, compte.username)}
            </span>
            <span className="min-w-0 flex-1">
              <span className="block text-[12.5px] font-semibold tracking-wide text-muted uppercase">Mon profil</span>
              <strong className="text-base">{compte.nom || compte.username}</strong>
            </span>
            <span className="text-right">
              <strong className="block text-[22px] text-accent tabular-nums">{compte.souk_score.total}</strong>
              <span className="text-[11.5px] text-muted">Souk Score</span>
            </span>
            <span>
              <span className="inline-flex rounded-full bg-[#FFEDD5] px-2.5 py-0.5 text-[12.5px] font-semibold text-[#9A3412]">
                {niveaux[compte.reputation.niveau]}
              </span>
              <span className="mt-1 block text-xs text-muted tabular-nums">{compte.reputation.xp.toLocaleString('fr-FR')} XP</span>
            </span>
            <span aria-hidden="true" className="text-muted">›</span>
          </Link>
        ) : null}

        <div role="note" className="flex items-start gap-2.5 rounded-xl border border-[#FED7AA] bg-[#FFF7ED] px-4 py-3 text-sm text-[#7C2D12]">
          <span aria-hidden="true" className="mt-0.5">ⓘ</span>
          <span>
            <strong>Revenus estimés</strong>, pas encore encaissés : ils sont calculés à partir des copies et des prix, tant que le paiement via Datacloud n’est pas en place.
          </span>
        </div>

        {!prism ? (
          <p role="status" className="m-0 rounded-[14px] border border-line bg-canvas p-4 text-sm">
            Les totaux ne sont pas encore sur Laravel. Lance Prism et remplis VITE_API_URL pour les voir.
          </p>
        ) : null}

        {tableau.isError ? (
          <div className="flex flex-col gap-2">
            <p role="alert" className="m-0">
              {isAxiosError(tableau.error) && tableau.error.response?.status === 401
                ? 'Connectez-vous pour voir votre tableau de bord.'
                : 'Le tableau n’a pas pu être chargé.'}
            </p>
            <button type="button" onClick={() => tableau.refetch()} className="min-h-cible w-fit rounded-controle bg-accent px-4 font-semibold text-canvas">
              Réessayer
            </button>
          </div>
        ) : null}

        {totaux ? (
          <section aria-label="Totaux" className="grid gap-3.5 min-[761px]:grid-cols-2 min-[1100px]:grid-cols-4">
            <CarteBleue titre="Vues" valeur={totaux.vues.toLocaleString('fr-FR')} detail="un visiteur compté une fois par jour" />
            <CarteBleue titre="Copies actives" valeur={String(totaux.copies_actives)} detail="en ligne chez des clients" />
            <CarteBleue titre="Revenu mensuel estimé" valeur={`${fcfa(totaux.revenus_estimes_mensuels)}/mois`} detail="copies actives × prix" />
            <CarteBleue titre="Ventes uniques estimées" valeur={fcfa(totaux.revenus_estimes_uniques)} detail="déploiements × prix, au total" />
          </section>
        ) : null}

        {tableau.data ? (
          <section className="overflow-hidden rounded-[14px] border border-line bg-canvas">
            <h2 className="m-0 px-5 pt-[18px] pb-2 text-[19px]">Par appli</h2>
            {tableau.data.applis.length === 0 ? <p className="px-5 text-muted">Aucune appli publiée.</p> : null}
            <div className="overflow-x-auto">
              <table className="w-full border-collapse text-left text-[14.5px]">
                <thead>
                  <tr className="text-[12.5px] font-bold tracking-wide text-subtle uppercase">
                    <th className="px-4 py-2.5 text-left">Appli</th>
                    <th className="px-4 py-2.5 text-right">Vues</th>
                    <th className="px-4 py-2.5 text-right">Copies actives</th>
                    <th className="px-4 py-2.5 text-right">Déploiements</th>
                    <th className="px-4 py-2.5 text-right">Revenu estimé</th>
                  </tr>
                </thead>
                <tbody>
                  {tableau.data.applis.map((appli) => (
                    <tr key={appli.app_id} className="border-t border-[#EEF2F9]">
                      <td className="px-4 py-3.5">
                        <div className="font-semibold">{appli.nom}</div>
                        <div className="text-[13px] text-muted">
                          {appli.prix === 0 ? 'Gratuit' : `${fcfa(appli.prix)}${appli.type_prix === 'mensuel' ? '/mois' : ' · paiement unique'}`}
                        </div>
                      </td>
                      <td className="px-4 py-3.5 text-right tabular-nums">{appli.vues.toLocaleString('fr-FR')}</td>
                      <td className="px-4 py-3.5 text-right tabular-nums">{appli.copies_actives}</td>
                      <td className="px-4 py-3.5 text-right tabular-nums">{appli.deploiements_total}</td>
                      <td className="px-4 py-3.5 text-right font-bold tabular-nums">
                        {fcfa(appli.revenu_estime)}
                        {appli.type_prix === 'mensuel' ? '/mois' : ' au total'}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
        ) : null}
      </div>
    </div>
  )
}

function CarteBleue({ titre, valeur, detail }: { titre: string; valeur: string; detail: string }) {
  return (
    <article className="carte-bleue flex min-h-[148px] flex-col justify-end gap-1 rounded-[18px] px-5 py-5 text-canvas shadow-[0_12px_28px_rgba(31,79,224,0.18)]">
      <span className="text-[13.5px] text-white/80">{titre}</span>
      <strong className="text-[30px] leading-none tracking-tight">{valeur}</strong>
      <span className="text-[12.5px] text-white/75">{detail}</span>
    </article>
  )
}

function Reseau() {
  return (
    <svg aria-hidden="true" className="pointer-events-none absolute bottom-0 left-0 w-full text-accent/30" viewBox="0 0 1200 420" fill="none">
      <g stroke="currentColor" strokeWidth="1.2">
        <path d="M80 360 220 250 360 300 520 160 700 240 860 120 1040 220" />
        <path d="M220 250 300 140M520 160 480 80M700 240 760 320M860 120 980 80" />
      </g>
      <g fill="currentColor">
        <circle cx="80" cy="360" r="4" />
        <circle cx="220" cy="250" r="5" />
        <circle cx="360" cy="300" r="4" />
        <circle cx="520" cy="160" r="6" />
        <circle cx="700" cy="240" r="4" />
        <circle cx="860" cy="120" r="5" />
        <circle cx="1040" cy="220" r="4" />
      </g>
      <circle cx="180" cy="300" r="54" fill="#cbd5e1" opacity="0.55" />
    </svg>
  )
}
