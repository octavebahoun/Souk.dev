import { useState } from 'react'
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { Link } from 'react-router'
import { useTitrePage } from '../../app/titre'
import {
  AccesRefuse,
  Badge,
  Bouton,
  Champ,
  EnTetePage,
  EtatErreur,
  EtatVide,
  LienBouton,
  Modale,
  Onglets,
  Pagination,
  presentationEtatCopie,
  Saisie,
  SqueletteListe,
  Tableau,
  ZoneTexte,
  formaterDate,
  useToast,
  type Colonne,
} from '../../ui'
import { erreursDeChamps, messageErreur } from '../paiements/api'
import {
  leverSuspensionCompte,
  listerCanaux,
  listerComptes,
  listerCopiesAdmin,
  listerSignalements,
  modifierCanal,
  reactiverCopie,
  rejeterSignalement,
  supprimerCanal,
  suspendreCompte,
  suspendreCopie,
  traiterSignalement,
  type Canal,
  type CompteAdmin,
  type Deploiement,
  type Signalement,
  type StatutSignalement,
} from './api'
import { cheminCible, estGrave, libellesCibles, libellesMotifs, libellesStatuts, tonsStatuts } from './presentationModeration'

type Vue = 'signalements' | 'copies' | 'comptes' | 'canaux'

/**
 * Modération (admin) : les signalements reçus (les motifs graves d'abord), les copies
 * suspendues pour abus, les comptes, et les canaux. Chaque décision demande un motif,
 * visible par le compte concerné.
 */
export function Moderation() {
  useTitrePage('Modération')
  const [vue, setVue] = useState<Vue>('signalements')
  const sonde = useQuery({ queryKey: ['admin', 'signalements', { statut: 'ouvert', page: 1 }], queryFn: () => listerSignalements({ statut: 'ouvert', page: 1 }) })

  if (sonde.isError && axios.isAxiosError(sonde.error) && sonde.error.response?.status === 403) {
    return <AccesRefuse description="La modération est réservée à l’équipe de Souk.dev." action={<LienBouton to="/tableau">Retour à mon espace</LienBouton>} />
  }

  return (
    <div className="flex flex-col gap-8">
      <EnTetePage
        titre="Modération"
        description="Signaler, examiner, décider : chaque suspension porte un motif, contestable par e-mail."
        actions={
          <LienBouton to="/legal/abus" variante="secondaire">
            Politique d’abus
          </LienBouton>
        }
      />
      <Onglets
        libelle="Modération"
        valeur={vue}
        onChange={setVue}
        onglets={[
          { valeur: 'signalements', libelle: 'Signalements' },
          { valeur: 'copies', libelle: 'Copies' },
          { valeur: 'comptes', libelle: 'Comptes' },
          { valeur: 'canaux', libelle: 'Canaux' },
        ]}
      >
        {vue === 'signalements' && <SectionSignalements />}
        {vue === 'copies' && <SectionCopies />}
        {vue === 'comptes' && <SectionComptes />}
        {vue === 'canaux' && <SectionCanaux />}
      </Onglets>
    </div>
  )
}

