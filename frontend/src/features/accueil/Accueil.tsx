import { useEffect, useState, type ReactNode } from 'react'
import { Link } from 'react-router'
import './accueil.css'

const vitrine = [
  { nom: 'Boutique MoMo', resume: 'Boutique en ligne avec paiement MTN MoMo', prix: '15 000 F/mois', fond: '#FEF3C7', texte: '#92400E', prixCouleur: '#0F172A' },
  { nom: 'Gestock', resume: 'Stock et inventaire pour boutiques', prix: '10 000 F/mois', fond: '#DCFCE7', texte: '#166534', prixCouleur: '#0F172A' },
  { nom: 'RendezVous', resume: 'Rendez-vous avec rappel par SMS', prix: '12 000 F/mois', fond: '#DBEAFE', texte: '#1E40AF', prixCouleur: '#0F172A' },
  { nom: 'MenuQR', resume: 'Menu de restaurant par QR code', prix: 'Gratuit', fond: '#FCE7F3', texte: '#9D174D', prixCouleur: '#166534' },
]

const bloc = 'accueil-bloc mx-auto box-border max-w-[1320px]'

export function Accueil() {
  const [menu, setMenu] = useState(false)

  useEffect(() => {
    if (!menu) return
    function fermer(evenement: KeyboardEvent) {
      if (evenement.key === 'Escape') setMenu(false)
    }
    document.addEventListener('keydown', fermer)
    return () => document.removeEventListener('keydown', fermer)
  }, [menu])

  return (
    <div className="accueil">
      <section>
        <div className="accueil-hero">
          <div className="accueil-aurore accueil-a1" aria-hidden="true" />
          <div className="accueil-aurore accueil-a2" aria-hidden="true" />
          <div className="accueil-aurore accueil-a3" aria-hidden="true" />
          <div className="accueil-grain" aria-hidden="true" />

          <header className="relative z-[3] flex flex-wrap items-center justify-between gap-3">
            <Link to="/" className="inline-flex items-center gap-2.5 text-xl font-bold tracking-tight text-ink no-underline">
              <Logo />
              Souk<span className="text-accent">.dev</span>
            </Link>
            <nav aria-label="Navigation de l’accueil" className="accueil-nav flex flex-wrap gap-x-7 gap-y-1.5 text-sm">
              <LienAccueil vers="/" courant>Accueil</LienAccueil>
              <LienAccueil vers="/echange">Échange</LienAccueil>
              <LienAccueil vers="/store">Store</LienAccueil>
              <a href="#histoire" className="relative py-1.5 font-medium text-subtle no-underline hover:text-ink">Comment ça marche</a>
              <LienAccueil vers="/missions">Missions</LienAccueil>
            </nav>
            <div className="relative flex items-center gap-1.5">
              <span className="accueil-ico w-auto gap-1.5 px-2.5 text-[13.5px] font-semibold" aria-label="Langue : français">FR</span>
              <Link to="/echange" className="accueil-ico" aria-label="Rechercher">
                <Loupe />
              </Link>
              <button type="button" className="accueil-ico" aria-expanded={menu} aria-controls="menu-accueil" aria-label="Menu" onClick={() => setMenu((ouvert) => !ouvert)}>
                <Burger />
              </button>
              {menu ? (
                <div id="menu-accueil" className="accueil-verre absolute top-[calc(100%+10px)] right-0 z-[5] flex w-[230px] flex-col gap-0.5 rounded-2xl p-2">
                  <Link to="/connexion" onClick={() => setMenu(false)} className="rounded-[10px] bg-ink px-3 py-2.5 text-sm font-semibold text-canvas no-underline">Se connecter</Link>
                  <Link to="/connexion" onClick={() => setMenu(false)} className="rounded-[10px] px-3 py-2.5 text-sm font-semibold text-ink no-underline hover:bg-ink/5">Créer un compte</Link>
                  <Link to="/publier" onClick={() => setMenu(false)} className="rounded-[10px] px-3 py-2.5 text-sm font-semibold text-ink no-underline hover:bg-ink/5">Publier une appli</Link>
                  <Link to="/evenements" onClick={() => setMenu(false)} className="rounded-[10px] px-3 py-2.5 text-sm font-semibold text-ink no-underline hover:bg-ink/5">Événements</Link>
                </div>
              ) : null}
            </div>
          </header>

          <div className="relative z-[2] flex flex-1 flex-wrap items-center gap-8">
            <div className="flex min-w-0 flex-1 basis-[440px] flex-col gap-5 pl-2">
              <span className="accueil-verre inline-flex w-fit items-center gap-2 rounded-full px-3.5 py-1.5 text-[11.5px] font-bold tracking-wide text-nav-texte uppercase">
                <span className="accueil-pouls size-[7px] rounded-full bg-[#16A34A]" />
                Pour les devs, par les devs
              </span>
              <h1 className="m-0 text-[66px] leading-[1.04] font-semibold tracking-tight">
                Le marché <em className="font-normal text-muted">des</em><br />devs africains.
              </h1>
              <p className="m-0 max-w-[44ch] text-[17px] leading-relaxed text-subtle">
                Postez un bug, un autre dev le corrige. Publiez votre appli, une entreprise la déploie en un clic. Ici, l’entraide devient un revenu.
              </p>
              <div className="mt-1.5 flex flex-wrap items-center gap-5">
                <Link to="/connexion" className="inline-flex min-h-[52px] items-center gap-3 rounded-[14px] bg-ink px-6 text-[15px] font-semibold text-canvas no-underline shadow-[0_10px_22px_rgba(214,192,156,0.35)]">
                  Je suis dev <Fleche />
                </Link>
                <Link to="/store" className="inline-flex items-center gap-3 no-underline">
                  <span className="grid size-[46px] place-items-center rounded-full bg-[#F4F1EB] text-ink shadow-[0_6px_16px_rgba(120,100,70,0.14)]"><Sac /></span>
                  <span className="flex flex-col leading-tight"><strong className="text-[14.5px] text-ink">Je cherche une appli</strong><span className="text-[12.5px] text-muted">Voir le Store</span></span>
                </Link>
              </div>
            </div>
            <Scene />
          </div>
        </div>
      </section>

      <main>
        <section id="histoire" aria-labelledby="t-histoire" className={`${bloc} relative flex flex-col gap-7 overflow-hidden px-6 py-14 min-[901px]:px-12`}>
          <p className="m-0 text-sm font-bold tracking-widest text-accent uppercase">Comment ça marche</p>
          <h2 id="t-histoire" className="m-0 max-w-[18ch] text-4xl leading-tight tracking-tight">D’un bug à un client, en quatre étapes</h2>
          <div className="accueil-parcours">
            <Rail />
            <ol className="m-0 grid list-none gap-5 p-0 min-[761px]:grid-cols-2 min-[1100px]:grid-cols-4">
              <Etape numero="1" couleur="#F97362" titre="Un dev poste son bug" texte="Avec son dépôt GitHub et, s’il veut, un lien de démo."><IlluBug /></Etape>
              <Etape numero="2" couleur="#1F4FE0" titre="Un autre dev le corrige" texte="Il propose sa branche. L’auteur accepte : c’est de l’entraide, gratuite."><IlluBranche /></Etape>
              <Etape numero="3" couleur="#B45309" titre="L’appli est publiée" texte="Avec son prix, ses captures et sa démo, dans le store."><IlluFiche /></Etape>
              <Etape numero="4" couleur="#16A34A" titre="Une entreprise la déploie" texte="Sa propre copie, isolée, en un clic. Ou une version sur mesure, demandée à l’auteur."><IlluBoutique /></Etape>
            </ol>
          </div>
        </section>

        <section aria-label="Pour qui" className={bloc}>
          <div className="relative mx-auto grid max-w-[1240px] gap-6 px-4 py-10 min-[901px]:px-10 min-[961px]:grid-cols-2">
            <article className="accueil-carte flex flex-col gap-4 rounded-[22px] p-7">
              <IlluDev />
              <h2 className="m-0 text-[26px] tracking-tight">Pour les devs</h2>
              <Points couleur="#E8EEFD" trait="#1F4FE0" lignes={['Faites-vous aider sur votre vrai code, pas sur un extrait.', 'Vendez vos applis au prix que vous fixez, sans commission.', 'Décrochez des missions sur mesure, sans enchères.']} />
              <Link to="/connexion" className="inline-flex min-h-[46px] w-fit items-center rounded-[10px] bg-accent px-5 font-bold text-canvas no-underline">Rejoindre l’échange avec GitHub</Link>
            </article>
            <article className="accueil-carte flex flex-col gap-4 rounded-[22px] p-7">
              <IlluCommerce />
              <h2 className="m-0 text-[26px] tracking-tight">Pour les entreprises, agences et particuliers</h2>
              <Points couleur="#FFE4DE" trait="#C2410C" lignes={['Des applis prêtes, que vous testez avant de payer.', 'Votre propre copie, isolée, avec vos données et votre nom.', 'Besoin d’une version adaptée ? Demandez-la à l’auteur.']} />
              <Link to="/store" className="inline-flex min-h-[46px] w-fit items-center rounded-[10px] bg-ink px-5 font-bold text-canvas no-underline">Parcourir le store</Link>
            </article>
            <div className="accueil-echange" aria-hidden="true">
              <span className="accueil-jeton accueil-j-app"><Grille /></span>
              <span className="accueil-jeton accueil-j-piece">F</span>
              <span className="absolute top-1/2 left-1/2 grid size-14 -translate-1/2 place-items-center rounded-full border border-white/80 bg-[rgb(246,243,237,0.95)] shadow-[0_0_0_6px_rgb(228,226,221,0.9)]"><EchangeIcone /></span>
            </div>
          </div>
        </section>

        <section aria-labelledby="t-store" className={`${bloc} flex flex-col gap-7 px-6 py-14 min-[901px]:px-12`}>
          <div className="flex flex-wrap items-end justify-between gap-3">
            <div>
              <p className="m-0 text-sm font-bold tracking-widest text-accent uppercase">Dans le store</p>
              <h2 id="t-store" className="m-0 text-4xl tracking-tight">Des applis faites ici, prêtes à servir</h2>
            </div>
            <Link to="/store" className="inline-flex min-h-11 items-center gap-2 rounded-xl bg-ink px-4 text-sm font-semibold text-canvas no-underline">Tout le store <Fleche /></Link>
          </div>
          <ul className="m-0 grid list-none gap-[18px] p-0 min-[761px]:grid-cols-2 min-[1100px]:grid-cols-4">
            {vitrine.map((appli) => (
              <li key={appli.nom}>
                <Link to="/store" className="accueil-carte flex h-full flex-col overflow-hidden rounded-[18px] text-ink no-underline">
                  <span className="block aspect-[16/10] p-3.5 pb-0" style={{ background: appli.fond }}>
                    <span className="flex h-full flex-col overflow-hidden rounded-t-[9px] bg-white shadow-[0_2px_8px_rgba(15,23,42,0.08)]">
                      <span className="flex h-[18px] items-center gap-1 px-2 text-[9px] text-white/85" style={{ background: appli.texte }}>
                        <i className="size-1.5 rounded-full bg-white/60" /><i className="size-1.5 rounded-full bg-white/60" /><i className="size-1.5 rounded-full bg-white/60" />
                        <span className="ml-1 font-mono">{appli.nom}</span>
                      </span>
                      <span className="flex flex-1 flex-col gap-1.5 p-2">
                        <span className="h-5 rounded" style={{ background: appli.fond }} />
                        <span className="grid grid-cols-3 gap-1"><span className="h-6 rounded" style={{ background: appli.fond }} /><span className="h-6 rounded" style={{ background: appli.fond }} /><span className="h-6 rounded" style={{ background: appli.fond }} /></span>
                        <span className="mt-auto self-end rounded-full px-2 text-[9px] font-bold text-white" style={{ background: appli.texte }}>Déployer</span>
                      </span>
                    </span>
                  </span>
                  <span className="flex flex-col gap-1 p-4">
                    <span className="text-[17px] font-bold">{appli.nom}</span>
                    <span className="text-sm text-subtle">{appli.resume}</span>
                    <span className="mt-1.5 flex items-center justify-between">
                      <span className="font-bold" style={{ color: appli.prixCouleur }}>{appli.prix}</span>
                      <span className="accueil-voir text-[13px] font-semibold text-accent opacity-0">Voir l’appli →</span>
                    </span>
                  </span>
                </Link>
              </li>
            ))}
          </ul>
        </section>

        <section aria-labelledby="t-prix" className={bloc}>
          <div className="mx-auto flex max-w-[1200px] flex-col gap-7 px-6 py-14 min-[901px]:px-12">
            <p className="m-0 text-sm font-bold tracking-widest text-accent uppercase">Transparent</p>
            <h2 id="t-prix" className="m-0 text-4xl tracking-tight">Qui paie quoi ?</h2>
            <div className="grid gap-[18px] min-[761px]:grid-cols-3">
              <Regle fond="#DCFCE7" titre="Gratuit" texte="Poster un problème, aider, corriger, publier son appli. Rien de tout ça ne lance de serveur."><Coche /></Regle>
              <Regle fond="#FEF3C7" titre="Le prix de l’appli" texte="Fixé par le dev, versé au dev. Souk.dev ne prend aucune commission."><span className="text-xl font-extrabold text-[#92400E]">F</span></Regle>
              <Regle fond="#E8EEFD" titre="L’hébergement" texte="Payé sur Datacloud, seulement quand on déploie une copie qui tourne."><Serveurs /></Regle>
            </div>
          </div>
        </section>

        <section aria-labelledby="t-final" className="mx-auto max-w-[1200px] px-4 py-16">
          <div className="flex flex-wrap items-center gap-8 overflow-hidden rounded-[28px] bg-[#0B1A3D] p-8 text-canvas min-[761px]:p-10">
            <div className="min-w-0 flex-1 basis-[320px]">
              <h2 id="t-final" className="m-0 text-[38px] leading-tight">Venez au souk.</h2>
              <p className="max-w-[46ch] text-lg text-[#C9D3EA]">Posez votre premier problème, ou trouvez l’appli qui fera tourner votre activité dès demain.</p>
              <div className="mt-4 flex flex-wrap gap-3">
                <Link to="/connexion" className="inline-flex min-h-[50px] items-center rounded-xl bg-gold px-6 font-bold text-ink no-underline">Je suis dev</Link>
                <Link to="/store" className="inline-flex min-h-[50px] items-center rounded-xl border border-white/50 px-6 font-bold text-canvas no-underline">Je cherche une appli</Link>
              </div>
            </div>
            <Trio />
          </div>
        </section>
      </main>

      <footer className="border-t border-[#EEF2F9]">
        <div className="mx-auto flex max-w-[1200px] flex-wrap justify-between gap-3 px-6 py-7 text-sm text-muted">
          <span><strong className="text-ink">Souk.dev</strong> · Excellence Team · CADEV 2026</span>
          <span>Hébergé sur Datacloud</span>
        </div>
      </footer>
    </div>
  )
}

