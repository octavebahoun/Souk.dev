import { useState, type FormEvent, type ReactNode } from 'react'
import { useNavigate } from 'react-router'
import { Check, ImagePlus, ShieldCheck } from 'lucide-react'
import type { components } from '../../shared/types/api'
import { useTitrePage } from '../../app/titre'
import { lireErreurHttp, messageAction } from '../echange/api'
import { formules } from '../paiements/formules'
import { categories, fcfa, publierAppli, redigerSoukdev, verifierDepot, type Categorie, type RedactionSoukdev } from './api'
import { DemandeSoukdev, PropositionSoukdev } from './PropositionSoukdev'
import { Alerte, Bouton, Carte, Champ, ChoixCartes, EnTetePage, Liste, PuceFiltre, Saisie, SaisieMontant, ZoneTexte, cx } from '../../ui'

type Techno = components['schemas']['Techno']
type Taille = 'petite' | 'moyenne' | 'grande'

const technos: Techno[] = ['laravel', 'php', 'react', 'vue', 'node', 'mysql', 'postgresql', 'autre']

/**
 * Publier une appli : quatre étapes (dépôt vérifié, infos, prix et hébergement, captures et stack)
 * et un récapitulatif qui dit ce qui manque. Publier ne lance aucun serveur.
 */