function SectionSignalements() {
  const [statut, setStatut] = useState<StatutSignalement | undefined>('ouvert')
  const [page, setPage] = useState(1)
  const [decision, setDecision] = useState<{ signalement: Signalement; action: 'traiter' | 'rejeter' } | null>(null)
  const signalements = useQuery({
    queryKey: ['admin', 'signalements', { statut, page }],
    queryFn: () => listerSignalements({ statut, page }),
    placeholderData: keepPreviousData,
  })

  const colonnes: Array<Colonne<Signalement>> = [
    {
      cle: 'cible',
      entete: 'Cible',
      principale: true,
      rendu: (signalement) => <CibleSignalement signalement={signalement} />,
    },
    {
      cle: 'motif',
      entete: 'Motif',
      rendu: (signalement) => <Badge ton={estGrave(signalement.motif) ? 'danger' : 'neutre'}>{libellesMotifs[signalement.motif]}</Badge>,
    },
    {
      cle: 'signale',
      entete: 'Signalé',
      rendu: (signalement) => (
        <span>
          {signalement.signale_par.username ?? signalement.signale_par.nom ?? 'Un membre'}
          <br />
          <span className="text-petit text-texte-doux">{formaterDate(signalement.cree_le)}</span>
        </span>
      ),
    },
    {
      cle: 'statut',
      entete: 'Statut',
      rendu: (signalement) => <Badge ton={tonsStatuts[signalement.statut]}>{libellesStatuts[signalement.statut]}</Badge>,
    },
    {
      cle: 'actions',
      entete: 'Actions',
      rendu: (signalement) =>
        signalement.statut === 'ouvert' ? (
          <span className="flex flex-wrap gap-2">
            <Bouton taille="sm" onClick={() => setDecision({ signalement, action: 'traiter' })}>
              Traiter
            </Bouton>
            <Bouton taille="sm" variante="secondaire" onClick={() => setDecision({ signalement, action: 'rejeter' })}>
              Rejeter
            </Bouton>
          </span>
        ) : (
          <span className="text-petit text-texte-doux">{signalement.note ?? '—'}</span>
        ),
    },
  ]

  return (
    <div className="flex flex-col gap-4">
      <div role="group" aria-label="Filtrer par statut" className="flex flex-wrap gap-2">
        {(['ouvert', 'traite', 'rejete', undefined] as const).map((choix) => (
          <Bouton
            key={choix ?? 'tous'}
            taille="sm"
            variante={statut === choix ? 'primaire' : 'secondaire'}
            aria-pressed={statut === choix}
            onClick={() => {
              setStatut(choix)
              setPage(1)
            }}
          >
            {choix ? libellesStatuts[choix] : 'Tous'}
          </Bouton>
        ))}
      </div>

      {signalements.isPending ? (
        <div role="status" aria-label="Chargement des signalements"><SqueletteListe /></div>
      ) : signalements.isError || !signalements.data ? (
        <EtatErreur onReessayer={() => void signalements.refetch()} />
      ) : signalements.data.data.length === 0 ? (
        <EtatVide illustration="vide-notifications" titre="Aucun signalement" description="Personne n’a rien signalé. L’équipe peut souffler." />
      ) : (
        <>
          <Tableau legende="Signalements" colonnes={colonnes} lignes={signalements.data.data} cleLigne={(ligne) => ligne.id} />
          <Pagination page={signalements.data.meta.current_page} dernierePage={signalements.data.meta.last_page} onChange={setPage} />
        </>
      )}

      <ModaleDecision decision={decision} onFermer={() => setDecision(null)} />
    </div>
  )
}

function CibleSignalement({ signalement }: { signalement: Signalement }) {
  const chemin = cheminCible(signalement.cible, signalement.cible_id)
  return (
    <span>
      <Badge ton="info">{libellesCibles[signalement.cible]}</Badge> {signalement.cible_resume}
      {chemin && (
        <>
          {' — '}
          <Link to={chemin}>Voir</Link>
        </>
      )}
      {signalement.details && <span className="block text-petit text-texte-doux">{signalement.details}</span>}
    </span>
  )
}

function ModaleDecision({ decision, onFermer }: { decision: { signalement: Signalement; action: 'traiter' | 'rejeter' } | null; onFermer: () => void }) {
  const cache = useQueryClient()
  const toast = useToast()
  const [note, setNote] = useState('')
  const [erreurs, setErreurs] = useState<Record<string, string>>({})

  const mutation = useMutation({
    mutationFn: () => {
      if (!decision) throw new Error('Aucun signalement')
      return decision.action === 'traiter' ? traiterSignalement(decision.signalement.id, note || undefined) : rejeterSignalement(decision.signalement.id, note || undefined)
    },
    onSuccess: (signalement) => {
      toast.succes(signalement.statut === 'traite' ? 'Signalement traité.' : 'Signalement rejeté.')
      setNote('')
      setErreurs({})
      onFermer()
      void cache.invalidateQueries({ queryKey: ['admin', 'signalements'] })
    },
    onError: (probleme) => {
      setErreurs(erreursDeChamps(probleme))
      toast.erreur(messageErreur(probleme, 'La décision n’a pas pu être enregistrée.'))
    },
  })

  return (
    <Modale
      ouverte={decision !== null}
      onFermer={() => {
        setNote('')
        setErreurs({})
        onFermer()
      }}
      titre={decision?.action === 'traiter' ? 'Traiter le signalement' : 'Rejeter le signalement'}
      description={decision ? decision.signalement.cible_resume : undefined}
      pied={
        <>
          <Bouton variante="secondaire" onClick={onFermer}>
            Annuler
          </Bouton>
          <Bouton onClick={() => mutation.mutate()} chargement={mutation.isPending}>
            {decision?.action === 'traiter' ? 'Marquer traité' : 'Rejeter'}
          </Bouton>
        </>
      }
    >
      <Champ
        libelle="Note de l’équipe"
        aide={decision?.action === 'traiter' ? 'Ce qui a été fait : contenu supprimé, copie ou compte suspendu.' : 'Pourquoi ce signalement n’est pas fondé.'}
        erreur={erreurs.note}
      >
        {(liaison) => <ZoneTexte {...liaison} rows={3} maxLength={2000} value={note} onChange={(evenement) => setNote(evenement.target.value)} />}
      </Champ>
    </Modale>
  )
}

