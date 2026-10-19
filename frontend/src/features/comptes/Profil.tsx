import { useState, type FormEvent } from 'react'
import { Link, useParams } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { lireMoi, messageAction } from '../echange/api'
import { nomPays } from '../echange/texte'
import { lireProfil, proposerMission, type ProfilDev } from './api'

const roles: Record<ProfilDev['specialites'][number], string> = {
  frontend: 'Frontend',
  backend: 'Backend',
  mobile: 'Mobile',
  devops: 'Devops',
  securite: 'Sécurité',
  data: 'Data',
}

const niveaux: Record<ProfilDev['reputation']['niveau'], string> = {
  debutant: 'Débutant',
  contributeur: 'Contributeur',
  confirme: 'Confirmé',
  expert: 'Expert',
}

const partsScore = [
  { cle: 'entraide', nom: 'Entraide', max: 250 },
  { cle: 'projets', nom: 'Projets', max: 200 },
  { cle: 'clients', nom: 'Clients', max: 250 },
  { cle: 'securite', nom: 'Sécurité', max: 150 },
  { cle: 'activite', nom: 'Activité', max: 150 },
] as const

const badges = [
  { id: 'premier_correctif', nom: 'Premier correctif', regle: '1 correctif accepté' },
  { id: 'chasseur_de_bugs', nom: 'Chasseur de bugs', regle: '10 bugs résolus' },
  { id: 'publie', nom: 'Publié', regle: '1 appli publiée' },
  { id: 'fiable', nom: 'Fiable', regle: 'Note ≥ 4,5' },
  { id: 'securise', nom: 'Sécurisé', regle: '1 appli vérifiée' },
  { id: 'organisateur', nom: 'Organisateur', regle: '1 événement passé' },
] as const

const places = [
  'top-[6%] left-1/2 -translate-x-1/2',
  'top-[22%] right-0',
  'bottom-[18%] right-[6%]',
  'bottom-[4%] left-1/2 -translate-x-1/2',
  'top-[28%] left-0',
  'top-[58%] left-[2%]',
]

function fcfa(valeur: number): string {
  return `${new Intl.NumberFormat('fr-FR').format(valeur)} F`
}

function initiales(nom: string | null, username: string | null): string {
  const source = (nom || username || '?').trim()
  const mots = source.split(/[\s._-]+/).filter(Boolean)
  if (mots.length >= 2) return `${mots[0][0]}${mots[1][0]}`.toUpperCase()
  return source.slice(0, 2).toUpperCase()
}