export function Publier() {
  useTitrePage('Publier une appli')
  const naviguer = useNavigate()
  const [depot, setDepot] = useState('')
  const [nom, setNom] = useState('')
  const [description, setDescription] = useState('')
  const [categorie, setCategorie] = useState<Categorie | ''>('')
  const [prix, setPrix] = useState('')
  const [typePrix, setTypePrix] = useState<'mensuel' | 'unique' | null>(null)
  const [taille, setTaille] = useState<Taille | null>(null)
  const [stack, setStack] = useState<Techno[]>([])
  const [captures, setCaptures] = useState<File[]>([])
  const [demo, setDemo] = useState('')
  const [verif, setVerif] = useState<string | null>(null)
  const [erreurDepot, setErreurDepot] = useState<string | null>(null)
  const [verification, setVerification] = useState(false)
  // Proposition de soukdev.json pour un dépôt qui n'en a pas encore.
  const [redaction, setRedaction] = useState<RedactionSoukdev | null>(null)
  const [redactionEnCours, setRedactionEnCours] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)
  // Erreurs par champ : vérifiées ici avant l'envoi, ou renvoyées par le serveur (422).
  const [champs, setChamps] = useState<Record<string, string>>({})
  const [envoi, setEnvoi] = useState(false)

  // « soukdev.json est absent… », « docker-compose.yml est absent… » : Souk.dev peut l'écrire.
  const fichierManquant = erreurDepot !== null && /(soukdev\.json|docker-compose\.yml) est absent/.test(erreurDepot)
  const prixNombre = prix === '' ? null : Number(prix.replace(/\s/g, ''))
  const etapes = {
    depot: verif !== null,
    infos: nom.trim() !== '' && description.trim() !== '' && demo.trim() !== '' && categorie !== '',
    prix: prixNombre !== null && typePrix !== null && taille !== null,
    medias: captures.length >= 1 && stack.length > 0,
  }
  const complet = etapes.depot && etapes.infos && etapes.prix && etapes.medias

  async function verifier() {
    setErreurDepot(null)
    setVerification(true)
    try {
      const recu = await verifierDepot(depot.trim())
      setVerif(`Dépôt valide : service web « ${recu.service_web.nom} », backend ${recu.backend}.`)
    } catch (erreurHttp) {
      setVerif(null)
      setErreurDepot(messageAction(erreurHttp, 'Le dépôt n’a pas passé la vérification.'))
    } finally {
      setVerification(false)
    }
  }

  async function rediger() {
    setErreurDepot(null)
    setRedaction(null)
    setRedactionEnCours(true)
    try {
      setRedaction(await redigerSoukdev(depot.trim()))
    } catch (erreurHttp) {
      setErreurDepot(messageAction(erreurHttp, 'Le soukdev.json n’a pas pu être écrit.'))
    } finally {
      setRedactionEnCours(false)
    }
  }

  async function envoyer(evenement: FormEvent) {
    evenement.preventDefault()
    setErreur(null)
    const manquants: Record<string, string> = {}
    if (!depot.trim()) manquants.depot_url = 'Indiquez l’adresse du dépôt GitHub public.'
    if (!nom.trim()) manquants.nom = 'Donnez un nom à l’appli.'
    if (!description.trim()) manquants.description = 'Décrivez ce que fait l’appli, pour qui.'
    if (!/^https:\/\/\S+\.\S+/.test(demo.trim())) manquants.demo_url = 'Indiquez l’adresse de la démo, en https://.'
    if (!categorie) manquants.categorie = 'Choisissez une catégorie.'
    if (prixNombre === null || Number.isNaN(prixNombre)) manquants.prix = 'Indiquez le prix, 0 si l’appli est gratuite.'
    setChamps(manquants)
    if (Object.keys(manquants).length > 0 || !categorie || prixNombre === null) {
      setErreur('Il manque des informations : corrigez les champs signalés.')
      return
    }
    if (!typePrix || !taille || stack.length === 0) {
      setErreur('Choisissez le paiement, la taille d’hébergement et au moins une technologie.')
      return
    }
    if (captures.length < 1 || captures.length > 5) {
      setErreur('Ajoutez entre 1 et 5 images (PNG, JPEG ou WebP).')
      return
    }
    setEnvoi(true)
    try {
      const creee = await publierAppli({
        nom: nom.trim(),
        description: description.trim(),
        depot_url: depot.trim(),
        demo_url: demo.trim(),
        categorie,
        prix: prixNombre,
        type_prix: typePrix,
        taille,
        stack,
        captures,
      })
      void naviguer(`/store/${creee.id}`)
    } catch (erreurHttp) {
      // Les erreurs de champ du serveur (422) vont sous leur champ.
      setChamps(lireErreurHttp(erreurHttp).champs)
      setErreur(messageAction(erreurHttp, 'La publication a échoué.'))
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <EnTetePage titre="Publier une appli" description="Publier est gratuit et ne lance aucun serveur : votre appli tournera seulement quand un client la déploiera." />

      <div className="grid gap-6 bureau:grid-cols-[minmax(0,1fr)_18rem] bureau:items-start">
        <form onSubmit={(evenement) => void envoyer(evenement)} className="flex min-w-0 flex-col gap-5" noValidate>
          <Etape numero={1} titre="Le dépôt GitHub" fait={etapes.depot} description="Un dépôt public, avec un docker-compose.yml et un soukdev.json à la racine.">
            <div className="flex flex-col gap-3 tablette:flex-row tablette:items-end">
              <Champ libelle="Adresse du dépôt" erreur={erreurDepot ?? champs.depot_url} className="flex-1">
                {(liaison) => (
                  <Saisie
                    {...liaison}
                    value={depot}
                    onChange={(evenement) => {
                      setDepot(evenement.target.value)
                      setVerif(null)
                      setRedaction(null)
                    }}
                    required
                    className="font-mono text-petit"
                    placeholder="https://github.com/orga/appli"
                  />
                )}
              </Champ>
              <Bouton variante="secondaire" chargement={verification} disabled={depot.trim() === ''} onClick={() => void verifier()}>
                Vérifier le dépôt
              </Bouton>
            </div>
            {verif ? (
              <Alerte ton="succes" titre="Dépôt vérifié">
                {verif}
              </Alerte>
            ) : fichierManquant ? (
              <Alerte
                ton="info"
                titre="Il manque un fichier à votre dépôt"
                action={
                  <Bouton taille="sm" chargement={redactionEnCours} onClick={() => void rediger()}>
                    Écrire les fichiers manquants
                  </Bouton>
                }
              >
                Souk.dev peut l’écrire à partir de votre code : vous n’aurez plus qu’à l’ajouter sur GitHub.
              </Alerte>
            ) : (
              <DemandeSoukdev desactive={depot.trim() === '' || verification} chargement={redactionEnCours} onDemander={() => void rediger()} />
            )}
            {redaction && !verif ? <PropositionSoukdev redaction={redaction} /> : null}
            <p className="m-0 flex gap-2 text-petit text-texte-doux">
              <ShieldCheck aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
              La vérification contrôle l’accès, le docker-compose et les secrets. L’analyse des dépendances démarre après la publication ; le badge apparaît alors sur la fiche.
            </p>
          </Etape>

          <Etape numero={2} titre="Les infos" fait={etapes.infos}>
            <Champ libelle="Nom" erreur={champs.nom}>
              {(liaison) => <Saisie {...liaison} value={nom} onChange={(evenement) => setNom(evenement.target.value)} required maxLength={80} />}
            </Champ>
            <Champ libelle="Description" erreur={champs.description} aide="Ce que fait l’appli, pour qui : c’est ce que lit un client dans le Store.">
              {(liaison) => <ZoneTexte {...liaison} value={description} onChange={(evenement) => setDescription(evenement.target.value)} required rows={4} />}
            </Champ>
            <div className="grid gap-4 tablette:grid-cols-2">
              <Champ libelle="URL de l’application en ligne" erreur={champs.demo_url} aide="Sa démo, en HTTPS.">
                {(liaison) => <Saisie {...liaison} value={demo} onChange={(evenement) => setDemo(evenement.target.value)} required type="url" placeholder="https://votre-domaine.bj" />}
              </Champ>
              <Champ libelle="Catégorie" erreur={champs.categorie}>
                {(liaison) => (
                  <Liste {...liaison} required value={categorie} onChange={(evenement) => setCategorie(evenement.target.value as Categorie | '')}>
                    <option value="">Choisir une catégorie</option>
                    {categories.map((item) => (
                      <option key={item.valeur} value={item.valeur}>
                        {item.libelle}
                      </option>
                    ))}
                  </Liste>
                )}
              </Champ>
            </div>
          </Etape>

          <Etape numero={3} titre="Prix et hébergement" fait={etapes.prix} description="Le prix vous revient en entier ; le client paie en plus l’hébergement de sa copie.">
            <div className="grid gap-4 tablette:grid-cols-[14rem_minmax(0,1fr)] tablette:items-start">
              <Champ libelle="Prix de l’appli" erreur={champs.prix} aide="0 si elle est gratuite.">
                {(liaison) => (
                  <SaisieMontant
                    {...liaison}
                    value={prix}
                    onChange={(evenement) => {
                      const saisi = evenement.target.value
                      // Un signe moins ou une lettre ne disparaît pas en silence : le champ dit ce qu'il attend.
                      setChamps((courants) => ({ ...courants, prix: /[^\d\s]/.test(saisi) ? 'Seulement des chiffres : un nombre entier de francs, sans signe ni virgule.' : '' }))
                      setPrix(saisi.replace(/[^\d ]/g, ''))
                    }}
                    required
                    placeholder="ex. 15 000"
                  />
                )}
              </Champ>
              <ChoixCartes
                nom="type-prix"
                legende="Paiement"
                colonnes={2}
                valeur={typePrix}
                onChange={setTypePrix}
                options={[
                  { valeur: 'mensuel', libelle: 'Mensuel' },
                  { valeur: 'unique', libelle: 'Une fois' },
                ]}
              />
            </div>
            <ChoixCartes
              nom="taille"
              legende="Taille d’hébergement recommandée"
              colonnes={1}
              valeur={taille}
              onChange={setTaille}
              options={(Object.keys(formules) as Taille[]).map((cle) => ({
                valeur: cle,
                libelle: formules[cle].nom,
                description: `${formules[cle].memoire} · ${formules[cle].coeurs}`,
                complement: `${fcfa(formules[cle].prix)} / mois`,
              }))}
            />
          </Etape>

          <Etape numero={4} titre="Captures et stack" fait={etapes.medias}>
            <div className="flex flex-col gap-2">
              <span className="text-petit font-medium text-titre">Captures</span>
              <div className="flex flex-wrap items-center gap-3">
                <input
                  id="captures-appli"
                  type="file"
                  accept="image/png,image/jpeg,image/webp"
                  multiple
                  aria-describedby="captures-aide"
                  className="peer pour-lecteur"
                  onChange={(evenement) => setCaptures([...(evenement.target.files ?? [])].slice(0, 5))}
                />
                <label
                  htmlFor="captures-appli"
                  className="inline-flex h-11 cursor-pointer items-center gap-2 rounded-moyen border border-bordure-champ bg-surface px-4 text-corps font-medium text-titre shadow-1 hover:bg-surface-douce peer-focus-visible:outline-3 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-lien tablette:h-10"
                >
                  <ImagePlus aria-hidden="true" className="size-4" />
                  Choisir des images
                </label>
                <span className="text-petit text-texte">{captures.length === 0 ? 'Aucune image choisie' : `${captures.length} image${captures.length > 1 ? 's' : ''} choisie${captures.length > 1 ? 's' : ''}`}</span>
              </div>
              <p id="captures-aide" className="m-0 text-petit text-texte-doux">
                1 à 5 images (PNG, JPEG ou WebP). La première sert de couverture.
              </p>
              {captures.length > 0 ? (
                <ol className="m-0 flex list-decimal flex-col gap-0.5 pl-5 text-petit text-texte">
                  {captures.map((fichier, index) => (
                    <li key={`${fichier.name}-${index}`} className="break-all">
                      {fichier.name}
                      {index === 0 ? <span className="text-texte-doux"> (couverture)</span> : null}
                    </li>
                  ))}
                </ol>
              ) : null}
            </div>
            <fieldset className="m-0 flex flex-col gap-2 border-0 p-0">
              <legend className="mb-2 text-petit font-medium text-titre">La stack</legend>
              <div className="flex flex-wrap gap-2">
                {technos.map((techno) => (
                  <PuceFiltre key={techno} presse={stack.includes(techno)} onClick={() => setStack((liste) => (liste.includes(techno) ? liste.filter((item) => item !== techno) : [...liste, techno]))}>
                    {techno}
                  </PuceFiltre>
                ))}
              </div>
            </fieldset>
          </Etape>

          {erreur ? (
            <Alerte ton="danger" urgente>
              {erreur}
            </Alerte>
          ) : null}
          <div className="flex flex-col items-stretch gap-2 tablette:items-end">
            <Bouton type="submit" taille="lg" chargement={envoi} disabled={!complet} aria-describedby={complet ? undefined : 'publier-incomplet'}>
              Publier l’appli
            </Bouton>
            {complet ? null : (
              <p id="publier-incomplet" className="m-0 text-petit text-texte-doux">
                Complétez les quatre étapes pour publier : le récapitulatif dit ce qui manque.
              </p>
            )}
          </div>
        </form>

        <aside aria-labelledby="titre-recap" className="bureau:sticky bureau:top-24">
          <Carte className="flex flex-col gap-4">
            <h2 id="titre-recap" className="m-0 text-titre-3 text-titre">
              Récapitulatif
            </h2>
            <ol className="m-0 flex list-none flex-col gap-2 p-0 text-petit">
              {(
                [
                  ['Dépôt vérifié', etapes.depot],
                  ['Infos complètes', etapes.infos],
                  ['Prix et hébergement', etapes.prix],
                  ['Captures et stack', etapes.medias],
                ] as const
              ).map(([libelle, fait]) => (
                <li key={libelle} className="flex items-center gap-2">
                  <span aria-hidden="true" className={cx('grid size-5 place-items-center rounded-pastille', fait ? 'bg-succes-plein text-sur-couleur' : 'border border-bordure-champ')}>
                    {fait ? <Check className="size-3.5" /> : null}
                  </span>
                  <span className={fait ? 'text-titre' : 'text-texte-doux'}>
                    {libelle}
                    <span className="pour-lecteur">{fait ? ' : fait' : ' : à faire'}</span>
                  </span>
                </li>
              ))}
            </ol>
            <dl className="m-0 flex flex-col gap-2 border-t border-bordure pt-4 text-petit">
              <Ligne terme="Appli" valeur={nom || 'À renseigner'} />
              <Ligne terme="Catégorie" valeur={categories.find((item) => item.valeur === categorie)?.libelle ?? 'À choisir'} />
              <Ligne terme="Prix" valeur={prixNombre === null ? 'À renseigner' : `${prixNombre === 0 ? 'Gratuit' : fcfa(prixNombre)}${prixNombre > 0 && typePrix === 'mensuel' ? ' / mois' : prixNombre > 0 && typePrix === 'unique' ? ' une fois' : ''}`} />
              <Ligne terme="Hébergement" valeur={taille ? `${formules[taille].nom}, ${fcfa(formules[taille].prix)} / mois` : 'À choisir'} />
              {prixNombre !== null && !Number.isNaN(prixNombre) && taille ? (
                <>
                  <Ligne terme="Le client paie" valeur={`${fcfa(formules[taille].prix + (typePrix === 'unique' ? 0 : prixNombre))} / mois${typePrix === 'unique' && prixNombre > 0 ? ` + ${fcfa(prixNombre)} une fois` : ''}`} />
                  <Ligne terme="Vous recevez" valeur={prixNombre === 0 ? 'Rien : appli gratuite' : `${fcfa(prixNombre)}${typePrix === 'unique' ? ' par client, une fois' : ' par client et par mois'}, sans commission`} />
                </>
              ) : null}
              <Ligne terme="Captures" valeur={`${captures.length} sur 5`} />
            </dl>
          </Carte>
        </aside>
      </div>
    </div>
  )
}

function Etape({ numero, titre, description, fait, children }: { numero: number; titre: string; description?: string; fait: boolean; children: ReactNode }) {
  return (
    <Carte as="section" aria-labelledby={`etape-${numero}`} className="flex flex-col gap-4">
      <div className="flex items-start gap-3">
        <span aria-hidden="true" className={cx('chiffres grid size-7 shrink-0 place-items-center rounded-pastille text-petit font-semibold', fait ? 'bg-succes-plein text-sur-couleur' : 'bg-primaire-doux text-sur-primaire-doux')}>
          {fait ? <Check className="size-4" /> : numero}
        </span>
        <div className="flex flex-col gap-0.5">
          <h2 id={`etape-${numero}`} className="m-0 text-titre-3 text-titre">
            {titre}
          </h2>
          {description ? <p className="m-0 text-petit text-texte-doux">{description}</p> : null}
        </div>
      </div>
      {children}
    </Carte>
  )
}

function Ligne({ terme, valeur }: { terme: string; valeur: string }) {
  return (
    <div className="flex justify-between gap-3">
      <dt className="text-texte-doux">{terme}</dt>
      <dd className="m-0 text-right font-medium break-all text-titre">{valeur}</dd>
    </div>
  )
}