function SectionCopies() {
  const cache = useQueryClient()
  const toast = useToast()
  const [suspendues, setSuspendues] = useState(false)
  const [page, setPage] = useState(1)
  const [suspension, setSuspension] = useState<Deploiement | null>(null)
  const copies = useQuery({
    queryKey: ['admin', 'copies', { suspendues, page }],
    queryFn: () => listerCopiesAdmin({ suspension_admin: suspendues || undefined, page }),
    placeholderData: keepPreviousData,
  })

  const reactiver = useMutation({
    mutationFn: (copie: Deploiement) => reactiverCopie(copie.id),
    onSuccess: () => {
      toast.succes('Suspension levée : la copie repart avec ses données.')
      void cache.invalidateQueries({ queryKey: ['admin', 'copies'] })
    },
    onError: (probleme) => toast.erreur(messageErreur(probleme, 'La copie n’a pas pu repartir.')),
  })

  const colonnes: Array<Colonne<Deploiement>> = [
    {
      cle: 'copie',
      entete: 'Copie',
      principale: true,
      rendu: (copie) => (
        <span>
          #{copie.id} — {copie.app_nom}
          <br />
          <span className="text-petit text-texte-doux">{presentationEtatCopie(copie.etat).libelle}</span>
        </span>
      ),
    },
    {
      cle: 'suspension',
      entete: 'Suspension pour abus',
      rendu: (copie) => (copie.suspension_admin_motif ? <Badge ton="danger">{copie.suspension_admin_motif}</Badge> : <span className="text-texte-doux">—</span>),
    },
    {
      cle: 'actions',
      entete: 'Actions',
      rendu: (copie) =>
        copie.suspension_admin_motif ? (
          <Bouton taille="sm" variante="secondaire" chargement={reactiver.isPending} onClick={() => reactiver.mutate(copie)}>
            Réactiver
          </Bouton>
        ) : (
          <Bouton taille="sm" variante="secondaire" disabled={copie.etat !== 'en_ligne'} title={copie.etat !== 'en_ligne' ? 'Seule une copie en ligne peut être suspendue pour abus.' : undefined} onClick={() => setSuspension(copie)}>
            Suspendre
          </Bouton>
        ),
    },
  ]

  return (
    <div className="flex flex-col gap-4">
      <Bouton taille="sm" variante={suspendues ? 'primaire' : 'secondaire'} aria-pressed={suspendues} onClick={() => { setSuspendues(!suspendues); setPage(1) }}>
        {suspendues ? 'Toutes les copies' : 'Seulement les suspendues pour abus'}
      </Bouton>

      {copies.isPending ? (
        <div role="status" aria-label="Chargement des copies"><SqueletteListe /></div>
      ) : copies.isError || !copies.data ? (
        <EtatErreur onReessayer={() => void copies.refetch()} />
      ) : copies.data.data.length === 0 ? (
        <EtatVide illustration="vide-copies" titre="Aucune copie" description={suspendues ? 'Aucune copie suspendue pour abus.' : 'Aucune copie déployée pour l’instant.'} />
      ) : (
        <>
          <Tableau legende="Copies" colonnes={colonnes} lignes={copies.data.data} cleLigne={(ligne) => ligne.id} />
          <Pagination page={copies.data.meta.current_page} dernierePage={copies.data.meta.last_page} onChange={setPage} />
        </>
      )}

      <ModaleSuspensionCopie copie={suspension} onFermer={() => setSuspension(null)} />
    </div>
  )
}

