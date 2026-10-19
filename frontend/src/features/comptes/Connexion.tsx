import { useState, type FormEvent, type ReactNode } from 'react'
import { Link } from 'react-router'
import { isAxiosError } from 'axios'
import { demanderLien } from './api'
import { lireErreurHttp } from '../echange/api'

type ProfilChoix = 'dev' | 'client'

function actif(choisi: boolean): string {
  return choisi ? 'bg-canvas text-ink shadow-[0_1px_3px_rgba(15,23,42,0.15)]' : 'bg-transparent text-subtle'
}

function Icone({ children, className }: { children: ReactNode; className: string }) {
  return (
    <span aria-hidden="true" className={`pointer-events-none absolute ${className}`}>
      {children}
    </span>
  )
}

export function Connexion() {
  const [profil, setProfil] = useState<ProfilChoix>('dev')
  const [email, setEmail] = useState('')
  const [envoye, setEnvoye] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)
  const dev = profil === 'dev'

  async function envoyer(evenement: FormEvent) {
    evenement.preventDefault()
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
      setErreur('Cette adresse ne semble pas valide.')
      return
    }
    setEnvoi(true)
    setErreur(null)
    try {
      await demanderLien(email.trim())
      setEnvoye(true)
    } catch (erreurHttp) {
      const lu = lireErreurHttp(erreurHttp, 'L’envoi a échoué.')
      if (isAxiosError(erreurHttp) && erreurHttp.response?.status === 429) {
        setErreur('Trop de liens demandés. Réessayez plus tard.')
      } else {
        setErreur(lu.champs.email ?? lu.message)
      }
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <div className="connexion-split min-h-svh bg-[#070b16] text-ink">
      <aside className="relative flex min-h-[620px] flex-col justify-between overflow-hidden px-6 py-8 text-canvas min-[861px]:px-[52px] min-[861px]:py-11">
        <img
          src="/connexion-hero.jpg"
          alt=""
          className="absolute -inset-3 h-[calc(100%+24px)] w-[calc(100%+24px)] object-cover object-[center_22%]"
        />
        <div
          aria-hidden="true"
          className="absolute inset-0 bg-[linear-gradient(180deg,rgba(15,23,42,0.35)_0%,rgba(15,23,42,0.08)_32%,rgba(15,23,42,0.72)_62%,rgba(15,23,42,0.92)_100%)]"
        />
        <Link to="/" className="relative text-2xl font-extrabold text-canvas no-underline">
          Souk<span className="text-gold">.dev</span>
        </Link>
        <div className="relative flex max-w-[480px] flex-col gap-[18px] [text-shadow:0_2px_18px_rgba(0,0,0,0.45)]">
          <span className="w-fit rounded-full border border-white/15 bg-ink/55 px-3 py-1 font-mono text-[12.5px] text-gold shadow-none">
            // bienvenue sur Souk.dev
          </span>
          <h1 className="m-0 text-[40px] leading-[1.08] font-extrabold tracking-[-0.025em] text-canvas min-[861px]:text-[44px]">
            Le marché des devs Africains, en un seul compte.
          </h1>
          <p className="m-0 text-[17px] leading-[1.55] text-[#F1F5F9]">
            Les devs échangent et publient leurs applis. Les entreprises trouvent l’outil ou le dev qu’il leur faut.
          </p>
          <div className="rounded-xl border border-white/15 bg-ink/55 px-5 py-4 font-mono text-sm leading-8 shadow-none">
            <div className="text-nav-doux">// votre première page après connexion</div>
            <div>
              <span className="text-[#7FA2FF]">dev</span> <span className="text-nav-doux">→</span> l’Échange
            </div>
            <div>
              <span className="text-[#FFB4A6]">entreprise</span> <span className="text-nav-doux">→</span> le Store
            </div>
          </div>
          <Link to="/" className="mt-1 text-sm font-semibold text-[#E2E8F0] no-underline">
            ← Retour à l’accueil
          </Link>
        </div>
      </aside>

      <main className="relative flex items-center justify-center overflow-hidden bg-[#070b16] px-6 py-12">
        <Icone className="flotte tuile-neon top-11 left-10 text-[#60A5FA]">
          <Nuage />
        </Icone>
        <Icone className="flotte-3 tuile-neon top-[60px] right-12 text-[#A78BFA]">
          <Serveur />
        </Icone>
        <Icone className="flotte-2 top-[150px] left-[22%] text-[#7FA2FF] opacity-70">
          <Chevrons />
        </Icone>
        <Icone className="flotte top-10 left-1/2 text-gold">
          <Etoile />
        </Icone>
        <Icone className="flotte-3 top-[170px] right-[20%] text-[#F472B6] opacity-80">
          <Accolades />
        </Icone>
        <Icone className="flotte-2 top-[44%] left-[18px] text-[#FB923C] opacity-80">
          <Cle />
        </Icone>
        <Icone className="flotte top-[52%] right-[18px] text-[#22D3EE] opacity-80">
          <Reglage />
        </Icone>
        <Icone className="flotte-2 tuile-neon bottom-16 left-11 text-[#22D3EE]">
          <Cubes />
        </Icone>
        <Icone className="flotte tuile-neon right-11 bottom-20 text-[#F472B6]">
          <Ecran />
        </Icone>
        <Icone className="flotte-3 bottom-[150px] left-[30%] text-[#FB923C] opacity-70">
          <Infini />
        </Icone>
        <Icone className="flotte-2 bottom-10 left-1/2 text-[#3B6BFF] opacity-70">
          <Branches />
        </Icone>
        <Icone className="flotte right-[24%] bottom-[170px] text-[#A78BFA] opacity-70">
          <Terminal />
        </Icone>

        <div
          role="dialog"
          aria-labelledby="titre-connexion"
          className="entree-carte relative z-[1] flex w-full max-w-[440px] flex-col gap-[22px] rounded-[18px] bg-canvas p-8 shadow-[0_30px_70px_rgba(0,0,0,0.45)]"
        >
          <div>
            <h2 id="titre-connexion" className="m-0 text-[28px] tracking-[-0.02em] text-ink">
              Se connecter
            </h2>
            <p className="m-0 text-muted">Choisissez votre profil.</p>
          </div>
          <div className="grid grid-cols-2 gap-1.5 rounded-xl bg-[#F1F5F9] p-1.5">
            <button type="button" aria-pressed={dev} onClick={() => setProfil('dev')} className={`min-h-cible rounded-lg border-0 font-bold ${actif(dev)}`}>
              Développeur
            </button>
            <button type="button" aria-pressed={!dev} onClick={() => setProfil('client')} className={`min-h-cible rounded-lg border-0 font-bold ${actif(!dev)}`}>
              Entreprise
            </button>
          </div>

          {dev ? (
            <div className="flex flex-col gap-3">
              <a href="/auth/github?profil=dev" className="inline-flex min-h-[52px] items-center justify-center gap-2.5 rounded-xl bg-ink font-bold text-canvas no-underline">
                <Branches />
                Continuer avec GitHub
              </a>
              <p className="m-0 text-[13.5px] text-muted">
                Dépôts publics uniquement : on ne demande jamais l’accès à vos dépôts privés.
              </p>
            </div>
          ) : envoye ? (
            <div role="status" className="flex flex-col gap-1.5 rounded-xl border border-[#BBF7D0] bg-[#F0FDF4] p-4 text-[#166534]">
              <strong>Lien envoyé à {email}</strong>
              <span className="text-sm">Ouvrez-le depuis cet appareil. Il ne sert qu’une seule fois.</span>
              <button type="button" onClick={() => setEnvoye(false)} className="mt-1 min-h-8 self-start border-0 bg-transparent p-0 font-semibold text-accent">
                Changer d’adresse
              </button>
            </div>
          ) : (
            <form onSubmit={(evenement) => void envoyer(evenement)} className="flex flex-col gap-2.5" noValidate>
              <label htmlFor="email-lien" className="text-sm font-semibold">
                Votre email
              </label>
              <input
                id="email-lien"
                type="email"
                autoComplete="email"
                value={email}
                onChange={(evenement) => {
                  setEmail(evenement.target.value)
                  setErreur(null)
                }}
                placeholder="vous@entreprise.bj"
                className="min-h-12 rounded-controle border border-line bg-canvas px-3.5 text-ink"
              />
              {erreur ? <p role="alert" className="m-0 text-sm text-danger">{erreur}</p> : null}
              <button type="submit" disabled={envoi} className="min-h-[52px] rounded-xl border-0 bg-ink font-bold text-canvas disabled:opacity-60">
                Recevoir mon lien
              </button>
              <p className="m-0 text-[13.5px] text-muted">Un lien de connexion par email, sans mot de passe.</p>
            </form>
          )}
        </div>
      </main>
    </div>
  )
}

