import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router'
import type { components } from '../../shared/types/api'
import { lireErreurHttp } from '../echange/api'
import { categories, fcfa, publierAppli, verifierDepot, type Categorie } from './api'
import { Fenetre } from './Fenetre'

type Techno = components['schemas']['Techno']

const technos: Techno[] = ['laravel', 'php', 'react', 'vue', 'node', 'mysql', 'postgresql', 'autre']

export function Publier() {
  const naviguer = useNavigate()
  const [depot, setDepot] = useState('')
  const [nom, setNom] = useState('')
  const [description, setDescription] = useState('')
  const [categorie, setCategorie] = useState<Categorie>('commerce')
  const [prix, setPrix] = useState('15000')
  const [typePrix, setTypePrix] = useState<'mensuel' | 'unique'>('mensuel')
  const [taille, setTaille] = useState<'petite' | 'moyenne' | 'grande'>('moyenne')
  const [stack, setStack] = useState<Techno[]>(['laravel'])
  const [demo, setDemo] = useState('')
  const [verif, setVerif] = useState<string | null>(null)
  const [erreur, setErreur] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)

  async function verifier() {
    setErreur(null)
    try {
      const recu = await verifierDepot(depot.trim())
      setVerif(`Dépôt valide. Service ${recu.service_web.nom}, backend ${recu.backend}.`)
    } catch (erreurHttp) {
      setVerif(null)
      setErreur(lireErreurHttp(erreurHttp, 'Le dépôt n’a pas passé la vérification.').message)
    }
  }

  async function envoyer(evenement: FormEvent) {
    evenement.preventDefault()
    setEnvoi(true)
    setErreur(null)
    try {
      const creee = await publierAppli({
        nom: nom.trim(),
        description: description.trim(),
        depot_url: depot.trim(),
        demo_url: demo.trim(),
        categorie,
        prix: Number(prix),
        type_prix: typePrix,
        taille,
        stack,
      })
      void naviguer(`/store/${creee.id}`)
    } catch (erreurHttp) {
      setErreur(lireErreurHttp(erreurHttp, 'La publication a échoué.').message)
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <div className="mx-auto grid max-w-[1100px] items-start gap-6 px-4 py-8 min-[961px]:grid-cols-[minmax(0,1fr)_280px]">
      <form onSubmit={(evenement) => void envoyer(evenement)} className="flex flex-col gap-4">
        <h1 className="m-0 text-[30px] font-bold">Publier une appli</h1>
        <p className="m-0 text-muted">Dépôt public, soukdev.json, prix. Publier ne coûte rien.</p>
        <section className="flex flex-col gap-3 rounded-[18px] border border-line bg-canvas p-5">
          <h2 className="m-0 text-lg">Le dépôt</h2>
        <label className="flex flex-col gap-1 font-semibold">Adresse du dépôt
          <input value={depot} onChange={(e) => setDepot(e.target.value)} required className="min-h-cible w-full rounded-controle border border-line bg-canvas px-3 font-normal" placeholder="https://github.com/orga/appli" />
        </label>
        <button type="button" onClick={() => void verifier()} className="min-h-cible w-fit rounded-controle border border-line bg-page px-4 font-semibold">Vérifier le dépôt</button>
        {verif ? <p role="status" className="m-0 text-[#166534]">{verif}</p> : null}
        </section>
        <section className="flex flex-col gap-3 rounded-[18px] border border-line bg-canvas p-5">
          <h2 className="m-0 text-lg">Les infos</h2>
        <label className="flex flex-col gap-1 font-semibold">Nom
          <input value={nom} onChange={(e) => setNom(e.target.value)} required className="min-h-cible w-full rounded-controle border border-line bg-canvas px-3 font-normal" />
        </label>
        <label className="flex flex-col gap-1 font-semibold">Description
          <textarea value={description} onChange={(e) => setDescription(e.target.value)} required rows={4} className="w-full rounded-controle border border-line bg-canvas p-3 font-normal" />
        </label>
        <label className="flex flex-col gap-1 font-semibold">Démo
          <input value={demo} onChange={(e) => setDemo(e.target.value)} required type="url" placeholder="https://demo.exemple.dev" className="min-h-cible w-full rounded-controle border border-line bg-canvas px-3 font-normal" />
        </label>
        <label className="flex flex-col gap-1 font-semibold">Catégorie
          <select value={categorie} onChange={(e) => setCategorie(e.target.value as Categorie)} className="champ-select mt-1 w-full">
            {categories.map((item) => <option key={item.valeur} value={item.valeur}>{item.libelle}</option>)}
          </select>
        </label>
        <div className="grid gap-3 min-[761px]:grid-cols-2">
          <label className="flex flex-col gap-1 font-semibold">Prix (FCFA)
            <input value={prix} onChange={(e) => setPrix(e.target.value.replace(/\D/g, ''))} className="min-h-cible w-full rounded-controle border border-line bg-canvas px-3 font-normal" />
          </label>
          <label className="flex flex-col gap-1 font-semibold">Type
            <select value={typePrix} onChange={(e) => setTypePrix(e.target.value as 'mensuel' | 'unique')} className="champ-select mt-1 w-full">
              <option value="mensuel">Mensuel</option>
              <option value="unique">Paiement unique</option>
            </select>
          </label>
        </div>
        <div className="font-semibold">Taille
          <span className="mt-1 grid grid-cols-3 gap-2 font-normal">
            {([
              ['petite', 'Petite', '3 500 F'],
              ['moyenne', 'Moyenne', '7 000 F'],
              ['grande', 'Grande', '13 500 F'],
            ] as const).map(([valeur, libelle, montant]) => (
              <button key={valeur} type="button" aria-pressed={taille === valeur} onClick={() => setTaille(valeur)} className={`min-h-cible rounded-controle border px-2 text-sm ${taille === valeur ? 'border-accent bg-nav font-semibold text-accent' : 'border-line bg-canvas'}`}>
                {libelle}<br />{montant}
              </button>
            ))}
          </span>
        </div>
        <p className="m-0 text-sm text-muted">Les captures (1 à 5 images) sont exigées par l’API. Ce formulaire ne les envoie pas encore : la publication répondra une erreur tant qu’elles manquent.</p>
        <fieldset className="m-0 border-0 p-0">
          <legend className="font-semibold">La stack</legend>
          <div className="mt-2 flex flex-wrap gap-2">
            {technos.map((techno) => (
              <label key={techno} className="inline-flex min-h-cible items-center gap-2 rounded-full border border-line bg-canvas px-3">
                <input
                  type="checkbox"
                  checked={stack.includes(techno)}
                  onChange={() => setStack((liste) => (liste.includes(techno) ? liste.filter((item) => item !== techno) : [...liste, techno]))}
                />
                {techno}
              </label>
            ))}
          </div>
        </fieldset>
        </section>
        {erreur ? <p role="alert" className="m-0 text-danger">{erreur}</p> : null}
        <button type="submit" disabled={envoi} className="min-h-[52px] rounded-controle bg-accent font-bold text-canvas">Publier</button>
      </form>
      <aside className="rounded-2xl border border-line bg-canvas p-4">
        <p className="m-0 text-xs font-bold tracking-wide text-muted uppercase">Aperçu dans le store</p>
        <div className="mt-3 overflow-hidden rounded-xl">
          <Fenetre nom={nom || 'Votre appli'} categorie={categorie} />
        </div>
        <p className="m-0 mt-3 font-bold">{nom || 'Votre appli'}</p>
        <p className="m-0 text-sm text-muted">{fcfa(Number(prix) || 0)}{typePrix === 'mensuel' ? '/mois' : ''}</p>
      </aside>
    </div>
  )
}
