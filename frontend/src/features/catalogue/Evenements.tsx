import { useState, type FormEvent } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router'
import { CalendarPlus, MapPin, Star, Video } from 'lucide-react'
import { useTitrePage } from '../../app/titre'
import { annulerEvenement, creerEvenement, modifierEvenement, desinscrire, inscrire, listerEvenements, posterAvis, type Evenement } from './api'
import { lireMoi, messageAction } from '../echange/api'
import { Signaler } from '../admin/Signaler'
import {
  Alerte,
  Badge,
  Bouton,
  Champ,
  ChoixCartes,
  EnTetePage,
  EtatErreur,
  EtatVide,
  LienBouton,
  Liste,
  Modale,
  ChoixPastilles,
  Saisie,
  SqueletteListe,
  ZoneTexte,
  formaterNote,
  useToast,
} from '../../ui'

type Filtre = 'tous' | 'en_ligne' | 'presentiel'

const filtres: { valeur: Filtre; libelle: string }[] = [
  { valeur: 'tous', libelle: 'Tous' },
  { valeur: 'en_ligne', libelle: 'En ligne' },
  { valeur: 'presentiel', libelle: 'Présentiel' },
]

/**
 * Événements : ateliers en ligne et rencontres. À venir d'abord (s'inscrire), puis les passés
 * (donner son avis) ; créer un événement est réservé aux comptes connectés.
 */
export function Evenements() {
  useTitrePage('Événements')
  const query = useQuery({ queryKey: ['evenements'], queryFn: () => listerEvenements() })
  const moi = useQuery({ queryKey: ['moi'], queryFn: lireMoi })
  const client = useQueryClient()
  const toast = useToast()
  const [filtre, setFiltre] = useState<Filtre>('tous')
  const [ouvert, setOuvert] = useState(false)
  // Chaque ouverture repart d'un formulaire vierge (format, erreur).
  const [ouvertures, setOuvertures] = useState(0)
  const [enModification, setEnModification] = useState<Evenement | null>(null)
  const [aAnnuler, setAAnnuler] = useState<Evenement | null>(null)
  const [annulation, setAnnulation] = useState(false)
  const [maintenant] = useState(() => Date.now())
  const liste = (query.data?.data ?? []).filter((item) => filtre === 'tous' || item.type === filtre)
  const aVenir = liste.filter((item) => new Date(item.date).getTime() >= maintenant)
  const passes = liste.filter((item) => new Date(item.date).getTime() < maintenant)

  function ouvrir(evenement: Evenement | null = null) {
    setEnModification(evenement)
    setOuvertures((nombre) => nombre + 1)
    setOuvert(true)
  }

  async function annuler() {
    if (!aAnnuler) return
    setAnnulation(true)
    try {
      await annulerEvenement(aAnnuler.id)
      await client.invalidateQueries({ queryKey: ['evenements'] })
      toast.succes(aAnnuler.nb_inscrits > 0 ? 'Événement annulé : les inscrits sont prévenus par e-mail.' : 'Événement annulé.')
      setAAnnuler(null)
    } catch (erreurHttp) {
      toast.erreur(messageAction(erreurHttp, 'L’annulation a échoué.'))
    } finally {
      setAnnulation(false)
    }
  }

  async function basculer(item: Evenement) {
    try {
      if (item.inscrit) await desinscrire(item.id)
      else await inscrire(item.id)
      await client.invalidateQueries({ queryKey: ['evenements'] })
      toast.succes(item.inscrit ? 'Inscription annulée.' : 'Vous êtes inscrit.')
    } catch (erreurHttp) {
      toast.erreur(messageAction(erreurHttp, 'L’inscription a échoué.'))
    }
  }

  return (
    <div className="mx-auto flex w-full max-w-site flex-col gap-6 px-4 py-8 tablette:px-6 bureau:px-8">
      <EnTetePage
        titre="Événements"
        description="Ateliers en ligne et rencontres près de chez vous, organisés par les devs de la communauté."
        actions={
          <Bouton icone={<CalendarPlus aria-hidden="true" className="size-4" />} onClick={() => ouvrir()}>
            Créer un événement
          </Bouton>
        }
      />

      <ChoixPastilles libelle="Type d’événement" valeur={filtre} onChange={setFiltre} options={filtres} />

      {query.isPending ? <SqueletteListe nombre={3} /> : null}
      {query.isError ? <EtatErreur description={messageAction(query.error, 'Les événements n’ont pas pu être chargés.')} onReessayer={() => void query.refetch()} /> : null}
      {query.isSuccess && aVenir.length === 0 && passes.length === 0 ? (
        <EtatVide
          illustration="vide-evenements"
          titre="Aucun événement de ce type pour l’instant"
          description="Un atelier, une rencontre, une revue de code : pourquoi pas le vôtre ?"
          action={<Bouton onClick={() => ouvrir()}>Créer un événement</Bouton>}
        />
      ) : null}

      {aVenir.length > 0 ? (
        <section aria-labelledby="titre-a-venir" className="flex flex-col gap-3">
          <h2 id="titre-a-venir" className="m-0 text-titre-3 text-titre">
            À venir
          </h2>
          <ul className="m-0 flex list-none flex-col gap-3 p-0">
            {aVenir.map((item) => (
              <CarteEvenement
                key={item.id}
                item={item}
                onBasculer={() => void basculer(item)}
                connecteId={moi.data?.id ?? null}
                onModifier={() => ouvrir(item)}
                onAnnuler={() => setAAnnuler(item)}
              />
            ))}
          </ul>
        </section>
      ) : null}

      {passes.length > 0 ? (
        <section aria-labelledby="titre-passes" className="flex flex-col gap-3">
          <h2 id="titre-passes" className="m-0 text-titre-3 text-titre">
            Passés
          </h2>
          <ul className="m-0 flex list-none flex-col gap-3 p-0">
            {passes.map((item) => (
              <CarteEvenement key={item.id} item={item} passe connecteId={moi.data?.id ?? null} />
            ))}
          </ul>
        </section>
      ) : null}

      <Formulaire key={ouvertures} ouvert={ouvert} evenement={enModification} onFermer={() => setOuvert(false)} />
      <Modale
        ouverte={aAnnuler !== null}
        onFermer={() => setAAnnuler(null)}
        taille="petite"
        titre="Annuler cet événement ?"
        description={
          aAnnuler && aAnnuler.nb_inscrits > 0
            ? `« ${aAnnuler.titre} » disparaît de la liste et ${aAnnuler.nb_inscrits === 1 ? 'la personne inscrite reçoit' : `les ${aAnnuler.nb_inscrits} inscrits reçoivent`} un e-mail d’annulation.`
            : `« ${aAnnuler?.titre ?? ''} » disparaît de la liste. Personne n’est encore inscrit.`
        }
        pied={
          <>
            <Bouton variante="contour" onClick={() => setAAnnuler(null)}>
              Garder l’événement
            </Bouton>
            <Bouton variante="danger" chargement={annulation} onClick={() => void annuler()}>
              Annuler l’événement
            </Bouton>
          </>
        }
      />
    </div>
  )
}