function trait(children: ReactNode) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" className="size-9" aria-hidden="true">
      {children}
    </svg>
  )
}

function Nuage() {
  return trait(<path d="M7 18a4 4 0 0 1-.6-7.96A6 6 0 0 1 18 9a4.5 4.5 0 0 1-.5 9z" />)
}
function Serveur() {
  return trait(
    <>
      <rect x="3" y="3" width="18" height="7" rx="1.5" />
      <rect x="3" y="14" width="18" height="7" rx="1.5" />
      <path d="M7 6.5h.01M7 17.5h.01M11 6.5h6M11 17.5h6" />
    </>,
  )
}
function Chevrons() {
  return trait(<path d="M9 8 4 12l5 4M15 8l5 4-5 4M13.5 5l-3 14" />)
}
function Etoile() {
  return trait(<path d="M12 3v5M12 16v5M3 12h5M16 12h5M6.5 6.5 9 9M15 15l2.5 2.5M17.5 6.5 15 9M9 15l-2.5 2.5" />)
}
function Accolades() {
  return trait(<path d="M8 4c-2 0-2 2-2 4s-1 3-2 4c1 1 2 2 2 4s0 4 2 4M16 4c2 0 2 2 2 4s1 3 2 4c-1 1-2 2-2 4s0 4-2 4" />)
}
function Cle() {
  return trait(<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.6 2.6-2.4-.6-.6-2.4z" />)
}
function Reglage() {
  return trait(
    <>
      <circle cx="12" cy="12" r="7" />
      <circle cx="12" cy="12" r="2" />
      <path d="M12 2v3M12 19v3M2 12h3M19 12h3" />
    </>,
  )
}
function Cubes() {
  return trait(<path d="M3 13h6v6H3zM9 13h6v6H9zM15 13h6v6h-6zM6 7h6v6H6zM12 7h6v6h-6z" />)
}
function Ecran() {
  return trait(
    <>
      <rect x="3" y="4" width="18" height="13" rx="2" />
      <path d="M8 21h8M12 17v4" />
    </>,
  )
}
function Infini() {
  return trait(<path d="M8 8c-2.2 0-4 1.8-4 4s1.8 4 4 4c3 0 5-8 8-8 2.2 0 4 1.8 4 4s-1.8 4-4 4c-3 0-5-8-8-8z" />)
}
function Branches() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" className="size-[18px]" aria-hidden="true">
      <circle cx="6" cy="5" r="2" />
      <circle cx="6" cy="19" r="2" />
      <circle cx="18" cy="8" r="2" />
      <path d="M6 7v10M18 10c0 4-6 3-12 7" />
    </svg>
  )
}
function Terminal() {
  return trait(
    <>
      <rect x="3" y="5" width="18" height="14" rx="2" />
      <path d="m7 10 3 2-3 2M12 15h5" />
    </>,
  )
}