function ModaleSuspensionCopie({ copie, onFermer }: { copie: Deploiement | null; onFermer: () => void }) {
  const cache = useQueryClient()
  const toast = useToast()
  const [motif, setMotif] = useState('')
  const [erreurs, setErreurs] = useState<Record<string, string>>({})

  const mutation = useMutation({
    mutationFn: () => {
      if (!copie) throw new Error('Aucune copie')
      return suspendreCopie(copie.id, { motif })
    },
    onSuccess: () => {
      toast.succes('Copie suspendue : ses conteneurs s’arrêtent, ses données sont gardées.')
      setMotif('')
      setErreurs({})
      onFermer()
      void cache.invalidateQueries({ queryKey: ['admin', 'copies'] })
    },
    onError: (probleme) => {
      setErreurs(erreursDeChamps(probleme))
      toast.erreur(messageErreur(probleme, 'La copie n’a pas pu être suspendue.'))
    },
  })

  return (
    <Modale
      ouverte={copie !== null}
      onFermer={() => {
        setMotif('')
        setErreurs({})
        onFermer()
      }}
      titre="Suspendre une copie pour abus"
      description={copie ? `Copie #${copie.id} (${copie.app_nom}). Le client est prévenu par e-mail, avec le motif et un moyen de contester.` : undefined}
      pied={
        <>
          <Bouton variante="secondaire" onClick={onFermer}>
            Annuler
          </Bouton>
          <Bouton variante="danger" disabled={motif.trim() === ''} onClick={() => mutation.mutate()} chargement={mutation.isPending}>
            Suspendre
          </Bouton>
        </>
      }
    >
      <Champ libelle="Motif" aide="Minage, hameçonnage… Visible par le client." erreur={erreurs.motif}>
        {(liaison) => <ZoneTexte {...liaison} rows={3} maxLength={500} value={motif} onChange={(evenement) => setMotif(evenement.target.value)} />}
      </Champ>
    </Modale>
  )
}

function SectionComptes() {
  const cache = useQueryClient()
  const toast = useToast()
  const [recherche, setRecherche] = useState('')
  const [filtre, setFiltre] = useState('')
  const [suspendus, setSuspendus] = useState(false)
  const [page, setPage] = useState(1)
  const [suspension, setSuspension] = useState<CompteAdmin | null>(null)
  const comptes = useQuery({
    queryKey: ['admin', 'comptes', { filtre, suspendus, page }],
    queryFn: () => listerComptes({ recherche: filtre || undefined, suspendus: suspendus || undefined, page }),
    placeholderData: keepPreviousData,
  })

  const lever = useMutation({
    mutationFn: (compte: CompteAdmin) => leverSuspensionCompte(compte.id),
    onSuccess: () => {
      toast.succes('Suspension levée : le compte agit de nouveau.')
      void cache.invalidateQueries({ queryKey: ['admin', 'comptes'] })
    },
    onError: (probleme) => toast.erreur(messageErreur(probleme, 'La suspension n’a pas pu être levée.')),
  })

  const colonnes: Array<Colonne<CompteAdmin>> = [
    {
      cle: 'compte',
      entete: 'Compte',
      principale: true,
      rendu: (compte) => (
        <span>
          {compte.username ?? compte.nom ?? `Compte #${compte.id}`}
          <br />
          <span className="text-petit text-texte-doux">{compte.email ?? 'Sans e-mail'}</span>
        </span>
      ),
    },
    {
      cle: 'suspension',
      entete: 'Suspension',
      rendu: (compte) =>
        compte.suspendu_le ? (
          <span>
            <Badge ton="danger">Suspendu</Badge> {compte.suspension_motif}
            <br />
            <span className="text-petit text-texte-doux">depuis le {formaterDate(compte.suspendu_le)}</span>
          </span>
        ) : (
          <span className="text-texte-doux">—</span>
        ),
    },
    {
      cle: 'actions',
      entete: 'Actions',
      rendu: (compte) =>
        compte.suspendu_le ? (
          <Bouton taille="sm" variante="secondaire" chargement={lever.isPending} onClick={() => lever.mutate(compte)}>
            Lever
          </Bouton>
        ) : (
          <Bouton taille="sm" variante="secondaire" disabled={compte.est_admin} title={compte.est_admin ? 'Un compte de l’équipe ne se suspend pas lui-même.' : undefined} onClick={() => setSuspension(compte)}>
            Suspendre
          </Bouton>
        ),
    },
  ]

  return (
    <div className="flex flex-col gap-4">
      <form
        role="search"
        className="flex flex-col gap-2 tablette:flex-row"
        onSubmit={(evenement) => {
          evenement.preventDefault()
          setFiltre(recherche)
          setPage(1)
        }}
      >
        <Champ libelle="Chercher un compte" libelleCache>
          {(liaison) => <Saisie {...liaison} type="search" placeholder="Nom, pseudo ou e-mail" value={recherche} onChange={(evenement) => setRecherche(evenement.target.value)} />}
        </Champ>
        <Bouton type="submit" variante="secondaire">
          Chercher
        </Bouton>
        <Bouton taille="sm" variante={suspendus ? 'primaire' : 'secondaire'} aria-pressed={suspendus} onClick={() => { setSuspendus(!suspendus); setPage(1) }}>
          Suspendus seulement
        </Bouton>
      </form>

      {comptes.isPending ? (
        <div role="status" aria-label="Chargement des comptes"><SqueletteListe /></div>
      ) : comptes.isError || !comptes.data ? (
        <EtatErreur onReessayer={() => void comptes.refetch()} />
      ) : comptes.data.data.length === 0 ? (
        <EtatVide illustration="vide-recherche" titre="Aucun compte" description="Aucun compte ne correspond à cette recherche." />
      ) : (
        <>
          <Tableau legende="Comptes" colonnes={colonnes} lignes={comptes.data.data} cleLigne={(ligne) => ligne.id} />
          <Pagination page={comptes.data.meta.current_page} dernierePage={comptes.data.meta.last_page} onChange={setPage} />
        </>
      )}

      <ModaleSuspensionCompte compte={suspension} onFermer={() => setSuspension(null)} />
    </div>
  )
}

