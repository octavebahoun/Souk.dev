import { useEffect, useState, type ReactNode } from 'react'
import { Link } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { fcfa, listerApplis, type Appli } from '../catalogue/api'
import { teintes } from '../catalogue/teintes'
import { listerDiscussions, messageAction, type Discussion } from '../echange/api'
import './accueil.css'

const bloc = 'accueil-bloc mx-auto box-border max-w-[1320px]'

export function Accueil() {
  const [menu, setMenu] = useState(false)
  const applis = useQuery({ queryKey: ['applis', 'accueil'], queryFn: () => listerApplis({ tri: 'recent' }) })
  const discussions = useQuery({ queryKey: ['discussions', 'accueil'], queryFn: () => listerDiscussions({ page: 1 }) })
  const vitrine = applis.data?.data.slice(0, 4) ?? []

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
            <Scene
              appli={vitrine[0]}
              discussion={discussions.data?.data[0]}
              chargementAppli={applis.isPending}
              chargementDiscussion={discussions.isPending}
              erreurAppli={applis.isError}
              erreurDiscussion={discussions.isError}
            />
          </div>
        </div>
      </section>

      <main>
        <section id="histoire" aria-labelledby="t-histoire" className={`${bloc} relative flex flex-col gap-7 overflow-hidden px-6 py-14 min-[901px]:px-12`}>
          <p className="m-0 text-sm font-bold tracking-widest text-primaire-actif uppercase">Comment ça marche</p>
          <h2 id="t-histoire" className="m-0 max-w-[18ch] text-4xl leading-tight tracking-tight">D’un bug à un client, en quatre étapes</h2>
          <div className="accueil-parcours">
            <Rail />
            <ol className="m-0 grid list-none gap-5 p-0 min-[761px]:grid-cols-2 min-[1100px]:grid-cols-4">
              <Etape numero="1" couleur="#F97362" titre="Un dev poste son bug" texte="Avec son dépôt GitHub et, s’il veut, un lien de démo."><PhotoEtape src="https://images.unsplash.com/photo-1519389950473-47ba0277781c?auto=format&fit=crop&w=900&q=82" alt="Des développeurs travaillent ensemble sur un ordinateur" /></Etape>
              <Etape numero="2" couleur="#1F4FE0" titre="Un autre dev le corrige" texte="Il propose sa branche. L’auteur accepte : c’est de l’entraide, gratuite."><PhotoEtape src="https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=900&q=82" alt="Un ordinateur affiche du code pendant une session de développement" /></Etape>
              <Etape numero="3" couleur="#B45309" titre="L’appli est publiée" texte="Avec son prix, ses captures et sa démo, dans le store."><PhotoEtape src="https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?auto=format&fit=crop&w=900&q=82" alt="Une cliente règle un achat avec un moyen de paiement mobile" /></Etape>
              <Etape numero="4" couleur="#16A34A" titre="Une entreprise la déploie" texte="Sa propre copie, isolée, en un clic. Ou une version sur mesure, demandée à l’auteur."><PhotoEtape src="https://images.unsplash.com/photo-1521737711867-e3b97375f902?auto=format&fit=crop&w=900&q=82" alt="Une équipe échange autour d’une table de réunion" /></Etape>
            </ol>
          </div>
        </section>

        <section aria-label="Pour qui" className={bloc}>
          <div className="relative mx-auto grid max-w-[1240px] gap-6 px-4 py-10 min-[901px]:px-10 min-[961px]:grid-cols-2">
            <article data-reveal-item className="accueil-carte flex flex-col gap-4 rounded-[22px] p-7">
              <IlluDev />
              <h2 className="m-0 text-[26px] tracking-tight">Pour les devs</h2>
              <Points couleur="#E8EEFD" trait="#1F4FE0" lignes={['Faites-vous aider sur votre vrai code, pas sur un extrait.', 'Vendez vos applis au prix que vous fixez, sans commission.', 'Décrochez des missions sur mesure, sans enchères.']} />
              <Link to="/connexion" className="inline-flex min-h-[46px] w-fit items-center rounded-[10px] bg-accent px-5 font-bold text-canvas no-underline">Rejoindre l’échange avec GitHub</Link>
            </article>
            <article data-reveal-item className="accueil-carte flex flex-col gap-4 rounded-[22px] p-7">
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
              <p className="m-0 text-sm font-bold tracking-widest text-primaire-actif uppercase">Dans le store</p>
              <h2 id="t-store" className="m-0 text-4xl tracking-tight">Des applis faites ici, prêtes à servir</h2>
            </div>
            <Link to="/store" className="inline-flex min-h-11 items-center gap-2 rounded-xl bg-ink px-4 text-sm font-semibold text-canvas no-underline">Tout le store <Fleche /></Link>
          </div>
          <ul className="m-0 grid list-none gap-[18px] p-0 min-[761px]:grid-cols-2 min-[1100px]:grid-cols-4">
            {vitrine.map((appli) => {
              const teinte = teintes[appli.categorie]
              const prix = appli.prix === 0 ? 'Gratuit' : `${fcfa(appli.prix)}${appli.type_prix === 'mensuel' ? '/mois' : ''}`
              return (
                <li key={appli.id} data-reveal-item>
                  <Link to={`/store/${appli.id}`} className="accueil-carte flex h-full flex-col overflow-hidden rounded-[18px] text-ink no-underline">
                    {appli.captures[0] ? (
                      <img src={appli.captures[0].url} alt={`Capture de ${appli.nom}`} className="aspect-[16/10] w-full object-cover" />
                    ) : (
                      <span className="grid aspect-[16/10] place-items-center p-4 text-center" style={{ background: teinte.fond, color: teinte.texte }}>
                        <span><strong className="block text-3xl">{appli.nom.slice(0, 2).toUpperCase()}</strong><span className="mt-1 block text-sm font-semibold">Aucune capture publiée</span></span>
                      </span>
                    )}
                    <span className="flex flex-1 flex-col gap-1 p-4">
                      <span className="text-[17px] font-bold">{appli.nom}</span>
                      <span className="line-clamp-2 text-sm text-texte">{appli.description}</span>
                      <span className="mt-1.5 flex items-center justify-between">
                        <span className="font-bold">{prix}</span>
                        <span className="text-[13px] font-semibold text-accent">Voir l’appli →</span>
                      </span>
                    </span>
                  </Link>
                </li>
              )
            })}
          </ul>
          {applis.isPending ? <p role="status" className="m-0 text-muted">Chargement des applis publiées…</p> : null}
          {applis.isError ? (
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-line bg-white/70 p-4">
              <p role="alert" className="m-0 text-subtle">{messageAction(applis.error, 'Les applis publiées ne sont pas disponibles.')}</p>
              <button type="button" onClick={() => void applis.refetch()} className="min-h-cible rounded-controle border border-line bg-canvas px-4 font-semibold">Réessayer</button>
            </div>
          ) : null}
          {applis.isSuccess && vitrine.length === 0 ? <p className="m-0 rounded-xl border border-dashed border-line p-6 text-center text-subtle">Aucune appli publiée pour le moment. Les fiches apparaîtront ici après une publication réelle.</p> : null}
        </section>

        <section aria-labelledby="t-prix" className={bloc}>
          <div className="mx-auto flex max-w-[1200px] flex-col gap-7 px-6 py-14 min-[901px]:px-12">
            <p className="m-0 text-sm font-bold tracking-widest text-primaire-actif uppercase">Transparent</p>
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
              <h2 id="t-final" className="m-0 text-[38px] leading-tight text-canvas">Venez au souk.</h2>
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
        <div className="mx-auto flex max-w-[1200px] flex-wrap justify-between gap-3 px-6 py-7 text-sm text-texte">
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
    <li data-reveal-item className="accueil-etape flex flex-col gap-3 rounded-[18px] border border-white/60 bg-[rgb(246,243,237,0.72)] p-5">
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
    <article data-reveal-item className="accueil-carte flex flex-col gap-2.5 rounded-[18px] p-6">
      <span className="grid size-12 place-items-center rounded-[14px]" style={{ background: fond }}>{children}</span>
      <h3 className="m-0 text-xl">{titre}</h3>
      <p className="m-0 text-subtle">{texte}</p>
    </article>
  )
}

