import { useState, type CSSProperties, type FormEvent } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router'
import { creerEvenement, desinscrire, inscrire, listerEvenements, posterAvis, type Evenement } from './api'
import { lireMoi, messageAction } from '../echange/api'

const couleurs = ['#E6007E', '#06B6C9', '#FF6B16', '#7C3AED']

function Cube({ classe, fond, texte }: { classe: string; fond: string; texte: string }) {
  return (
    <span aria-hidden="true" className={`cube-flottant absolute hidden size-16 place-items-center rounded-xl text-center text-[11px] font-extrabold tracking-widest text-white shadow-[10px_12px_0_rgb(0,0,0,0.25)] min-[761px]:grid ${classe}`} style={{ background: fond }}>
      {texte}
    </span>
  )
}

export function Evenements() {
  const query = useQuery({ queryKey: ['evenements'], queryFn: () => listerEvenements() })
  const client = useQueryClient()
  const [filtre, setFiltre] = useState<'tous' | 'en_ligne' | 'presentiel'>('tous')
  const [vue, setVue] = useState<'liste' | 'grille'>('liste')
  const [ouvert, setOuvert] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)
  const [maintenant] = useState(() => Date.now())
  const liste = (query.data?.data ?? []).filter((item) => filtre === 'tous' || item.type === filtre)
  const aVenir = liste.filter((item) => new Date(item.date).getTime() >= maintenant)
  const passes = liste.filter((item) => new Date(item.date).getTime() < maintenant)

  async function basculer(item: Evenement) {
    try {
      if (item.inscrit) await desinscrire(item.id)
      else await inscrire(item.id)
      await client.invalidateQueries({ queryKey: ['evenements'] })
    } catch (erreurHttp) {
      setErreur(messageAction(erreurHttp, 'L’inscription a échoué.'))
    }
  }

  return (
    <main className="mx-auto flex max-w-[1500px] flex-col px-4 pb-10">
      <section className="nuit-hero relative left-1/2 mb-0 w-screen -translate-x-1/2 overflow-hidden px-6 pt-10 pb-32 text-canvas min-[761px]:min-h-[645px] min-[761px]:pt-[112px]">
        <div aria-hidden="true" className="event-montagne event-montagne-loin" />
        <div aria-hidden="true" className="event-montagne event-montagne-proche" />
        <Cube classe="top-8 left-[6%] rotate-12" fond="#D946EF" texte="SOUK" />
        <Cube classe="top-16 right-[8%] -rotate-6 [animation-delay:1s]" fond="#312E81" texte="CODE" />
        <Cube classe="top-[42%] left-[14%] rotate-6 [animation-delay:.4s]" fond="#0F766E" texte="MEET" />
        <Cube classe="right-[5%] top-[48%] -rotate-12 [animation-delay:1.4s]" fond="#EA580C" texte="ATEL" />
        <Cube classe="bottom-20 left-[24%] -rotate-6 [animation-delay:2s]" fond="#65A30D" texte="HACK" />
        <Cube classe="right-[17%] bottom-16 rotate-12 [animation-delay:.8s]" fond="#8B5CF6" texte="REVUE" />
        <div className="relative z-10 mx-auto max-w-[860px] text-center">
          <h1 className="m-0 text-4xl font-extrabold tracking-[0.14em] min-[761px]:text-[76px] min-[761px]:leading-none">ÉVÉNEMENTS</h1>
          <p className="mx-auto mt-5 max-w-[58ch] rounded-2xl border border-white/10 bg-[#101f47]/75 px-5 py-4 text-[#E4E9F7] shadow-[0_18px_44px_rgba(4,13,38,0.18)]">Ateliers en ligne et rencontres près de chez vous, organisés par les devs de la communauté. Venez apprendre, montrer votre code et rencontrer ceux qui construisent avec vous.</p>
          <div className="mt-12 flex flex-wrap justify-center gap-3">
            <div role="group" aria-label="Type d'événement" className="inline-flex rounded-[10px] bg-white/15 p-[3px]">
              {([['tous', 'Tous'], ['en_ligne', 'En ligne'], ['presentiel', 'Présentiel']] as const).map(([valeur, libelle]) => (
                <button key={valeur} type="button" aria-pressed={filtre === valeur} onClick={() => setFiltre(valeur)} className={`min-h-9 rounded-lg px-3.5 text-sm font-semibold ${filtre === valeur ? 'bg-canvas text-ink' : 'bg-transparent text-canvas'}`}>{libelle}</button>
              ))}
            </div>
            <button type="button" onClick={() => setOuvert(true)} className="min-h-cible rounded-controle bg-gold px-4 font-bold text-ink">+ Créer un événement</button>
          </div>
        </div>
      </section>
      <div className="relative z-10 -mt-14 flex flex-col gap-6 min-[761px]:-mt-[104px]">
      {erreur ? <p role="alert" className="m-0 text-danger">{erreur}</p> : null}
      {query.isPending ? <div role="status" className="squelette h-32 rounded-xl" /> : null}
      {query.isError ? (
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-line bg-canvas p-5">
          <p role="alert" className="m-0 text-subtle">{messageAction(query.error, 'Les événements n’ont pas pu être chargés.')}</p>
          <button type="button" onClick={() => void query.refetch()} className="min-h-cible rounded-controle bg-accent px-4 font-semibold text-canvas">Réessayer</button>
        </div>
      ) : null}
      {aVenir.length > 0 ? (
        <section className="rounded-[24px] border border-white bg-canvas px-5 py-6 shadow-[0_24px_54px_rgba(15,23,42,0.12)] min-[761px]:px-8 min-[761px]:py-7">
          <div className="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-line pb-4">
            <h2 className="m-0 text-[22px] font-bold">Événements à venir</h2>
            <div role="group" aria-label="Disposition des événements" className="inline-flex gap-1 rounded-xl bg-page p-1">
              <button type="button" aria-pressed={vue === 'liste'} onClick={() => setVue('liste')} className={`min-h-10 rounded-lg px-3.5 text-sm font-bold ${vue === 'liste' ? 'bg-[#E8FBF7] text-[#0F8F81]' : 'bg-transparent text-muted'}`}>
                <span aria-hidden="true" className="mr-1.5">☰</span>Liste
              </button>
              <button type="button" aria-pressed={vue === 'grille'} onClick={() => setVue('grille')} className={`min-h-10 rounded-lg px-3.5 text-sm font-bold ${vue === 'grille' ? 'bg-[#E8FBF7] text-[#0F8F81]' : 'bg-transparent text-muted'}`}>
                <span aria-hidden="true" className="mr-1.5">▦</span>Grille
              </button>
            </div>
          </div>
          <Liste titre="" items={aVenir} onBasculer={basculer} grille={vue === 'grille'} />
        </section>
      ) : null}
      <Liste titre="Passés" items={passes} onBasculer={basculer} avis />
      {query.isSuccess && aVenir.length === 0 && passes.length === 0 ? (
        <p className="m-0 rounded-carte border border-dashed border-line bg-canvas p-7 text-center text-subtle">Aucun événement de ce type pour l’instant. Pourquoi pas le vôtre ?</p>
      ) : null}
      {ouvert ? <Formulaire onFerme={() => setOuvert(false)} /> : null}
      </div>
    </main>
  )
}