function ModaleSuspensionCompte({ compte, onFermer }: { compte: CompteAdmin | null; onFermer: () => void }) {
  const cache = useQueryClient()
  const toast = useToast()
  const [motif, setMotif] = useState('')
  const [erreurs, setErreurs] = useState<Record<string, string>>({})

  const mutation = useMutation({
    mutationFn: () => {
      if (!compte) throw new Error('Aucun compte')
      return suspendreCompte(compte.id, { motif })
    },
    onSuccess: () => {
      toast.succes('Compte suspendu : il est prévenu par e-mail, avec un moyen de contester.')
      setMotif('')
      setErreurs({})
      onFermer()
      void cache.invalidateQueries({ queryKey: ['admin', 'comptes'] })
    },
    onError: (probleme) => {
      setErreurs(erreursDeChamps(probleme))
      toast.erreur(messageErreur(probleme, 'Le compte n’a pas pu être suspendu.'))
    },
  })

  return (
    <Modale
      ouverte={compte !== null}
      onFermer={() => {
        setMotif('')
        setErreurs({})
        onFermer()
      }}
      titre="Suspendre un compte"
      description={compte ? `${compte.username ?? compte.nom ?? `Compte #${compte.id}`} ne pourra plus se connecter ni appeler l’API.` : undefined}
      pied={
        <>
          <Bouton variante="secondaire" onClick={onFermer}>
            Annuler
          </Bouton>
          <Bouton variante="danger" disabled={motif.trim() === ''} onClick={() => mutation.mutate()} chargement={mutation.isPending}>
            Suspendre
          </Bouton>
        </>
      }
    >
      <Champ libelle="Motif" aide="Harcèlement, spam… Visible par le compte." erreur={erreurs.motif}>
        {(liaison) => <ZoneTexte {...liaison} rows={3} maxLength={500} value={motif} onChange={(evenement) => setMotif(evenement.target.value)} />}
      </Champ>
    </Modale>
  )
}

function SectionCanaux() {
  const cache = useQueryClient()
  const toast = useToast()
  const [modification, setModification] = useState<Canal | null>(null)
  const [suppression, setSuppression] = useState<Canal | null>(null)
  const canaux = useQuery({ queryKey: ['canaux'], queryFn: listerCanaux })

  const supprimer = useMutation({
    mutationFn: (canal: Canal) => supprimerCanal(canal.slug),
    onSuccess: () => {
      toast.succes('Canal supprimé.')
      setSuppression(null)
      void cache.invalidateQueries({ queryKey: ['canaux'] })
    },
    onError: (probleme) => toast.erreur(messageErreur(probleme, 'Le canal n’a pas pu être supprimé.')),
  })

  const colonnes: Array<Colonne<Canal>> = [
    {
      cle: 'canal',
      entete: 'Canal',
      principale: true,
      rendu: (canal) => (
        <span>
          #{canal.slug} — {canal.nom}
          {canal.description && <span className="block text-petit text-texte-doux">{canal.description}</span>}
        </span>
      ),
    },
    {
      cle: 'pays',
      entete: 'Pays',
      rendu: (canal) => canal.pays ?? <span className="text-texte-doux">Général</span>,
    },
    {
      cle: 'actions',
      entete: 'Actions',
      rendu: (canal) => (
        <span className="flex flex-wrap gap-2">
          <Bouton taille="sm" variante="secondaire" onClick={() => setModification(canal)}>
            Modifier
          </Bouton>
          <Bouton taille="sm" variante="secondaire" onClick={() => setSuppression(canal)}>
            Supprimer
          </Bouton>
        </span>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-4">
      {canaux.isPending ? (
        <div role="status" aria-label="Chargement des canaux"><SqueletteListe /></div>
      ) : canaux.isError || !canaux.data ? (
        <EtatErreur onReessayer={() => void canaux.refetch()} />
      ) : canaux.data.length === 0 ? (
        <EtatVide illustration="vide-discussions" titre="Aucun canal" description="Les membres en créent depuis l’échange." />
      ) : (
        <Tableau legende="Canaux" colonnes={colonnes} lignes={canaux.data} cleLigne={(ligne) => ligne.slug} />
      )}

      <ModaleModificationCanal canal={modification} onFermer={() => setModification(null)} />

      <Modale
        ouverte={suppression !== null}
        onFermer={() => setSuppression(null)}
        titre="Supprimer ce canal ?"
        description={suppression ? `Le canal #${suppression.slug} disparaîtra. Refusé tant que des discussions l’utilisent.` : undefined}
        pied={
          <>
            <Bouton variante="secondaire" onClick={() => setSuppression(null)}>
              Annuler
            </Bouton>
            <Bouton variante="danger" chargement={supprimer.isPending} onClick={() => suppression && supprimer.mutate(suppression)}>
              Supprimer
            </Bouton>
          </>
        }
      />
    </div>
  )
}

function ModaleModificationCanal({ canal, onFermer }: { canal: Canal | null; onFermer: () => void }) {
  const cache = useQueryClient()
  const toast = useToast()
  const [nom, setNom] = useState('')
  const [description, setDescription] = useState('')
  const [erreurs, setErreurs] = useState<Record<string, string>>({})
  const [initial, setInitial] = useState<Canal | null>(null)

  if (canal !== initial) {
    setInitial(canal)
    setNom(canal?.nom ?? '')
    setDescription(canal?.description ?? '')
    setErreurs({})
  }

  const mutation = useMutation({
    mutationFn: () => {
      if (!canal) throw new Error('Aucun canal')
      return modifierCanal(canal.slug, { nom, description: description || null })
    },
    onSuccess: () => {
      toast.succes('Canal modifié.')
      onFermer()
      void cache.invalidateQueries({ queryKey: ['canaux'] })
    },
    onError: (probleme) => {
      setErreurs(erreursDeChamps(probleme))
      toast.erreur(messageErreur(probleme, 'Le canal n’a pas pu être modifié.'))
    },
  })

  return (
    <Modale
      ouverte={canal !== null}
      onFermer={onFermer}
      titre="Modifier le canal"
      description={canal ? `Le slug #${canal.slug} ne change pas : les liens restent valables.` : undefined}
      pied={
        <>
          <Bouton variante="secondaire" onClick={onFermer}>
            Annuler
          </Bouton>
          <Bouton disabled={nom.trim() === ''} onClick={() => mutation.mutate()} chargement={mutation.isPending}>
            Enregistrer
          </Bouton>
        </>
      }
    >
      <div className="flex flex-col gap-4">
        <Champ libelle="Nom" erreur={erreurs.nom}>
          {(liaison) => <Saisie {...liaison} maxLength={50} value={nom} onChange={(evenement) => setNom(evenement.target.value)} />}
        </Champ>
        <Champ libelle="Description" erreur={erreurs.description} facultatif>
          {(liaison) => <ZoneTexte {...liaison} rows={2} maxLength={300} value={description} onChange={(evenement) => setDescription(evenement.target.value)} />}
        </Champ>
      </div>
    </Modale>
  )
}
