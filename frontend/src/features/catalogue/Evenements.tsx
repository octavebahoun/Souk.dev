import { useState, type FormEvent } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { creerEvenement, desinscrire, inscrire, listerEvenements, type Evenement } from './api'
import { messageAction } from '../echange/api'

const couleurs = ['#F97362', '#1F4FE0', '#16A34A', '#D97706']

function Cube({ classe, texte }: { classe: string; texte: string }) {
  return (
    <span aria-hidden="true" className={`absolute hidden size-16 rotate-12 place-items-center rounded-xl text-center text-[11px] font-extrabold tracking-widest text-white shadow-lg min-[761px]:grid ${classe}`}>
      {texte}
    </span>
  )
}

export function Evenements() {
  const query = useQuery({ queryKey: ['evenements'], queryFn: () => listerEvenements() })
  const client = useQueryClient()
  const [filtre, setFiltre] = useState<'tous' | 'en_ligne' | 'presentiel'>('tous')
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
    <main className="mx-auto flex max-w-[1200px] flex-col gap-6 px-4 py-8 min-[761px]:px-6">
      <div className="relative min-h-[280px] overflow-hidden rounded-[22px] bg-[radial-gradient(circle_at_20%_20%,#24386B,#0B1A3D_55%)] px-6 py-10 text-canvas">
        <Cube classe="top-6 left-4 bg-[#D946EF]" texte="SOUK" />
        <Cube classe="top-16 right-6 bg-[#312E81]" texte="CODE" />
        <Cube classe="bottom-8 left-16 bg-[#0F766E]" texte="MEET" />
        <Cube classe="right-16 bottom-6 bg-[#EA580C]" texte="ATEL" />
        <div className="relative mx-auto max-w-[640px] text-center">
          <h1 className="m-0 text-4xl font-extrabold tracking-[0.12em] min-[761px]:text-6xl">ÉVÉNEMENTS</h1>
          <p className="mx-auto mt-4 max-w-[52ch] rounded-2xl bg-white/10 px-4 py-3 text-[#E4E9F7]">Ateliers en ligne et rencontres près de chez vous, organisés par les devs de la communauté.</p>
        </div>
        <div className="relative mt-8 flex flex-wrap justify-center gap-3">
          <div role="group" aria-label="Type d'événement" className="inline-flex rounded-[10px] bg-white/15 p-[3px]">
            {([['tous', 'Tous'], ['en_ligne', 'En ligne'], ['presentiel', 'Présentiel']] as const).map(([valeur, libelle]) => (
              <button key={valeur} type="button" aria-pressed={filtre === valeur} onClick={() => setFiltre(valeur)} className={`min-h-9 rounded-lg px-3.5 text-sm font-semibold ${filtre === valeur ? 'bg-canvas text-ink' : 'bg-transparent text-canvas'}`}>{libelle}</button>
            ))}
          </div>
          <button type="button" onClick={() => setOuvert(true)} className="min-h-cible rounded-controle bg-gold px-4 font-bold text-ink">Créer un événement</button>
        </div>
      </div>
      {erreur ? <p role="alert" className="m-0 text-danger">{erreur}</p> : null}
      {query.isPending ? <div role="status" className="squelette h-32 rounded-xl" /> : null}
      {query.isError ? <button type="button" onClick={() => query.refetch()} className="min-h-cible w-fit rounded-controle bg-accent px-4 font-semibold text-canvas">Réessayer</button> : null}
      <Liste titre="À venir" items={aVenir} onBasculer={basculer} />
      <Liste titre="Passés" items={passes} onBasculer={basculer} />
      {ouvert ? <Formulaire onFerme={() => setOuvert(false)} /> : null}
    </main>
  )
}