export function Profil() {
  const { username = '' } = useParams()
  const profil = useQuery({
    queryKey: ['profil', username],
    queryFn: () => lireProfil(username),
    enabled: username.length > 0,
  })
  const moi = useQuery({ queryKey: ['moi'], queryFn: lireMoi })

  if (profil.isPending) {
    return <div role="status" aria-label="Chargement du profil" className="squelette mx-auto mt-8 h-80 max-w-[1200px] rounded-[18px]" />
  }

  if (profil.isError || !profil.data) {
    return (
      <div className="mx-auto flex max-w-[1200px] flex-col gap-3 px-4 py-10">
        <p role="alert" className="m-0">Ce profil est introuvable, ou le chargement a échoué.</p>
        <button type="button" onClick={() => profil.refetch()} className="min-h-cible w-fit rounded-controle bg-accent px-4 font-semibold text-canvas">
          Réessayer
        </button>
      </div>
    )
  }

  const dev = profil.data
  const etoiles = dev.depots.reduce((somme, depot) => somme + depot.etoiles, 0)
  const note = dev.stats.note_moyenne
  const moiMeme = moi.data?.id === dev.id

  return (
    <div className="reseau bg-page">
      <div className="mx-auto flex max-w-[1200px] flex-col gap-6 px-4 py-7 min-[761px]:px-6">
        <section className="relative overflow-hidden rounded-[18px] border border-white/80 bg-[linear-gradient(135deg,#ffffff_42%,#f3efe4_100%)] p-6 shadow-[0_10px_30px_rgba(15,23,42,0.04)] min-[861px]:p-8">
          <div className="grid items-center gap-8 min-[861px]:grid-cols-[minmax(0,1fr)_340px]">
            <div className="flex flex-col gap-3">
              <span className="inline-flex w-fit items-center gap-2 rounded-full bg-[#DCFCE7] px-2.5 py-1 text-[11px] font-bold tracking-wide text-[#166534]">
                <span className="size-1.5 rounded-full bg-[#16A34A]" />
                BONJOUR, JE SUIS
              </span>
              <h1 className="m-0 text-4xl font-extrabold tracking-[-0.03em] min-[861px]:text-5xl">
                {dev.nom || dev.username || 'Dev'}
              </h1>
              <p className="m-0 text-lg text-ink">
                Dev{' '}
                <span className="font-bold text-accent">
                  {dev.specialites.length > 0 ? dev.specialites.map((item) => roles[item].toLowerCase()).join(' & ') : 'sur Souk.dev'}
                </span>
              </p>
              <p className="m-0 max-w-[54ch] text-subtle">{dev.bio || 'Pas encore de bio.'}</p>
              <p className="m-0 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-nav-texte">
                <span className="inline-flex items-center gap-1.5">
                  <Pin />
                  {dev.pays ? nomPays(dev.pays) : 'Pays non renseigné'}
                </span>
                <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[13px] font-semibold ${dev.disponible ? 'bg-[#DCFCE7] text-[#166534]' : 'bg-chip text-subtle'}`}>
                  <span className={`size-2 rounded-full ${dev.disponible ? 'bg-[#16A34A]' : 'bg-nav-doux'}`} />
                  {dev.disponible ? 'Disponible pour une mission' : 'Pas disponible'}
                </span>
              </p>
              <div className="mt-1 flex flex-wrap gap-2">
                {moiMeme ? (
                  <Link to="/tableau" className="inline-flex min-h-cible items-center rounded-controle bg-ink px-4 font-semibold text-canvas no-underline">
                    Mon tableau de bord
                  </Link>
                ) : (
                  <DemandeMission username={username} nom={dev.nom || dev.username || 'ce dev'} />
                )}
                {dev.github_url ? (
                  <a href={dev.github_url} className="inline-flex min-h-cible items-center gap-2 rounded-controle border border-line bg-canvas px-4 font-semibold text-ink no-underline">
                    <LienExterne />
                    Voir mon GitHub
                  </a>
                ) : null}
              </div>
              {!moiMeme ? <p className="m-0 text-[12.5px] text-muted">Contacter un dev est gratuit.</p> : null}
            </div>
            <Orbite dev={dev} />
          </div>
        </section>

        <section className="grid gap-4 min-[861px]:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
          <article className="rounded-[18px] border border-line bg-canvas p-6">
            <h2 className="m-0 text-xl font-bold">À propos</h2>
            <p className="m-0 mt-3 max-w-[58ch] text-subtle">{dev.bio || 'Ce dev n’a pas encore écrit de présentation.'}</p>
            <div className="mt-4 flex flex-wrap gap-2">
              {dev.specialites.map((item) => (
                <span key={item} className="rounded-full bg-nav px-2.5 py-0.5 text-[13px] font-semibold text-accent">
                  {roles[item]}
                </span>
              ))}
            </div>
          </article>
          <div className="grid grid-cols-2 gap-3">
            <Info titre="Nom" valeur={dev.nom || '—'} />
            <Info titre="Pays" valeur={dev.pays ? nomPays(dev.pays) : '—'} />
            <Info titre="Disponible" valeur={dev.disponible ? 'Libre pour une mission' : 'Pas en ce moment'} />
            <Info titre="GitHub" valeur={dev.username ? `@${dev.username}` : '—'} />
          </div>
        </section>

        <section aria-label="Statistiques" className="grid gap-3 min-[761px]:grid-cols-5">
          <Chiffre valeur={String(dev.stats.bugs_resolus)} legende="bugs résolus" />
          <Chiffre valeur={String(dev.stats.applis_publiees)} legende="appli publiée" />
          <Chiffre valeur={String(dev.stats.missions_terminees)} legende="missions terminées" />
          <Chiffre valeur={note === null ? '—' : note.toLocaleString('fr-FR')} legende={note === null ? 'pas encore de note' : `note des clients · ${dev.stats.nb_notes} notes`} />
          <Chiffre valeur={String(etoiles)} legende="étoiles GitHub" />
        </section>

        <section className="grid gap-4 min-[861px]:grid-cols-2">
          <article className="rounded-[18px] border border-line bg-canvas p-6">
            <div className="flex items-baseline justify-between gap-3">
              <h2 className="m-0 text-xl font-bold">Souk Score</h2>
              <span className="text-sm text-muted">sur 1000</span>
            </div>
            <p className="m-0 mt-2 text-5xl font-extrabold tracking-tight text-accent">{dev.souk_score.total}</p>
            <ul className="m-0 mt-4 flex list-none flex-col gap-3 p-0">
              {partsScore.map((part) => {
                const valeur = dev.souk_score[part.cle]
                const largeur = Math.min(100, Math.round((valeur / part.max) * 100))
                return (
                  <li key={part.cle}>
                    <div className="mb-1 flex justify-between text-sm">
                      <span className="font-semibold">{part.nom}</span>
                      <span className="text-nav-texte">{valeur} / {part.max}</span>
                    </div>
                    <div className="h-2 overflow-hidden rounded-full bg-chip">
                      <div className="h-full rounded-full bg-accent" style={{ width: `${largeur}%` }} />
                    </div>
                  </li>
                )
              })}
            </ul>
          </article>
          <article className="rounded-[18px] border border-line bg-canvas p-6">
            <h2 className="m-0 text-xl font-bold">Réputation</h2>
            <div className="mt-3 flex flex-wrap items-center gap-3">
              <span className="rounded-full bg-[#FFEDD5] px-3 py-1 text-sm font-semibold text-[#9A3412]">{niveaux[dev.reputation.niveau]}</span>
              <strong className="text-[22px]">{dev.reputation.xp.toLocaleString('fr-FR')} XP</strong>
            </div>
            <ul className="m-0 mt-4 grid list-none grid-cols-2 gap-2 p-0">
              {badges.map((badge) => {
                const obtenu = dev.reputation.badges.includes(badge.id)
                return (
                  <li key={badge.id} className={`rounded-controle border px-3 py-2 ${obtenu ? 'border-line bg-canvas' : 'border-transparent bg-page text-muted'}`}>
                    <div className="text-sm font-semibold">{badge.nom}</div>
                    <div className="text-xs text-muted">{badge.regle}</div>
                  </li>
                )
              })}
            </ul>
          </article>
        </section>

        <section className="flex flex-col gap-3">
          <div className="flex items-baseline justify-between">
            <h2 className="m-0 text-xl font-bold">Projets phares</h2>
            <Link to="/store" className="text-sm font-semibold">Voir le Store →</Link>
          </div>
          {dev.applis.length === 0 ? <p className="m-0 text-muted">Aucune appli publiée.</p> : null}
          <ul className="m-0 grid list-none gap-3 p-0">
            {dev.applis.map((appli) => (
              <li key={appli.id} className="flex flex-wrap items-center gap-4 rounded-[18px] border border-line bg-canvas p-4">
                <Fenetre />
                <div className="min-w-0 flex-1">
                  <p className="m-0 text-xs font-semibold text-[#16A34A]">Publiée sur le Store</p>
                  <h3 className="m-0 text-lg font-bold">{appli.nom}</h3>
                  <p className="m-0 text-sm text-subtle">{appli.description}</p>
                  <p className="m-0 mt-1 font-bold">
                    {appli.prix === 0 ? 'Gratuit' : `${fcfa(appli.prix)}${appli.type_prix === 'mensuel' ? '/mois' : ''}`}
                  </p>
                </div>
              </li>
            ))}
          </ul>
        </section>

        <section className="flex flex-col gap-3">
          <h2 className="m-0 text-xl font-bold">Dépôts GitHub publics</h2>
          {dev.depots.length === 0 ? <p className="m-0 text-muted">Aucun dépôt public.</p> : null}
          <ul className="m-0 grid list-none gap-3 p-0 min-[761px]:grid-cols-2">
            {dev.depots.map((depot) => (
              <li key={depot.url} className="rounded-[18px] border border-line bg-canvas p-4">
                <a href={depot.url} className="font-bold">{depot.nom}</a>
                {depot.description ? <p className="m-0 text-sm text-subtle">{depot.description}</p> : null}
                <p className="m-0 text-sm text-muted">
                  {depot.langage ? `${depot.langage} · ` : ''}★ {depot.etoiles}
                </p>
              </li>
            ))}
          </ul>
        </section>
      </div>
    </div>
  )
}

function Orbite({ dev }: { dev: ProfilDev }) {
  return (
    <div className="relative mx-auto h-[300px] w-full max-w-[340px]">
      <div aria-hidden="true" className="absolute inset-6 rounded-full border border-dashed border-[#D7DEEA]" />
      <div className="absolute top-1/2 left-1/2 grid size-[168px] -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full border-4 border-white bg-[#FEF3C7] text-4xl font-extrabold text-[#92400E] shadow-[0_8px_24px_rgba(15,23,42,0.08)]">
        {initiales(dev.nom, dev.username)}
      </div>
      {dev.competences.slice(0, 6).map((item, index) => (
        <span key={item} className={`absolute rounded-full border border-line bg-canvas px-3 py-1 text-sm font-semibold shadow-sm ${places[index]}`}>
          {item}
        </span>
      ))}
    </div>
  )
}

function DemandeMission({ username, nom }: { username: string; nom: string }) {
  const [ouvert, setOuvert] = useState(false)
  const [message, setMessage] = useState('')
  const [budget, setBudget] = useState('')
  const [delai, setDelai] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)
  const [envoye, setEnvoye] = useState(false)
  const [envoi, setEnvoi] = useState(false)

  async function envoyer(evenement: FormEvent) {
    evenement.preventDefault()
    if (message.trim().length < 3) {
      setErreur('Décrivez votre besoin en quelques mots.')
      return
    }
    setEnvoi(true)
    setErreur(null)
    try {
      await proposerMission(username, {
        message: message.trim(),
        budget: budget ? Number(budget) : undefined,
        delai: delai || undefined,
      })
      setEnvoye(true)
      setOuvert(false)
    } catch (erreurHttp) {
      setErreur(messageAction(erreurHttp, '10 missions par heure. Réessayez plus tard.'))
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <>
      <button type="button" onClick={() => setOuvert(true)} className="inline-flex min-h-cible items-center gap-2 rounded-controle bg-ink px-4 font-semibold text-canvas">
        Proposer une mission
        <span aria-hidden="true">→</span>
      </button>
      {envoye ? <span className="self-center text-sm font-semibold text-[#166534]">Demande envoyée.</span> : null}
      {ouvert ? (
        <div className="fixed inset-0 z-[60] flex items-center justify-center bg-ink/50 p-4">
          <form role="dialog" aria-modal="true" aria-labelledby="titre-mission" onSubmit={(evenement) => void envoyer(evenement)} className="flex w-full max-w-[520px] flex-col gap-4 rounded-2xl bg-canvas p-6">
            <h2 id="titre-mission" className="m-0 text-xl">Proposer une mission à {nom}</h2>
            <label className="flex flex-col gap-1 text-sm font-semibold">
              Votre besoin
              <textarea value={message} onChange={(evenement) => setMessage(evenement.target.value)} rows={4} className="rounded-controle border border-line p-3 font-normal" />
            </label>
            <div className="grid gap-3 min-[761px]:grid-cols-2">
              <label className="flex flex-col gap-1 text-sm font-semibold">
                Budget (facultatif)
                <input value={budget} inputMode="numeric" onChange={(evenement) => setBudget(evenement.target.value.replace(/\D/g, ''))} className="min-h-cible rounded-controle border border-line px-3 font-normal" placeholder="150000" />
              </label>
              <label className="flex flex-col gap-1 text-sm font-semibold">
                Date souhaitée
                <input type="date" value={delai} onChange={(evenement) => setDelai(evenement.target.value)} className="min-h-cible rounded-controle border border-line px-3 font-normal" />
              </label>
            </div>
            {erreur ? <p role="alert" className="m-0 text-sm text-danger">{erreur}</p> : null}
            <div className="flex justify-end gap-2">
              <button type="button" onClick={() => setOuvert(false)} className="min-h-cible rounded-controle border border-line bg-canvas px-4 font-semibold">Annuler</button>
              <button type="submit" disabled={envoi} className="min-h-cible rounded-controle bg-accent px-4 font-semibold text-canvas">Envoyer la demande</button>
            </div>
          </form>
        </div>
      ) : null}
    </>
  )
}

function Info({ titre, valeur }: { titre: string; valeur: string }) {
  return (
    <article className="rounded-[16px] border border-line bg-canvas p-4">
      <p className="m-0 text-xs text-muted">{titre}</p>
      <p className="m-0 font-bold break-all">{valeur}</p>
    </article>
  )
}

function Chiffre({ valeur, legende }: { valeur: string; legende: string }) {
  return (
    <article className="rounded-[16px] border border-line bg-canvas px-4 py-4">
      <p className="m-0 text-[26px] font-extrabold tracking-tight">{valeur}</p>
      <p className="m-0 text-[13.5px] text-muted">{legende}</p>
    </article>
  )
}

function Fenetre() {
  return (
    <span aria-hidden="true" className="flex h-[92px] w-[150px] flex-col overflow-hidden rounded-xl border border-[#E7D7B8] bg-[#FEF3C7] p-2">
      <span className="h-3 rounded-t bg-[#92400E]" />
      <span className="mt-1.5 grid flex-1 grid-cols-3 gap-1">
        <span className="rounded bg-[#FDE68A]" />
        <span className="rounded bg-[#FDE68A]" />
        <span className="rounded bg-[#FDE68A]" />
      </span>
    </span>
  )
}

function Pin() {
  return (
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
      <path d="M12 22s7-6.2 7-12a7 7 0 0 0-14 0c0 5.8 7 12 7 12z" />
      <circle cx="12" cy="10" r="2.5" />
    </svg>
  )
}

function LienExterne() {
  return (
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true">
      <path d="M14 4h6v6M20 4l-9 9" />
      <path d="M18 13v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h6" />
    </svg>
  )
}