function Liste({ titre, items, onBasculer, avis = false, grille = false }: { titre: string; items: Evenement[]; onBasculer: (item: Evenement) => void; avis?: boolean; grille?: boolean }) {
  if (items.length === 0) return null
  return (
    <section className="flex flex-col gap-3">
      {titre ? <h2 className="m-0 text-[19px]">{titre}</h2> : null}
      <ul className={`m-0 list-none gap-3 p-0 ${grille ? 'grid grid-cols-1 min-[761px]:grid-cols-2 min-[1100px]:grid-cols-3' : 'flex flex-col'}`}>
        {items.map((item, index) => {
          const date = new Date(item.date)
          const couleur = couleurs[index % couleurs.length]
          return (
            <li key={item.id} data-reveal-item className={`carte-vive flex flex-col rounded-carte border border-line bg-canvas ${grille ? 'gap-4 p-4' : 'gap-3 border-b-[#E7EBF3] px-0 py-5 min-[761px]:px-3'}`}>
              <div className={`flex items-start gap-4 ${grille ? 'flex-col' : 'flex-col min-[761px]:flex-row min-[761px]:gap-5'}`}>
                {avis ? null : (
                  <div className={`flex shrink-0 items-start ${grille ? 'gap-3' : 'gap-3 min-[761px]:w-[300px] min-[761px]:gap-5'}`}>
                    {!grille ? <div className="hidden min-w-[62px] flex-col gap-6 pt-1 text-[13px] font-bold tracking-[0.16em] text-muted uppercase min-[761px]:flex"><span>{date.toLocaleDateString('fr-FR', { month: 'short' }).replace('.', '').toUpperCase()}</span><span>{date.getDate()}</span></div> : null}
                    <div aria-hidden="true" className={`event-cube grid place-items-center text-center font-extrabold tracking-[0.18em] text-white ${grille ? 'size-[94px] rounded-lg text-[10px]' : 'size-[88px] rounded-lg text-[10px] min-[761px]:size-[196px] min-[761px]:rounded-none min-[761px]:text-[20px]'}`} style={{ background: couleur, '--event-color': couleur } as CSSProperties}>
                      {date.toLocaleDateString('fr-FR', { month: 'short' }).replace('.', '').toUpperCase()} {date.getDate()} ’{String(date.getFullYear()).slice(-2)}
                    </div>
                  </div>
                )}
                <div className="flex min-w-0 flex-[1_1_320px] flex-col gap-1.5">
                  <div className="flex flex-wrap items-center gap-2">
                    <h3 className="m-0 text-[17px]">{item.titre}</h3>
                    <span className={`rounded-full px-2 text-[12.5px] font-bold ${item.type === 'en_ligne' ? 'bg-nav text-accent' : 'bg-[#FFEDD5] text-[#9A3412]'}`}>{item.type === 'en_ligne' ? 'En ligne' : 'Présentiel'}</span>
                  </div>
                  <p className="m-0 text-[14.5px] text-subtle">{item.description}</p>
                  <p className="m-0 text-[13.5px] text-muted">
                    {date.toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' })}
                    {' · '}
                    {item.type === 'en_ligne' ? 'En ligne' : item.lieu || 'Présentiel'}
                    {item.organisateur.username ? <> · par <Link to={`/devs/${item.organisateur.username}`} className="font-bold">{item.organisateur.nom || item.organisateur.username}</Link></> : null}
                  </p>
                  {item.lien ? <p className="m-0 text-sm">Lien : <a href={item.lien} className="font-mono text-[13.5px]">{item.lien}</a></p> : null}
                  {item.type === 'en_ligne' && !item.lien && !avis ? <p className="m-0 text-[13.5px] text-muted">Le lien apparaît une fois inscrit.</p> : null}
                  {item.note_moyenne != null ? <p className="m-0 text-sm font-bold">★ {item.note_moyenne} <span className="font-medium text-muted">/ 5</span></p> : null}
                  {avis ? <AvisFormulaire id={item.id} /> : null}
                </div>
                <div className={`flex gap-2 ${grille ? 'w-full flex-row items-center justify-between' : 'ml-auto flex-col items-end'}`}>
                  <button type="button" aria-pressed={item.inscrit} onClick={() => onBasculer(item)} className={`min-h-cible rounded-controle px-[18px] font-semibold ${item.inscrit ? 'border border-line bg-canvas text-ink' : 'bg-accent text-canvas'}`}>
                    {item.inscrit ? 'Se désinscrire' : "S'inscrire"}
                  </button>
                  <span className="text-[13px] text-muted tabular-nums">
                    {item.inscrit ? <span className="mr-2 rounded-full bg-success-bg px-2 font-bold text-success">Inscrit</span> : null}
                    {item.nb_inscrits} inscrits
                  </span>
                </div>
              </div>
            </li>
          )
        })}
      </ul>
    </section>
  )
}

function AvisFormulaire({ id }: { id: number }) {
  const client = useQueryClient()
  const [note, setNote] = useState('')
  const [commentaire, setCommentaire] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)
  const [ok, setOk] = useState(false)

  async function envoyer(evenement: FormEvent) {
    evenement.preventDefault()
    try {
      if (!note) {
        setErreur('Choisissez une note avant de publier votre avis.')
        return
      }
      await posterAvis(id, Number(note), commentaire.trim())
      setOk(true)
      await client.invalidateQueries({ queryKey: ['evenements'] })
    } catch (erreurHttp) {
      setErreur(messageAction(erreurHttp, 'L’avis n’a pas été publié.'))
    }
  }

  if (ok) return <p role="status" className="m-0 text-sm">Avis publié.</p>
  return (
    <form onSubmit={(e) => void envoyer(e)} className="mt-2 flex flex-wrap items-end gap-2">
      <label className="text-sm font-semibold">Avis
        <select required value={note} onChange={(e) => setNote(e.target.value)} className="champ-select mt-1">
          <option value="">Choisir une note</option>
          {[1, 2, 3, 4, 5].map((valeur) => <option key={valeur} value={valeur}>{valeur}</option>)}
        </select>
      </label>
      <input value={commentaire} onChange={(e) => setCommentaire(e.target.value)} placeholder="Commentaire" className="min-h-cible min-w-[180px] flex-1 rounded-controle border border-line bg-canvas px-3" />
      <button type="submit" className="min-h-cible rounded-controle bg-ink px-3 font-semibold text-canvas">Publier</button>
      {erreur ? <p role="alert" className="m-0 w-full text-sm text-danger">{erreur}</p> : null}
    </form>
  )
}