function LienAccueil({ vers, courant, children }: { vers: string; courant?: boolean; children: string }) {
  return (
    <Link to={vers} aria-current={courant ? 'page' : undefined} className={`relative py-1.5 no-underline ${courant ? 'font-semibold text-ink after:absolute after:right-0 after:-bottom-1 after:left-0 after:h-px after:bg-ink' : 'font-medium text-subtle hover:text-ink'}`}>
      {children}
    </Link>
  )
}

function Etape({ numero, couleur, titre, texte, children }: { numero: string; couleur: string; titre: string; texte: string; children: ReactNode }) {
  return (
    <li className="accueil-etape flex flex-col gap-3 rounded-[18px] border border-white/60 bg-[rgb(246,243,237,0.72)] p-5">
      {children}
      <span className="text-[13px] font-bold" style={{ color: couleur }}>Étape {numero}</span>
      <h3 className="m-0 text-[19px]">{titre}</h3>
      <p className="m-0 text-[15px] text-subtle">{texte}</p>
    </li>
  )
}

function Points({ couleur, trait, lignes }: { couleur: string; trait: string; lignes: string[] }) {
  return (
    <ul className="m-0 flex list-none flex-col gap-2.5 p-0 text-nav-texte">
      {lignes.map((ligne) => (
        <li key={ligne} className="flex items-start gap-2.5">
          <svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" className="mt-1 shrink-0"><circle cx="12" cy="12" r="11" fill={couleur} /><path d="M7 12.5l3.2 3.2L17 9" fill="none" stroke={trait} strokeWidth="2.6" strokeLinecap="round" strokeLinejoin="round" /></svg>
          {ligne}
        </li>
      ))}
    </ul>
  )
}