function Liste({ titre, items, onBasculer }: { titre: string; items: Evenement[]; onBasculer: (item: Evenement) => void }) {
  if (items.length === 0) return null
  return (
    <section className="flex flex-col gap-3">
      <h2 className="m-0 text-[19px]">{titre}</h2>
      <ul className="m-0 flex list-none flex-col gap-3 p-0">
        {items.map((item, index) => {
          const date = new Date(item.date)
          return (
            <li key={item.id} className="flex flex-wrap gap-4 rounded-[14px] border border-line bg-canvas p-4">
              <div className="w-16 overflow-hidden rounded-xl border border-line text-center">
                <div className="text-xs font-bold text-canvas" style={{ background: couleurs[index % couleurs.length] }}>{date.toLocaleDateString('fr-FR', { month: 'short' })}</div>
                <div className="text-2xl font-extrabold">{date.getDate()}</div>
              </div>
              <div className="min-w-0 flex-1">
                <h3 className="m-0 text-[17px]">{item.titre}</h3>
                <p className="m-0 text-sm text-muted">{item.type === 'en_ligne' ? 'En ligne' : item.lieu || 'Présentiel'} · {item.nb_inscrits} inscrits</p>
                <p className="m-0 text-subtle">{item.description}</p>
              </div>
              <button type="button" onClick={() => onBasculer(item)} className="min-h-cible self-center rounded-controle bg-accent px-4 font-semibold text-canvas">
                {item.inscrit ? 'Se désinscrire' : "S'inscrire"}
              </button>
            </li>
          )
        })}
      </ul>
    </section>
  )
}

function Formulaire({ onFerme }: { onFerme: () => void }) {
  const client = useQueryClient()
  const [erreur, setErreur] = useState<string | null>(null)

  async function envoyer(evenement: FormEvent<HTMLFormElement>) {
    evenement.preventDefault()
    const donnees = new FormData(evenement.currentTarget)
    try {
      await creerEvenement({
        titre: String(donnees.get('titre') ?? ''),
        description: String(donnees.get('description') ?? ''),
        date: new Date(String(donnees.get('date') ?? '')).toISOString(),
        type: String(donnees.get('type')) === 'presentiel' ? 'presentiel' : 'en_ligne',
        lieu: String(donnees.get('lieu') ?? '') || undefined,
        lien: String(donnees.get('lien') ?? '') || undefined,
      })
      await client.invalidateQueries({ queryKey: ['evenements'] })
      onFerme()
    } catch (erreurHttp) {
      setErreur(messageAction(erreurHttp, 'La création a échoué.'))
    }
  }

  return (
    <div className="fixed inset-0 z-[60] grid place-items-center bg-ink/45 p-4" onClick={onFerme}>
      <form role="dialog" aria-modal="true" aria-labelledby="titre-event" onClick={(e) => e.stopPropagation()} onSubmit={(e) => void envoyer(e)} className="flex w-full max-w-lg flex-col gap-3 rounded-2xl bg-canvas p-5">
        <h2 id="titre-event" className="m-0 text-xl">Créer un événement</h2>
        <input name="titre" required placeholder="Titre" className="min-h-cible rounded-controle border border-line px-3" />
        <textarea name="description" required placeholder="Description" rows={3} className="rounded-controle border border-line p-3" />
        <input name="date" required type="datetime-local" className="min-h-cible rounded-controle border border-line px-3" />
        <select name="type" className="champ-select"><option value="en_ligne">En ligne</option><option value="presentiel">Présentiel</option></select>
        <input name="lieu" placeholder="Lieu" className="min-h-cible rounded-controle border border-line px-3" />
        <input name="lien" type="url" placeholder="Lien" className="min-h-cible rounded-controle border border-line px-3" />
        {erreur ? <p role="alert" className="m-0 text-danger">{erreur}</p> : null}
        <div className="flex justify-end gap-2">
          <button type="button" onClick={onFerme} className="min-h-cible rounded-controle border border-line px-4">Annuler</button>
          <button type="submit" className="min-h-cible rounded-controle bg-accent px-4 font-semibold text-canvas">Créer l’événement</button>
        </div>
      </form>
    </div>
  )
}