function Formulaire({ onFerme }: { onFerme: () => void }) {
  const client = useQueryClient()
  const moi = useQuery({ queryKey: ['moi'], queryFn: lireMoi })
  const [erreur, setErreur] = useState<string | null>(null)
  const [type, setType] = useState<'en_ligne' | 'presentiel' | ''>('')
  const [envoi, setEnvoi] = useState(false)

  async function envoyer(evenement: FormEvent<HTMLFormElement>) {
    evenement.preventDefault()
    const donnees = new FormData(evenement.currentTarget)
    const date = new Date(String(donnees.get('date') ?? ''))
    if (!Number.isFinite(date.getTime())) {
      setErreur('Choisissez une date et une heure valides.')
      return
    }
    if (date.getTime() <= Date.now()) {
      setErreur('Choisissez une date et une heure à venir.')
      return
    }
    if (!type) {
      setErreur('Choisissez le format de l’événement.')
      return
    }
    setEnvoi(true)
    setErreur(null)
    try {
      await creerEvenement({
        titre: String(donnees.get('titre') ?? ''),
        description: String(donnees.get('description') ?? ''),
        date: date.toISOString(),
        type,
        lieu: type === 'presentiel' ? String(donnees.get('lieu') ?? '').trim() : undefined,
        lien: type === 'en_ligne' ? String(donnees.get('lien') ?? '').trim() : undefined,
      })
      await client.invalidateQueries({ queryKey: ['evenements'] })
      onFerme()
    } catch (erreurHttp) {
      setErreur(messageAction(erreurHttp, 'La création a échoué.'))
    } finally {
      setEnvoi(false)
    }
  }

  if (moi.isPending) {
    return <div className="fixed inset-0 z-[60] grid place-items-center bg-ink/45 p-4"><p role="status" className="rounded-2xl bg-canvas p-6">Vérification de la connexion…</p></div>
  }

  if (moi.isError || !moi.data) {
    const serveurAbsent = moi.isError
    return (
      <div className="fixed inset-0 z-[60] grid place-items-center bg-ink/45 p-4" onClick={onFerme}>
        <div role="dialog" aria-modal="true" aria-labelledby="titre-event-connexion" onClick={(e) => e.stopPropagation()} className="flex w-full max-w-lg flex-col gap-3 rounded-2xl bg-canvas p-6 shadow-xl">
          <h2 id="titre-event-connexion" className="m-0 text-xl">{serveurAbsent ? 'Impossible de vérifier la connexion' : 'Connectez-vous pour créer un événement'}</h2>
          <p role={serveurAbsent ? 'alert' : undefined} className="m-0 text-subtle">{serveurAbsent ? messageAction(moi.error, 'Le service de connexion ne répond pas.') : 'La publication d’un événement est réservée aux comptes connectés.'}</p>
          <div className="flex flex-wrap justify-end gap-2">
            <button type="button" onClick={onFerme} className="min-h-cible rounded-controle border border-line px-4">Fermer</button>
            {serveurAbsent ? <button type="button" onClick={() => void moi.refetch()} className="min-h-cible rounded-controle border border-line px-4 font-semibold">Réessayer</button> : null}
            {!serveurAbsent ? <Link to="/connexion" className="inline-flex min-h-cible items-center rounded-controle bg-accent px-4 font-semibold text-canvas no-underline">Se connecter</Link> : null}
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="fixed inset-0 z-[60] grid place-items-center bg-ink/45 p-4" onClick={onFerme}>
      <form role="dialog" aria-modal="true" aria-labelledby="titre-event" onClick={(e) => e.stopPropagation()} onSubmit={(e) => void envoyer(e)} className="flex w-full max-w-lg flex-col gap-3 rounded-2xl bg-canvas p-5">
        <h2 id="titre-event" className="m-0 text-xl">Créer un événement</h2>
        <label className="flex flex-col gap-1 text-sm font-semibold">Titre
          <input name="titre" required maxLength={200} placeholder="Ex. Atelier Laravel à Cotonou" className="min-h-cible rounded-controle border border-line px-3 font-normal" />
        </label>
        <label className="flex flex-col gap-1 text-sm font-semibold">Description
          <textarea name="description" required maxLength={10000} placeholder="Décrivez le sujet et ce que les participants vont apprendre…" rows={3} className="rounded-controle border border-line p-3 font-normal" />
        </label>
        <label className="flex flex-col gap-1 text-sm font-semibold">Date et heure
          <input name="date" required type="datetime-local" className="min-h-cible rounded-controle border border-line px-3 font-normal" />
        </label>
        <label className="flex flex-col gap-1 text-sm font-semibold">Format
          <select name="type" required value={type} onChange={(e) => { setType(e.target.value as 'en_ligne' | 'presentiel' | ''); setErreur(null) }} className="champ-select font-normal"><option value="">Choisir un format</option><option value="en_ligne">En ligne</option><option value="presentiel">Présentiel</option></select>
        </label>
        {type === 'presentiel' ? (
          <label className="flex flex-col gap-1 text-sm font-semibold">Lieu
            <input name="lieu" required maxLength={200} placeholder="Ex. Cotonou, Haie Vive" className="min-h-cible rounded-controle border border-line px-3 font-normal" />
          </label>
        ) : type === 'en_ligne' ? (
          <label className="flex flex-col gap-1 text-sm font-semibold">Lien de la rencontre
            <input name="lien" required type="url" placeholder="https://meet.google.com/abc-defg-hij" className="min-h-cible rounded-controle border border-line px-3 font-normal" />
          </label>
        ) : <p className="m-0 rounded-xl bg-page p-3 text-sm text-muted">Le lieu ou le lien s’affichera après le choix du format.</p>}
        {erreur ? <p role="alert" className="m-0 text-danger">{erreur}</p> : null}
        <div className="flex justify-end gap-2">
          <button type="button" onClick={onFerme} className="min-h-cible rounded-controle border border-line px-4">Annuler</button>
          <button type="submit" disabled={envoi} className="min-h-cible rounded-controle bg-accent px-4 font-semibold text-canvas disabled:cursor-wait disabled:opacity-70">{envoi ? 'Création en cours…' : 'Créer l’événement'}</button>
        </div>
      </form>
    </div>
  )
}