function Regle({ fond, titre, texte, children }: { fond: string; titre: string; texte: string; children: ReactNode }) {
  return (
    <article className="accueil-carte flex flex-col gap-2.5 rounded-[18px] p-6">
      <span className="grid size-12 place-items-center rounded-[14px]" style={{ background: fond }}>{children}</span>
      <h3 className="m-0 text-xl">{titre}</h3>
      <p className="m-0 text-subtle">{texte}</p>
    </article>
  )
}

function Scene() {
  return (
    <div className="accueil-scene min-w-0 flex-1 basis-[320px]" aria-hidden="true">
      <div className="accueil-scene-echelle relative h-full">
        <div className="accueil-pile">
          <div className="accueil-plaque accueil-p-code font-mono text-[11.5px] leading-relaxed">
            <div className="mb-3 flex gap-1.5"><i className="size-[9px] rounded-full bg-[#F87171]" /><i className="size-[9px] rounded-full bg-[#FBBF24]" /><i className="size-[9px] rounded-full bg-[#34D399]" /><span className="ml-2 text-[11px] text-muted">callback.php</span></div>
            <div>
              <span className="text-[#7C3AED]">public function</span> <span className="text-accent">callback</span>(Request $r)<br />{'{'}<br />
              {'  '}$sig = $r-&gt;header(<span className="text-[#B45309]">'X-Signature'</span>);<br />
              <span className="block rounded bg-red-500/10 text-[#B91C1C]">- if (!$sig) abort(500);</span>
              <span className="block rounded bg-green-500/10 text-[#166534]">{'+ if (!$this->verifie($sig)) abort(401);'}</span>
              {'  '}Commande::payee($r-&gt;ref);<br />{'}'}
            </div>
          </div>
          <div className="accueil-plaque accueil-p-fix">
            <div className="flex items-center gap-2.5">
              <span className="grid size-8 place-items-center rounded-full bg-[#DCFCE7] text-[#166534]"><Branche /></span>
              <span className="leading-tight"><strong className="block text-[13.5px]">Correctif proposé</strong><span className="font-mono text-[11.5px] text-muted">fix/momo-callback</span></span>
            </div>
            <div className="mt-2.5 flex gap-2.5 font-mono text-xs"><span className="text-[#166534]">+12</span><span className="text-[#B91C1C]">−3</span><span className="ml-auto font-sans font-bold text-[#166534]">Résolue ✓</span></div>
          </div>
          <div className="accueil-plaque accueil-p-app">
            <span className="grid size-[46px] place-items-center rounded-xl bg-[#FEF3C7] font-extrabold text-[#92400E]">BM</span>
            <span className="flex-1 leading-tight"><strong className="block text-sm">Boutique MoMo</strong><span className="text-[12.5px] font-bold text-accent">15 000 F/mois</span></span>
            <span className="rounded-full bg-ink px-3 py-1.5 text-xs font-bold text-canvas">Déployer</span>
          </div>
        </div>
        <div className="accueil-ombre" />
        <svg className="accueil-fil absolute top-9 right-[186px]" width="120" height="60" viewBox="0 0 120 60"><path d="M2 58 L60 6 H118" fill="none" stroke="rgba(15,23,42,0.35)" strokeWidth="1" /><circle cx="3" cy="57" r="3.5" fill="#fff" stroke="rgba(15,23,42,0.4)" /></svg>
        <div className="accueil-info accueil-verre absolute top-[18px] right-0 flex items-center gap-2.5 rounded-2xl px-3 py-2.5">
          <span className="grid size-8 place-items-center rounded-full border border-[#E2E8F0] bg-white"><Chevrons /></span>
          <span className="leading-tight"><strong className="block text-[13px]">Corrigé par un dev</strong><span className="text-[11.5px] text-muted">Sans commission</span></span>
        </div>
        <div className="accueil-sceau absolute right-1.5 bottom-0 size-[120px] rounded-full border border-white/45 bg-[rgb(244,241,235,0.45)] backdrop-blur">
          <svg viewBox="0 0 120 120" className="accueil-sceau-texte absolute inset-0 size-full"><defs><path id="cercle-sk" d="M60 60 m-46 0 a46 46 0 1 1 92 0 a46 46 0 1 1 -92 0" /></defs><text fontFamily="IBM Plex Mono, monospace" fontSize="9.5" letterSpacing="2.6" fill="#334155"><textPath href="#cercle-sk">AUCUNE COMMISSION • GRATUIT POUR LES DEVS • </textPath></text></svg>
          <span className="absolute inset-0 grid place-items-center text-center leading-none"><span><strong className="block text-2xl tracking-tight">0 %</strong><span className="text-[10.5px] text-muted">commission</span></span></span>
        </div>
      </div>
    </div>
  )
}

