import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router'
import type { components } from '../../shared/types/api'
import { messageAction } from '../echange/api'
import { categories, fcfa, publierAppli, verifierDepot, type Categorie } from './api'

type Techno = components['schemas']['Techno']

const technos: Techno[] = ['laravel', 'php', 'react', 'vue', 'node', 'mysql', 'postgresql', 'autre']

export function Publier() {
  const naviguer = useNavigate()
  const [depot, setDepot] = useState('')
  const [nom, setNom] = useState('')
  const [description, setDescription] = useState('')
  const [categorie, setCategorie] = useState<Categorie | ''>('')
  const [prix, setPrix] = useState('')
  const [typePrix, setTypePrix] = useState<'mensuel' | 'unique' | ''>('')
  const [taille, setTaille] = useState<'petite' | 'moyenne' | 'grande' | ''>('')
  const [stack, setStack] = useState<Techno[]>([])
  const [captures, setCaptures] = useState<File[]>([])
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
      setErreur(messageAction(erreurHttp, 'Le dépôt n’a pas passé la vérification.'))
    }
  }

  async function envoyer(evenement: FormEvent) {
    evenement.preventDefault()
    setEnvoi(true)
    setErreur(null)
    try {
      if (!categorie || prix === '' || !typePrix || !taille || stack.length === 0) {
        setErreur('Renseignez la catégorie, le prix, le type de paiement, la taille d’hébergement et au moins une technologie.')
        return
      }
      if (captures.length < 1 || captures.length > 5) {
        setErreur('Ajoutez entre 1 et 5 images (PNG, JPEG ou WebP).')
        return
      }
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
        captures,
      })
      void naviguer(`/store/${creee.id}`)
    } catch (erreurHttp) {
      setErreur(messageAction(erreurHttp, 'La publication a échoué.'))
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <div className="mx-auto grid max-w-[1320px] items-start gap-6 px-4 py-8 min-[761px]:px-9 min-[961px]:grid-cols-[minmax(0,1fr)_320px]">
      <form onSubmit={(evenement) => void envoyer(evenement)} className="flex flex-col gap-4">
        <nav aria-label="Fil d'Ariane" className="text-sm text-muted"><Link to="/store" className="text-muted">Store</Link> <span aria-hidden="true">/</span> Publier</nav>
        <h1 className="m-0 text-[30px] font-bold tracking-tight">Publier une appli</h1>
        <p className="m-0 text-muted">Publier est gratuit et ne lance aucun serveur. Votre appli tournera seulement quand un client la déploiera.</p>
        <section className="verre flex flex-col gap-3 rounded-[18px] p-6">
          <div className="flex items-center gap-2.5">
            <Etape numero="1" fait={Boolean(verif)} />
            <h2 className="m-0 text-lg">Le dépôt GitHub</h2>
          </div>
          <p className="m-0 text-sm text-muted">Un dépôt public, avec un docker-compose.yml et un soukdev.json à la racine.</p>
        <label className="flex flex-col gap-1 font-semibold">Adresse du dépôt
          <input value={depot} onChange={(e) => setDepot(e.target.value)} required className="min-h-cible w-full rounded-controle border border-line bg-canvas px-3 font-normal" placeholder="https://github.com/orga/appli" />
        </label>
        <button type="button" onClick={() => void verifier()} className="min-h-cible w-fit rounded-controle border border-line bg-page px-4 font-semibold">Vérifier le dépôt</button>
        {verif ? <p role="status" className="m-0 text-[#166534]">{verif}</p> : null}
        </section>
        <section className="verre flex flex-col gap-3 rounded-[18px] p-6">
          <div className="flex items-center gap-2.5">
            <Etape numero="2" fait={false} />
            <h2 className="m-0 text-lg">Analyse de sécurité</h2>
          </div>
          <p className="m-0 text-sm text-muted">La vérification du dépôt contrôle l’accès, le docker-compose et les secrets. L’analyse des dépendances démarre après la publication, et le badge apparaît alors sur la fiche.</p>
        </section>
        <section className="verre flex flex-col gap-3 rounded-[18px] p-6">
          <div className="flex items-center gap-2.5">
            <Etape numero="3" fait={nom.trim().length > 0 && description.trim().length > 0} />
            <h2 className="m-0 text-lg">Les infos</h2>
          </div>
        <label className="flex flex-col gap-1 font-semibold">Nom
          <input value={nom} onChange={(e) => setNom(e.target.value)} required className="min-h-cible w-full rounded-controle border border-line bg-canvas px-3 font-normal" />
        </label>
        <label className="flex flex-col gap-1 font-semibold">Description
          <textarea value={description} onChange={(e) => setDescription(e.target.value)} required rows={4} className="w-full rounded-controle border border-line bg-canvas p-3 font-normal" />
        </label>
        <label className="flex flex-col gap-1 font-semibold">URL de l’application en ligne
          <input value={demo} onChange={(e) => setDemo(e.target.value)} required type="url" placeholder="https://votre-domaine.bj" className="min-h-cible w-full rounded-controle border border-line bg-canvas px-3 font-normal" />
        </label>
        <label className="flex flex-col gap-1 font-semibold">Catégorie
          <select required value={categorie} onChange={(e) => setCategorie(e.target.value as Categorie | '')} className="champ-select mt-1 w-full">
            <option value="">Choisir une catégorie</option>
            {categories.map((item) => <option key={item.valeur} value={item.valeur}>{item.libelle}</option>)}
          </select>
        </label>
        <div className="grid gap-3 min-[761px]:grid-cols-2">
          <label className="flex flex-col gap-1 font-semibold">Prix (FCFA)
            <input value={prix} onChange={(e) => setPrix(e.target.value)} type="number" min="0" step="1" required inputMode="numeric" placeholder="0 si l’application est gratuite" className="min-h-cible w-full rounded-controle border border-line bg-canvas px-3 font-normal" />
          </label>
          <label className="flex flex-col gap-1 font-semibold">Type
            <select required value={typePrix} onChange={(e) => setTypePrix(e.target.value as 'mensuel' | 'unique' | '')} className="champ-select mt-1 w-full">
              <option value="">Choisir le paiement</option>
              <option value="mensuel">Mensuel</option>
              <option value="unique">Paiement unique</option>
            </select>
          </label>
        </div>
        <div role="group" aria-label="Taille d’hébergement" className="font-semibold">Taille d’hébergement
          {!taille ? <span className="block text-sm font-normal text-muted">Choisissez une formule avant de publier.</span> : null}
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
        <div className="font-semibold">Les captures
          <input
            type="file"
            accept="image/png,image/jpeg,image/webp"
            multiple
            className="mt-1 block w-full text-sm font-normal"
            onChange={(e) => setCaptures([...(e.target.files ?? [])].slice(0, 5))}
          />
          <span className="mt-1 block text-sm font-normal text-muted">{captures.length === 0 ? '1 à 5 images. La première est la couverture.' : captures.map((fichier) => fichier.name).join(', ')}</span>
        </div>
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
      <aside className="flex flex-col gap-3 rounded-2xl border border-line bg-canvas p-5">
        <h2 className="m-0 text-lg font-bold">Récapitulatif de publication</h2>
        <dl className="m-0 grid grid-cols-[auto_1fr] gap-x-3 gap-y-2 text-sm">
          <dt className="text-muted">Appli</dt><dd className="m-0 font-semibold">{nom || 'À renseigner'}</dd>
          <dt className="text-muted">Catégorie</dt><dd className="m-0">{categories.find((item) => item.valeur === categorie)?.libelle ?? 'À choisir'}</dd>
          <dt className="text-muted">Prix</dt><dd className="m-0">{prix === '' ? 'À renseigner' : `${fcfa(Number(prix))}${typePrix === 'mensuel' ? '/mois' : typePrix === 'unique' ? ' · paiement unique' : ''}`}</dd>
          <dt className="text-muted">Hébergement</dt><dd className="m-0">{taille ? `${taille} · ${taille === 'petite' ? '3 500' : taille === 'moyenne' ? '7 000' : '13 500'} F/mois` : 'À choisir'}</dd>
          <dt className="text-muted">Dépôt</dt><dd className="m-0 break-all">{depot || 'URL GitHub publique requise'}</dd>
          <dt className="text-muted">Application en ligne</dt><dd className="m-0 break-all">{demo || 'URL HTTPS requise'}</dd>
          <dt className="text-muted">Captures</dt><dd className="m-0">{captures.length}/5{captures[0] ? ` · couverture : ${captures[0].name}` : ' · au moins une requise'}</dd>
        </dl>
      </aside>
    </div>
  )
}

function Etape({ numero, fait }: { numero: string; fait: boolean }) {
  return (
    <span aria-hidden="true" className={`grid size-7 place-items-center rounded-full text-sm font-bold ${fait ? 'bg-success text-canvas' : 'bg-nav text-accent'}`}>
      {fait ? '✓' : numero}
    </span>
  )
}