function CarteEvenement({
  item,
  passe = false,
  onBasculer,
  connecteId,
  onModifier,
  onAnnuler,
}: {
  item: Evenement
  passe?: boolean
  onBasculer?: () => void
  connecteId: number | null
  onModifier?: () => void
  onAnnuler?: () => void
}) {
  const organisateur = connecteId != null && connecteId === item.organisateur.id
  const date = new Date(item.date)
  const mois = date.toLocaleDateString('fr-FR', { month: 'short' }).replace('.', '')
  const heure = date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
  return (
    <li className="flex flex-col gap-4 rounded-moyen border border-bordure bg-surface p-4 shadow-1 tablette:flex-row tablette:p-5">
      <p aria-hidden="true" className="m-0 flex size-16 shrink-0 flex-col items-center justify-center rounded-moyen bg-surface-accent text-sur-primaire-doux">
        <span className="text-etiquette uppercase">{mois}</span>
        <span className="chiffres text-titre-2">{date.getDate()}</span>
      </p>
      <div className="flex min-w-0 flex-1 flex-col gap-2">
        <div className="flex flex-wrap items-center gap-2">
          <h3 className="m-0 text-titre-3 text-titre">{item.titre}</h3>
          <Badge ton={item.type === 'en_ligne' ? 'info' : 'marque'} icone={item.type === 'en_ligne' ? <Video aria-hidden="true" className="size-3.5" /> : <MapPin aria-hidden="true" className="size-3.5" />}>
            {item.type === 'en_ligne' ? 'En ligne' : 'Présentiel'}
          </Badge>
          {item.inscrit ? <Badge ton="succes">Inscrit</Badge> : null}
        </div>
        <p className="m-0 text-corps text-texte">{item.description}</p>
        <p className="m-0 text-petit text-texte-doux">
          <time dateTime={item.date}>{date.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' })} à {heure}</time>
          {' · '}
          {item.type === 'en_ligne' ? 'En ligne' : item.lieu || 'Présentiel'}
          {item.organisateur.username ? (
            <>
              {' · par '}
              <Link to={`/devs/${item.organisateur.username}`} className="font-medium underline underline-offset-2">
                {item.organisateur.nom || item.organisateur.username}
              </Link>
            </>
          ) : null}
        </p>
        {item.lien ? (
          <p className="m-0 text-petit">
            Lien : <a href={item.lien} className="font-mono break-all underline underline-offset-2">{item.lien}</a>
          </p>
        ) : null}
        {item.type === 'en_ligne' && !item.lien && !passe ? <p className="m-0 text-petit text-texte-doux">Le lien apparaît une fois inscrit.</p> : null}
        {item.note_moyenne != null ? (
          <p className="m-0 flex items-center gap-1 text-petit font-medium text-titre">
            <Star aria-hidden="true" className="size-4 fill-current text-etoile" />
            {formaterNote(item.note_moyenne)} <span className="font-normal text-texte-doux">/ 5</span>
          </p>
        ) : null}
        {/* Seuls les participants inscrits donnent leur avis, une fois l'événement passé. */}
        {passe && connecteId != null && item.inscrit ? <AvisFormulaire id={item.id} /> : null}
      </div>
      <div className="flex shrink-0 flex-row items-center justify-between gap-2 border-t border-bordure pt-3 tablette:flex-col tablette:items-end tablette:justify-start tablette:border-t-0 tablette:pt-0">
        {!passe && organisateur && onModifier && onAnnuler ? (
          <div className="flex flex-wrap gap-2 tablette:justify-end">
            <Bouton variante="contour" onClick={onModifier}>
              Modifier
            </Bouton>
            <Bouton variante="fantome" onClick={onAnnuler}>
              Annuler l’événement
            </Bouton>
          </div>
        ) : !passe && onBasculer ? (
          <Bouton variante={item.inscrit ? 'contour' : 'primaire'} onClick={onBasculer}>
            {item.inscrit ? 'Se désinscrire' : 'S’inscrire'}
          </Bouton>
        ) : null}
        <span className="chiffres text-petit text-texte-doux">
          {item.nb_inscrits} inscrit{item.nb_inscrits > 1 ? 's' : ''}
        </span>
        {connecteId != null && connecteId !== item.organisateur.id ? <Signaler cible="evenement" cibleId={item.id} etiquette="cet événement" /> : null}
      </div>
    </li>
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
    if (!note) {
      setErreur('Choisissez une note avant de publier votre avis.')
      return
    }
    try {
      await posterAvis(id, Number(note), commentaire.trim())
      setOk(true)
      await client.invalidateQueries({ queryKey: ['evenements'] })
    } catch (erreurHttp) {
      setErreur(messageAction(erreurHttp, 'L’avis n’a pas été publié.'))
    }
  }

  if (ok) return <p role="status" className="m-0 text-petit text-succes">Avis publié. Merci.</p>
  return (
    <form onSubmit={(evenement) => void envoyer(evenement)} className="mt-1 grid gap-3 border-t border-bordure pt-3 tablette:grid-cols-[10rem_minmax(0,1fr)_auto] tablette:items-end">
      <Champ libelle="Votre note" erreur={erreur}>
        {(liaison) => (
          <Liste {...liaison} value={note} onChange={(evenement) => setNote(evenement.target.value)}>
            <option value="">Choisir</option>
            {[5, 4, 3, 2, 1].map((valeur) => (
              <option key={valeur} value={valeur}>
                {valeur} / 5
              </option>
            ))}
          </Liste>
        )}
      </Champ>
      <Champ libelle="Commentaire" facultatif>
        {(liaison) => <Saisie {...liaison} value={commentaire} onChange={(evenement) => setCommentaire(evenement.target.value)} />}
      </Champ>
      <Bouton type="submit" variante="secondaire">
        Publier l’avis
      </Bouton>
    </form>
  )
}

/** Valeur d'un champ datetime-local (heure locale, à la minute). */
function dateLocale(iso: string): string {
  const date = new Date(iso)
  const decalage = date.getTimezoneOffset() * 60_000
  return new Date(date.getTime() - decalage).toISOString().slice(0, 16)
}

function Formulaire({ ouvert, evenement, onFermer }: { ouvert: boolean; evenement: Evenement | null; onFermer: () => void }) {
  const client = useQueryClient()
  const toast = useToast()
  const moi = useQuery({ queryKey: ['moi'], queryFn: lireMoi })
  const [erreur, setErreur] = useState<string | null>(null)
  const [type, setType] = useState<'en_ligne' | 'presentiel' | null>(evenement?.type ?? null)
  const [envoi, setEnvoi] = useState(false)

  async function envoyer(soumission: FormEvent<HTMLFormElement>) {
    soumission.preventDefault()
    const donnees = new FormData(soumission.currentTarget)
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
    const corps = {
      titre: String(donnees.get('titre') ?? ''),
      description: String(donnees.get('description') ?? ''),
      date: date.toISOString(),
      type,
      lieu: type === 'presentiel' ? String(donnees.get('lieu') ?? '').trim() : undefined,
      lien: type === 'en_ligne' ? String(donnees.get('lien') ?? '').trim() : undefined,
    }
    try {
      if (evenement) await modifierEvenement(evenement.id, corps)
      else await creerEvenement(corps)
      await client.invalidateQueries({ queryKey: ['evenements'] })
      toast.succes(evenement ? 'Événement modifié.' : 'Événement créé.')
      onFermer()
    } catch (erreurHttp) {
      setErreur(messageAction(erreurHttp, evenement ? 'La modification a échoué.' : 'La création a échoué.'))
    } finally {
      setEnvoi(false)
    }
  }

  if (!moi.data) {
    const serveurAbsent = moi.isError
    return (
      <Modale
        ouverte={ouvert}
        onFermer={onFermer}
        taille="petite"
        titre={serveurAbsent ? 'Impossible de vérifier la connexion' : 'Connectez-vous pour créer un événement'}
        description={serveurAbsent ? messageAction(moi.error, 'Le service de connexion ne répond pas.') : 'La publication d’un événement est réservée aux comptes connectés.'}
        pied={
          <>
            <Bouton variante="contour" onClick={onFermer}>
              Fermer
            </Bouton>
            {serveurAbsent ? <Bouton onClick={() => void moi.refetch()}>Réessayer</Bouton> : <LienBouton to="/connexion">Se connecter</LienBouton>}
          </>
        }
      >
        {moi.isPending ? <p role="status" className="m-0 text-petit text-texte-doux">Vérification de la connexion…</p> : null}
      </Modale>
    )
  }

  return (
    <Modale
      ouverte={ouvert}
      onFermer={onFermer}
      titre={evenement ? 'Modifier l’événement' : 'Créer un événement'}
      description={evenement ? 'Les changements apparaissent tout de suite dans la liste.' : "Il apparaît tout de suite dans la liste ; vous pourrez le modifier ou l’annuler ensuite."}
      pied={
        <>
          <Bouton variante="contour" onClick={onFermer}>
            Annuler
          </Bouton>
          <Bouton type="submit" form="creer-evenement" chargement={envoi}>
            {evenement ? 'Enregistrer' : 'Créer l’événement'}
          </Bouton>
        </>
      }
    >
      <form id="creer-evenement" onSubmit={(soumission) => void envoyer(soumission)} className="flex flex-col gap-4">
        <Champ libelle="Titre">
          {(liaison) => <Saisie {...liaison} name="titre" required maxLength={200} defaultValue={evenement?.titre} placeholder="Ex. : Atelier Laravel à Cotonou" data-autofocus />}
        </Champ>
        <Champ libelle="Description">
          {(liaison) => <ZoneTexte {...liaison} name="description" required maxLength={10000} rows={3} defaultValue={evenement?.description} placeholder="Le sujet, et ce que les participants vont apprendre…" />}
        </Champ>
        <Champ libelle="Date et heure">
          {(liaison) => <Saisie {...liaison} name="date" required type="datetime-local" defaultValue={evenement ? dateLocale(evenement.date) : undefined} />}
        </Champ>
        <ChoixCartes
          nom="format-evenement"
          legende="Format"
          colonnes={2}
          valeur={type}
          onChange={(valeur) => {
            setType(valeur)
            setErreur(null)
          }}
          options={[
            { valeur: 'en_ligne', libelle: 'En ligne', description: 'Un lien de visio' },
            { valeur: 'presentiel', libelle: 'Présentiel', description: 'Un lieu, une adresse' },
          ]}
        />
        {type === 'presentiel' ? (
          <Champ libelle="Lieu">
            {(liaison) => <Saisie {...liaison} name="lieu" required maxLength={200} defaultValue={evenement?.lieu ?? undefined} placeholder="Ex. : Cotonou, Haie Vive" />}
          </Champ>
        ) : null}
        {type === 'en_ligne' ? (
          <Champ libelle="Lien de la rencontre" aide="Visible des seuls inscrits.">
            {(liaison) => <Saisie {...liaison} name="lien" required type="url" defaultValue={evenement?.lien ?? undefined} placeholder="https://meet.google.com/abc-defg-hij" />}
          </Champ>
        ) : null}
        {erreur ? <Alerte ton="danger">{erreur}</Alerte> : null}
      </form>
    </Modale>
  )
}