function Rail() {
  return (
    <div className="accueil-rail" aria-hidden="true">
      <div className="accueil-ligne" />
      <span className="accueil-arret left-[12.5%]" /><span className="accueil-arret left-[37.5%]" /><span className="accueil-arret left-[62.5%]" /><span className="accueil-arret left-[87.5%]" />
      <div className="accueil-pastille">
        <span className="accueil-ic accueil-i1 bg-[#F97362]"><Bug /></span>
        <span className="accueil-ic accueil-i2 bg-accent"><Branche /></span>
        <span className="accueil-ic accueil-i3 bg-[#D97706]"><Sac /></span>
        <span className="accueil-ic accueil-i4 bg-[#16A34A] text-[19px] font-extrabold">F</span>
      </div>
    </div>
  )
}

function IlluBug() {
  return <svg viewBox="0 0 160 110" role="img" aria-label="Un écran avec un bug" className="block h-auto w-full max-w-[220px]"><rect width="160" height="110" rx="14" fill="#FFE4DE" /><rect x="38" y="22" width="84" height="56" rx="6" fill="#fff" /><rect x="30" y="76" width="100" height="8" rx="4" fill="#94A3B8" /><ellipse cx="80" cy="52" rx="10" ry="13" fill="#F97362" /><circle cx="80" cy="37" r="6" fill="#F97362" /><g stroke="#F97362" strokeWidth="2.5" strokeLinecap="round"><path d="M70 46 L62 42 M70 54 L61 54 M70 61 L63 66 M90 46 L98 42 M90 54 L99 54 M90 61 L97 66" /></g><circle cx="128" cy="22" r="11" fill="#F97362" /><text x="128" y="27" fontSize="15" fontWeight="800" fill="#fff" textAnchor="middle">!</text></svg>
}
function IlluBranche() {
  return <svg viewBox="0 0 160 110" role="img" aria-label="Une branche de correctif acceptée" className="block h-auto w-full max-w-[220px]"><rect width="160" height="110" rx="14" fill="#E8EEFD" /><g fill="none" stroke="#1F4FE0" strokeWidth="4" strokeLinecap="round"><path d="M50 26 V86" /><path d="M50 74 C50 52 104 60 104 40" /></g><circle cx="50" cy="26" r="8" fill="#fff" stroke="#1F4FE0" strokeWidth="4" /><circle cx="50" cy="86" r="8" fill="#fff" stroke="#1F4FE0" strokeWidth="4" /><circle cx="104" cy="36" r="8" fill="#fff" stroke="#1F4FE0" strokeWidth="4" /><circle cx="122" cy="80" r="15" fill="#16A34A" /><path d="M115 80 l5 5 l9 -10" fill="none" stroke="#fff" strokeWidth="3.2" strokeLinecap="round" strokeLinejoin="round" /></svg>
}
function IlluFiche() {
  return <svg viewBox="0 0 160 110" role="img" aria-label="Une fiche d’appli publiée" className="block h-auto w-full max-w-[220px]"><rect width="160" height="110" rx="14" fill="#FEF3C7" /><rect x="34" y="20" width="92" height="74" rx="10" fill="#fff" /><rect x="42" y="28" width="76" height="34" rx="6" fill="#FFCC00" opacity="0.55" /><rect x="42" y="68" width="46" height="7" rx="3.5" fill="#0F172A" /><rect x="42" y="80" width="30" height="7" rx="3.5" fill="#1F4FE0" /><circle cx="122" cy="24" r="14" fill="#1F4FE0" /><path d="M122 31 V18 M116 23 L122 17 L128 23" fill="none" stroke="#fff" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" /></svg>
}
function IlluBoutique() {
  return <svg viewBox="0 0 160 110" role="img" aria-label="Une boutique et un téléphone" className="block h-auto w-full max-w-[220px]"><rect width="160" height="110" rx="14" fill="#DCFCE7" /><rect x="30" y="44" width="78" height="52" rx="4" fill="#fff" /><path d="M30 30 h13 v14 a6.5 6.5 0 0 1 -13 0 Z" fill="#F97362" /><path d="M43 30 h13 v14 a6.5 6.5 0 0 1 -13 0 Z" fill="#fff" /><path d="M56 30 h13 v14 a6.5 6.5 0 0 1 -13 0 Z" fill="#F97362" /><path d="M69 30 h13 v14 a6.5 6.5 0 0 1 -13 0 Z" fill="#fff" /><path d="M82 30 h13 v14 a6.5 6.5 0 0 1 -13 0 Z" fill="#F97362" /><path d="M95 30 h13 v14 a6.5 6.5 0 0 1 -13 0 Z" fill="#fff" /><rect x="56" y="66" width="24" height="30" rx="3" fill="#16A34A" opacity="0.7" /><rect x="108" y="40" width="30" height="54" rx="7" fill="#0F172A" /><rect x="112" y="46" width="22" height="36" rx="3" fill="#FFCC00" /><text x="123" y="69" fontSize="13" fontWeight="800" fill="#92400E" textAnchor="middle">F</text></svg>
}
function IlluDev() {
  return <svg viewBox="0 0 300 200" role="img" aria-label="Une dev avec son ordinateur" className="block h-auto w-full"><rect width="300" height="200" rx="20" fill="#E8EEFD" /><circle cx="250" cy="44" r="12" fill="#FFCC00" /><circle cx="40" cy="160" r="6" fill="#F97362" /><g transform="translate(130 70)"><circle cx="-22" cy="6" r="7" fill="#1F2937" /><circle cx="22" cy="6" r="7" fill="#1F2937" /><path d="M-42 104 C-42 52 -26 36 0 36 C26 36 42 52 42 104 Z" fill="#7C3AED" /><rect x="-7" y="14" width="14" height="22" rx="6" fill="#A86B3C" /><circle cx="0" cy="0" r="21" fill="#A86B3C" /><path d="M-21 -1 C-22 -27 22 -27 21 -1 C14 -12 -14 -12 -21 -1 Z" fill="#1F2937" /><circle cx="-7" cy="1" r="2.3" fill="#0F172A" /><circle cx="7" cy="1" r="2.3" fill="#0F172A" /><rect x="-48" y="62" width="96" height="58" rx="6" fill="#E2E8F0" /><rect x="-58" y="118" width="116" height="9" rx="4.5" fill="#94A3B8" /></g><ellipse cx="236" cy="150" rx="22" ry="7" fill="#FFCC00" stroke="#F59E0B" strokeWidth="2" /><ellipse cx="236" cy="141" rx="22" ry="7" fill="#FFCC00" stroke="#F59E0B" strokeWidth="2" /></svg>
}
function IlluCommerce() {
  return <svg viewBox="0 0 300 200" role="img" aria-label="Un commerçant devant sa boutique" className="block h-auto w-full"><rect width="300" height="200" rx="20" fill="#FFE4DE" /><rect x="150" y="70" width="120" height="110" rx="6" fill="#fff" /><path d="M150 52 h20 v18 a10 10 0 0 1 -20 0 Z" fill="#1F4FE0" /><path d="M170 52 h20 v18 a10 10 0 0 1 -20 0 Z" fill="#fff" /><path d="M190 52 h20 v18 a10 10 0 0 1 -20 0 Z" fill="#1F4FE0" /><path d="M210 52 h20 v18 a10 10 0 0 1 -20 0 Z" fill="#fff" /><path d="M230 52 h20 v18 a10 10 0 0 1 -20 0 Z" fill="#1F4FE0" /><rect x="168" y="96" width="36" height="28" rx="4" fill="#E8EEFD" /><rect x="216" y="96" width="36" height="28" rx="4" fill="#E8EEFD" /><rect x="194" y="136" width="30" height="44" rx="3" fill="#1F4FE0" opacity="0.8" /><g transform="translate(80 80)"><path d="M-42 104 C-42 52 -26 36 0 36 C26 36 42 52 42 104 Z" fill="#16A34A" /><circle cx="0" cy="0" r="21" fill="#4A2C17" /><path d="M-21 -2 C-23 -28 23 -28 21 -2 C16 -13 -16 -13 -21 -2 Z" fill="#1F2937" /><circle cx="-7" cy="1" r="2.3" fill="#0F172A" /><circle cx="7" cy="1" r="2.3" fill="#0F172A" /></g><rect x="96" y="128" width="24" height="40" rx="5" fill="#0F172A" /><rect x="99" y="132" width="18" height="28" rx="2" fill="#FFCC00" /></svg>
}
function Trio() {
  return <svg viewBox="0 0 360 200" role="img" aria-label="Trois membres de la communauté" className="block h-auto w-full max-w-[420px] flex-1 basis-[280px]"><circle cx="300" cy="40" r="10" fill="#FFCC00" /><circle cx="340" cy="160" r="6" fill="#fff" opacity="0.6" /><g transform="translate(90 90) scale(0.9)"><path d="M-42 104 C-42 52 -26 36 0 36 C26 36 42 52 42 104 Z" fill="#FFCC00" /><circle cx="0" cy="0" r="21" fill="#8D5524" /><path d="M-21 -2 C-23 -28 23 -28 21 -2 Z" fill="#1F2937" /><circle cx="-7" cy="1" r="2.3" fill="#0F172A" /><circle cx="7" cy="1" r="2.3" fill="#0F172A" /></g><g transform="translate(180 80)"><path d="M-42 104 C-42 52 -26 36 0 36 C26 36 42 52 42 104 Z" fill="#F97362" /><circle cx="0" cy="0" r="21" fill="#A86B3C" /><path d="M-23 0 C-26 -30 26 -30 23 0 C15 -10 -15 -10 -23 0 Z" fill="#16A34A" /><circle cx="-7" cy="1" r="2.3" fill="#0F172A" /><circle cx="7" cy="1" r="2.3" fill="#0F172A" /></g><g transform="translate(270 90) scale(0.9)"><circle cx="0" cy="-8" r="27" fill="#1F2937" /><path d="M-42 104 C-42 52 -26 36 0 36 C26 36 42 52 42 104 Z" fill="#16A34A" /><circle cx="0" cy="0" r="21" fill="#6B3E1F" /><circle cx="-7" cy="1" r="2.3" fill="#0F172A" /><circle cx="7" cy="1" r="2.3" fill="#0F172A" /></g></svg>
}