type SceneProps = {
  appli?: Appli
  discussion?: Discussion
  chargementAppli: boolean
  chargementDiscussion: boolean
  erreurAppli: boolean
  erreurDiscussion: boolean
}

function Scene({ appli, discussion, chargementAppli, chargementDiscussion, erreurAppli, erreurDiscussion }: SceneProps) {
  return (
    <div role="group" aria-label="Activité publiée sur Souk.dev" className="accueil-scene min-w-0 flex-1 basis-[320px]">
      <div className="accueil-scene-echelle relative h-full">
        <div className="accueil-pile">
          <div className="accueil-plaque accueil-p-code font-mono text-[11.5px] leading-relaxed">
            <div className="mb-3 flex gap-1.5"><i className="size-[9px] rounded-full bg-[#F87171]" /><i className="size-[9px] rounded-full bg-[#FBBF24]" /><i className="size-[9px] rounded-full bg-[#34D399]" /><span className="ml-2 text-[11px] text-muted">ÉCHANGE · DONNÉES PUBLIÉES</span></div>
            {discussion ? (
              <Link to={`/echange/${discussion.id}`} className="block text-ink no-underline">
                <span className="mb-1 block text-[10px] font-bold tracking-[0.14em] text-accent uppercase">{discussion.canal ? `#${discussion.canal}` : 'Discussion publique'}</span>
                <strong className="block font-sans text-[15px] leading-snug">{discussion.titre}</strong>
                <span className="mt-1 block font-sans text-xs text-muted">{discussion.auteur.nom || (discussion.auteur.username ? `@${discussion.auteur.username}` : 'Compte Souk.dev')}</span>
                <span className="mt-2 block line-clamp-2 font-sans text-[12px] leading-relaxed text-subtle">{discussion.texte}</span>
                <span className="mt-2 inline-flex rounded-full bg-nav px-2.5 py-1 font-sans text-[11px] font-bold text-nav-texte">{discussion.resolue ? 'Résolue' : 'Ouverte à la communauté'}</span>
              </Link>
            ) : (
              <p className="m-0 font-sans text-sm leading-relaxed text-subtle">
                {chargementDiscussion ? 'Chargement des discussions publiées…' : erreurDiscussion ? 'Les discussions ne sont pas accessibles pour le moment.' : 'Aucune discussion publique publiée pour le moment.'}
              </p>
            )}
          </div>
          {appli ? (
            <Link to={`/store/${appli.id}`} className="accueil-plaque accueil-p-app text-ink no-underline">
              <span className="grid size-[46px] shrink-0 place-items-center overflow-hidden rounded-xl bg-[#FEF3C7] font-extrabold text-[#92400E]">
                {appli.captures[0] ? <img src={appli.captures[0].url} alt="" className="size-full object-cover" /> : appli.nom.slice(0, 2).toUpperCase()}
              </span>
              <span className="min-w-0 flex-1 leading-tight"><strong className="block truncate text-sm">{appli.nom}</strong><span className="text-[12.5px] font-bold text-accent">{appli.prix === 0 ? 'Gratuit' : `${fcfa(appli.prix)}${appli.type_prix === 'mensuel' ? '/mois' : ''}`}</span></span>
              <span className="rounded-full bg-ink px-3 py-1.5 text-xs font-bold text-canvas">Voir la fiche</span>
            </Link>
          ) : (
            <div className="accueil-plaque accueil-p-app text-subtle">
              <span className="grid size-[46px] shrink-0 place-items-center rounded-xl bg-[#E8EEFD] text-accent"><Grille /></span>
              <span className="min-w-0 flex-1 leading-tight"><strong className="block text-sm">{chargementAppli ? 'Chargement du Store…' : erreurAppli ? 'Store indisponible' : 'Aucune appli publiée'}</strong><span className="text-[12px]">{erreurAppli ? 'Les données seront réessayées plus bas.' : 'Les fiches s’affichent après publication.'}</span></span>
              <Link to="/store" className="rounded-full bg-ink px-3 py-1.5 text-xs font-bold text-canvas no-underline">Voir le Store</Link>
            </div>
          )}
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

function IlluDev() {
  return <img src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=1100&q=84" alt="Deux développeurs échangent devant un ordinateur" loading="lazy" decoding="async" className="block aspect-[16/9] w-full rounded-[16px] object-cover" />
}
function IlluCommerce() {
  return <img src="https://images.unsplash.com/photo-1521737711867-e3b97375f902?auto=format&fit=crop&w=1100&q=84" alt="Une équipe discute d’un projet dans une salle de réunion" loading="lazy" decoding="async" className="block aspect-[16/9] w-full rounded-[16px] object-cover" />
}
function PhotoEtape({ src, alt }: { src: string; alt: string }) {
  return <img src={src} alt={alt} loading="lazy" decoding="async" className="block aspect-[16/9] w-full rounded-xl object-cover shadow-[0_8px_18px_rgba(15,23,42,0.12)]" />
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
