import { useState, type FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { listerDevs, proposerMissionDev, type DevResume, type Specialite } from './api'
import { lireMoi, messageAction } from '../echange/api'
import { nomPays } from '../echange/texte'
import { Avatar } from '../../shared/ui/Avatar'
import { Modal } from '../../shared/ui/Modal'

const technos = ['laravel', 'php', 'react', 'vue', 'node', 'mysql', 'postgresql'] as const
const specialites: Specialite[] = ['frontend', 'backend', 'mobile', 'devops', 'securite', 'data']

const synonymesBesoin: Record<string, string[]> = {
  api: ['backend', 'node', 'laravel', 'php'],
  money: ['mobile', 'momo'],
  momo: ['mobile', 'flutter', 'momo'],
  paiement: ['mobile', 'flutter', 'momo', 'paiement'],
  paiements: ['mobile', 'flutter', 'momo', 'paiement'],
  site: ['frontend', 'react', 'vue'],
}

function mots(texte: string): string[] {
  return texte
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .match(/[a-z0-9]+/g) ?? []
}

function scoreCorrespondance(dev: DevResume, besoin: string): number {
  const ignores = new Set(['a', 'appli', 'application', 'applications', 'au', 'aux', 'avec', 'de', 'des', 'du', 'en', 'et', 'je', 'la', 'le', 'les', 'ma', 'mon', 'pour', 'un', 'une'])
  const requete = mots(besoin).filter((mot) => !ignores.has(mot))
  if (requete.length === 0) return 0

  const profil = new Set(mots([
    ...dev.competences,
    ...dev.specialites,
    dev.bio ?? '',
  ].join(' ')))
  const correspondances = requete.filter((mot) => {
    const candidats = synonymesBesoin[mot] ?? [mot]
    return candidats.some((candidat) => profil.has(candidat) || [...profil].some((terme) => terme.includes(candidat)))
  })
  return correspondances.length / requete.length
}

const positionsRadar = [
  { x: -29, y: -29 },
  { x: 22, y: -15 },
  { x: 7, y: 27 },
  { x: -37, y: 6 },
  { x: 28, y: 26 },
]

export function Recherche() {
  const [params, setParams] = useSearchParams()
  const [besoin, setBesoin] = useState('')
  const [survolee, setSurvolee] = useState<DevResume | null>(null)
  const moi = useQuery({ queryKey: ['moi'], queryFn: lireMoi })
  const filtres = {
    pays: params.get('pays') ?? '',
    competence: params.get('competence') ?? '',
    specialite: params.get('specialite') ?? '',
    disponible: params.get('disponible') === '1',
  }
  const devs = useQuery({
    queryKey: ['devs', filtres],
    queryFn: () => listerDevs({
      pays: filtres.pays || undefined,
      competence: filtres.competence || undefined,
      specialite: filtres.specialite || undefined,
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
  const radarDevs = [...liste]
  if (besoin.trim()) radarDevs.sort((a, b) => scoreCorrespondance(b, besoin) - scoreCorrespondance(a, besoin))
  const devsRadar = radarDevs.slice(0, positionsRadar.length)
  const [cible, setCible] = useState<DevResume | null>(null)

  return (
    <div className="mx-auto flex max-w-[1440px] flex-col gap-6 px-4 py-8 min-[761px]:px-9">
      <div>
        <h1 className="m-0 text-[30px] font-bold tracking-tight">Trouver un dev</h1>
        <p className="m-0 max-w-[75ch] text-muted">Classés par Souk Score : la qualité et l’activité récente, validées par d’autres. Contacter un dev est gratuit.</p>
      </div>
      <section aria-labelledby="radar-titre" className="grid items-center gap-6 overflow-hidden rounded-[22px] border border-white/80 bg-[linear-gradient(125deg,#f6f4f0_0%,#f1efe9_58%,#f7f4ec_100%)] p-5 shadow-[0_14px_36px_rgba(15,23,42,0.045)] min-[1000px]:grid-cols-[minmax(0,0.82fr)_minmax(0,1.18fr)] min-[1000px]:gap-8 min-[1000px]:p-8">
        <div className="talent-radar relative mx-auto aspect-square w-full max-w-[540px]" aria-label="Radar des talents disponibles">
          <div className="talent-radar-ring talent-radar-ring-outer" />
          <div className="talent-radar-ring talent-radar-ring-middle" />
          <div className="talent-radar-ring talent-radar-ring-inner" />
          <svg aria-hidden="true" viewBox="0 0 500 500" className="absolute inset-0 size-full">
            <path d="M250 250 105 105M250 250 397 183M250 250 368 383M250 250 188 422M250 250 69 292" stroke="#B9C6D8" strokeWidth="1.5" />
            <circle cx="250" cy="250" r="78" fill="#1F4FE0" fillOpacity=".06" />
          </svg>
          <div className="talent-radar-center">Vous</div>
          {devsRadar.map((dev, index) => {
            const couleurs = ['bg-[#E1E8FF] text-[#3949AB]', 'bg-[#FEF3C7] text-[#9A3412]', 'bg-[#EDE9FE] text-[#6D28D9]', 'bg-[#FCE7F3] text-[#BE185D]', 'bg-[#CCFBF1] text-[#0F766E]']
            const score = scoreCorrespondance(dev, besoin)
            const compression = 1 - score * 0.48
            const position = positionsRadar[index]
            return (
              <button
                key={dev.id}
                type="button"
                aria-label={`Proposer une mission à ${dev.nom || dev.username}${besoin.trim() ? `, correspondance ${Math.round(score * 100)} pour cent` : ''}`}
                onMouseEnter={() => setSurvolee(dev)}
                onMouseLeave={() => setSurvolee(null)}
                onFocus={() => setSurvolee(dev)}
                onBlur={() => setSurvolee(null)}
                onClick={() => setCible(dev)}
                style={{ left: `${50 + position.x * compression}%`, top: `${50 + position.y * compression}%` }}
                className={`talent-radar-person absolute grid size-[52px] -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full border-[3px] border-white text-[13px] font-extrabold shadow-[0_10px_20px_rgba(15,23,42,0.14)] ${couleurs[index]}`}
              >
                {initiales(dev.nom, dev.username)}
              </button>
            )
          })}
        </div>
        <div className="flex flex-col gap-3">
          <h2 id="radar-titre" className="m-0 text-[24px] font-bold tracking-tight">Radar des talents</h2>
          <p className="m-0 max-w-[70ch] leading-relaxed text-subtle">Décrivez votre besoin : plus un dev correspond, plus il se rapproche du centre. Survolez une pastille pour le découvrir, cliquez pour lui proposer une mission.</p>
          <label htmlFor="besoin-dev" className="mt-1 text-sm font-semibold">Votre besoin</label>
          <input id="besoin-dev" value={besoin} onChange={(e) => setBesoin(e.target.value)} placeholder="Ex. : paiement MoMo, appli mobile, API" className="min-h-[52px] rounded-controle border border-line bg-white px-3.5" />
          <div className="flex flex-wrap gap-2">
            {['Paiement MoMo', 'Appli mobile', 'Site React', 'Data'].map((item) => (
              <button key={item} type="button" aria-pressed={besoin === item} onClick={() => setBesoin(besoin === item ? '' : item)} className={`min-h-10 rounded-full border px-3.5 text-sm font-semibold transition-colors ${besoin === item ? 'border-accent bg-nav text-accent' : 'border-[#D7DCE5] bg-white/70 text-[#29436F]'}`}>{item}</button>
            ))}
          </div>
          <div aria-live="polite" className="mt-1 min-h-[58px] rounded-[14px] bg-white/75 px-4 py-3 text-sm text-muted">
            {survolee ? (
              <>
                <strong className="text-ink">{survolee.nom || survolee.username || 'Profil développeur'}</strong>
                {survolee.username ? <span> · @{survolee.username}</span> : null}
                {besoin.trim() ? <span> · {Math.round(scoreCorrespondance(survolee, besoin) * 100)} % de correspondance</span> : null}
                <span className="mt-1 block">{survolee.competences.join(' · ') || survolee.specialites.map((item) => libelleSpecialite[item]).join(' · ') || 'Compétences non renseignées'}</span>
                {survolee.username ? <Link to={`/devs/${survolee.username}`} className="mt-1 inline-flex font-semibold text-accent">Voir le profil</Link> : null}
              </>
            ) : (
              <>Au centre : <strong className="text-ink">{moi.data?.nom || moi.data?.username || 'votre profil'}</strong>{moi.data?.pays ? `, ${nomPays(moi.data.pays)}` : ''}. Survolez une pastille pour découvrir le dev.</>
            )}
          </div>
        </div>
      </section>
      <form aria-label="Filtres" className="grid items-end gap-3.5 rounded-carte border border-line bg-canvas p-4 min-[761px]:grid-cols-2 min-[1100px]:grid-cols-5" onSubmit={(e) => e.preventDefault()}>
        <label className="flex flex-col gap-1.5 text-[13.5px] font-semibold">Compétence
          <select className="champ-select" value={filtres.competence} onChange={(e) => changer('competence', e.target.value)}>
            <option value="">Toutes</option>
            {technos.map((techno) => <option key={techno} value={techno}>{techno}</option>)}
          </select>
        </label>
        <label className="flex flex-col gap-1.5 text-[13.5px] font-semibold">Spécialité
          <select className="champ-select" value={filtres.specialite} onChange={(e) => changer('specialite', e.target.value)}>
            <option value="">Toutes</option>
            {specialites.map((item) => <option key={item} value={item}>{libelleSpecialite[item]}</option>)}
          </select>
        </label>
        <label className="flex flex-col gap-1.5 text-[13.5px] font-semibold">Pays
          <select className="champ-select" value={filtres.pays} onChange={(e) => changer('pays', e.target.value)}>
            <option value="">Tous</option>
            {['BJ', 'SN', 'CI', 'TG', 'CM'].map((code) => <option key={code} value={code}>{nomPays(code)}</option>)}
          </select>
        </label>
        <label className="inline-flex min-h-cible items-center gap-2.5 rounded-controle border border-line px-3 font-semibold">
          <input type="checkbox" checked={filtres.disponible} onChange={() => changer('disponible', filtres.disponible ? '' : '1')} className="size-[18px] accent-accent" />
          Disponibles seulement
        </label>
        <button type="button" onClick={() => setParams({})} className="min-h-cible rounded-controle border border-line bg-canvas px-3.5 font-semibold text-nav-texte">Réinitialiser</button>
      </form>
      <div className="flex items-baseline justify-between gap-2">
        <h2 className="m-0 text-[17px]">{devs.isSuccess ? `${liste.length} dev${liste.length > 1 ? 's' : ''}` : 'Devs'}</h2>
        <span className="text-[13.5px] text-muted">Tri : Souk Score décroissant</span>
      </div>
      {devs.isPending ? <div role="status" className="squelette h-40 rounded-carte" /> : null}
      {devs.isError ? (
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-carte border border-line bg-canvas p-6">
          <p role="alert" className="m-0">{messageAction(devs.error, 'Les profils de développeurs n’ont pas pu être chargés.')}</p>
          <button type="button" onClick={() => void devs.refetch()} className="min-h-cible rounded-controle bg-accent px-4 font-semibold text-canvas">Réessayer</button>
        </div>
      ) : null}
      {devs.isSuccess && liste.length === 0 ? (
        <div className="flex flex-col items-center gap-3.5 rounded-carte border border-line bg-canvas p-8 text-center text-subtle">
          <p className="m-0">Aucun dev ne correspond. Retirez un filtre, ou publiez votre besoin comme mission sur une appli du Store.</p>
          <button type="button" onClick={() => setParams({})} className="min-h-cible rounded-controle bg-accent px-[18px] font-semibold text-canvas">Réinitialiser les filtres</button>
        </div>
      ) : null}
      <ul className="m-0 grid list-none gap-4 p-0 min-[761px]:grid-cols-2 min-[1200px]:grid-cols-3">
        {liste.map((dev) => (
          <li key={dev.id} data-reveal-item className="carte-vive flex flex-col gap-3 rounded-carte border border-line bg-canvas p-[18px]">
            <div className="flex items-start gap-3">
              <Avatar nom={dev.nom} username={dev.username} avatarUrl={dev.avatar_url} taille="moyen" />
              <div className="min-w-0 flex-1 leading-snug">
                {dev.username ? <Link to={`/devs/${dev.username}`} className="text-base font-bold text-ink no-underline">{dev.nom || dev.username}</Link> : <strong>{dev.nom || 'Dev'}</strong>}
                <p className="m-0 text-[13px] text-muted">@{dev.username || 'compte'}{dev.pays ? ` · ${nomPays(dev.pays)}` : ''}</p>
              </div>
              <div className="text-right leading-tight">
                <strong className="text-[22px] text-accent tabular-nums">{dev.souk_score.total}</strong>
                <span className="block text-[11.5px] text-muted">Souk Score</span>
              </div>
            </div>
            <div className="flex flex-wrap gap-1.5">
              {dev.disponible ? <span className="rounded-full bg-success-bg px-2 text-[12.5px] font-bold text-success">Disponible</span> : <span className="rounded-full bg-page px-2 text-[12.5px] font-bold text-subtle">Pas disponible</span>}
              <span className="rounded-full bg-[#FFEDD5] px-2 text-[12.5px] font-bold text-[#9A3412]">{libelleNiveau[dev.reputation.niveau]}</span>
              {dev.specialites.map((item) => <span key={item} className="rounded-full bg-nav px-2 text-[12.5px] font-bold text-accent-hover">{libelleSpecialite[item]}</span>)}
            </div>
            <p className="m-0 text-[13.5px] text-nav-texte">{dev.competences.join(' · ') || 'Compétences non renseignées'}</p>
            <p className="m-0 text-[13px] text-muted">{dev.stats.bugs_resolus} bugs résolus · {dev.stats.missions_terminees} missions · {dev.stats.note_moyenne == null ? 'Pas encore noté' : `${dev.stats.note_moyenne} / 5`}</p>
            <div className="mt-auto flex gap-2 pt-1">
              {dev.username ? <Link to={`/devs/${dev.username}`} className="inline-flex min-h-cible flex-1 items-center justify-center rounded-controle border border-line font-semibold text-ink no-underline">Voir le profil</Link> : null}
              <button type="button" disabled={!dev.username} onClick={() => setCible(dev)} className="min-h-cible flex-1 rounded-controle bg-accent font-semibold text-canvas disabled:opacity-50">Proposer une mission</button>
            </div>
          </li>
        ))}
      </ul>
      {cible?.username ? <MissionDev dev={cible} onFerme={() => setCible(null)} /> : null}
    </div>
  )
}

const libelleSpecialite: Record<Specialite, string> = {
  frontend: 'Frontend',
  backend: 'Backend',
  mobile: 'Mobile',
  devops: 'DevOps',
  securite: 'Sécurité',
  data: 'Data',
}

const libelleNiveau = {
  debutant: 'Débutant',
  contributeur: 'Contributeur',
  confirme: 'Confirmé',
  expert: 'Expert',
} as const

function initiales(nom: string | null, username: string | null): string {
  const source = (nom || username || '?').trim()
  const mots = source.split(/[\s._-]+/).filter(Boolean)
  return mots.length > 1 ? `${mots[0][0]}${mots[1][0]}`.toUpperCase() : source.slice(0, 2).toUpperCase()
}

function MissionDev({ dev, onFerme }: { dev: DevResume; onFerme: () => void }) {
  const [erreur, setErreur] = useState<string | null>(null)
  const [ok, setOk] = useState(false)

  async function envoyer(evenement: FormEvent<HTMLFormElement>) {
    evenement.preventDefault()
    if (!dev.username) return
    const donnees = new FormData(evenement.currentTarget)
    const budget = String(donnees.get('budget') ?? '')
    try {
      await proposerMissionDev(dev.username, {
        message: String(donnees.get('message') ?? ''),
        budget: budget ? Number(budget) : undefined,
        delai: String(donnees.get('delai') ?? '') || undefined,
      })
      setOk(true)
    } catch (erreurHttp) {
      setErreur(messageAction(erreurHttp, 'La demande n’a pas été envoyée.'))
    }
  }

  return (
    <Modal titre={`Proposer une mission à ${dev.nom || dev.username}`} onFermer={onFerme}>
      {ok ? (
        <p role="status" className="m-0">Demande envoyée. <Link to="/missions">Voir mes missions</Link></p>
      ) : (
        <form onSubmit={(e) => void envoyer(e)} className="flex flex-col gap-4">
          {dev.disponible ? null : <p role="note" className="m-0 rounded-controle border border-[#FED7AA] bg-warning-bg px-3.5 py-2.5 text-sm text-[#7C2D12]">Ce dev s’est marqué indisponible : il peut quand même répondre.</p>}
          <label className="text-sm font-semibold">Votre besoin <span className="font-medium text-muted">(requis)</span>
            <textarea name="message" required rows={4} className="mt-1.5 w-full rounded-controle border border-line p-3 font-normal" placeholder="Ex. : ajouter le paiement MoMo à ma boutique en ligne" />
          </label>
          <div className="grid gap-3.5 min-[520px]:grid-cols-2">
            <label className="text-sm font-semibold">Budget en FCFA <span className="font-medium text-muted">(facultatif)</span>
              <input name="budget" type="number" min={0} step={1000} inputMode="numeric" className="mt-1.5 min-h-cible w-full rounded-controle border border-line px-3 font-normal" placeholder="Ex. : 150000" />
            </label>
            <label className="text-sm font-semibold">Date souhaitée <span className="font-medium text-muted">(facultatif)</span>
              <input name="delai" type="date" className="mt-1.5 min-h-cible w-full rounded-controle border border-line px-3 font-normal" />
            </label>
          </div>
          <p className="m-0 text-[13.5px] text-subtle">Privé : visible par vous et ce dev. Contacter un dev est gratuit.</p>
          {erreur ? <p role="alert" className="m-0 text-sm text-danger">{erreur}</p> : null}
          <div className="flex justify-end gap-2.5">
            <button type="button" onClick={onFerme} className="min-h-cible rounded-controle border border-line px-[18px] font-semibold">Annuler</button>
            <button type="submit" className="min-h-cible rounded-controle bg-accent px-[18px] font-semibold text-canvas">Envoyer la demande</button>
          </div>
        </form>
      )}
    </Modal>
  )
}