function Logo() { return <svg width="30" height="22" viewBox="0 0 30 22" aria-hidden="true"><path d="M2 20 L11 4 L16 12 L19 7 L28 20 Z" fill="#0F172A" /><path d="M11 4 L13.5 8.5 L11 11 L8.6 8.4 Z" fill="#FFCC00" /></svg> }
function Loupe() { return <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></svg> }
function Burger() { return <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" /></svg> }
function Fleche() { return <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" /></svg> }
function Sac() { return <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M5 8h14l-1 12H6z" /><path d="M9 8V6a3 3 0 0 1 6 0v2" /></svg> }
function Branche() { return <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><circle cx="6" cy="5" r="2" /><circle cx="6" cy="19" r="2" /><circle cx="18" cy="8" r="2" /><path d="M6 7v10M18 10c0 4-6 3-12 7" /></svg> }
function Chevrons() { return <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M9 8 4 12l5 4M15 8l5 4-5 4" /></svg> }
function Bug() { return <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><rect x="8" y="6" width="8" height="12" rx="4" /><path d="M12 6V4M8 10H4M16 10h4M8 15H5M16 15h3" /></svg> }
function Grille() { return <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2" aria-hidden="true"><rect x="4" y="4" width="7" height="7" rx="1.5" /><rect x="13" y="4" width="7" height="7" rx="1.5" /><rect x="4" y="13" width="7" height="7" rx="1.5" /><rect x="13" y="13" width="7" height="7" rx="1.5" /></svg> }
function EchangeIcone() { return <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0F172A" strokeWidth="1.9" strokeLinecap="round" aria-hidden="true"><path d="M4 9h14l-3-3M20 15H6l3 3" /></svg> }
function Coche() { return <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#16A34A" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5" /></svg> }
function Serveurs() { return <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#1F4FE0" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="7" rx="2" /><rect x="3" y="13" width="18" height="7" rx="2" /><path d="M7 7.5h.01M7 16.5h.01" /></svg> }
