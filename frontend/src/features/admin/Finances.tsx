import { useRef, useState } from 'react'
import { keepPreviousData, useQuery, useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { useTitrePage } from '../../app/titre'
import {
  AccesRefuse,
  Alerte,
  Badge,
  Bouton,
  Carte,
  Champ,
  EnTetePage,
  EtatErreur,
  LienBouton,
  Modale,
  Montant,
  Onglets,
  Pagination,
  PastilleEtat,
  SaisieMontant,
  Squelette,
  SqueletteLignes,
  Tableau,
  ZoneTexte,
  cx,
  formaterDate,
  formaterFcfa,
  formaterPeriode,
  useToast,
  type Colonne,
} from '../../ui'
import {
  creerAvoir,
  erreursDeChamps,
  lireFinances,
  listerFacturesAdmin,
  listerRapprochements,
  listerReversementsAdmin,
  messageErreur,
  nouvelleCle,
  rembourserTropPercu,
  type Facture,
  type Rapprochement,
  type ReversementAdmin,
  type TropPercu,
} from '../paiements/api'
import { presentationFacture, presentationReversement } from '../paiements/presentation'

type Periode = 'mois' | 'precedent' | 'annee'
type Vue = 'factures' | 'reversements' | 'rapprochements'

function bornes(periode: Periode): { debut: string; fin: string } {
  const jour = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
  const maintenant = new Date()
  if (periode === 'precedent') {
    return { debut: jour(new Date(maintenant.getFullYear(), maintenant.getMonth() - 1, 1)), fin: jour(new Date(maintenant.getFullYear(), maintenant.getMonth(), 0)) }
  }
  if (periode === 'annee') return { debut: jour(new Date(maintenant.getFullYear(), 0, 1)), fin: jour(maintenant) }
  return { debut: jour(new Date(maintenant.getFullYear(), maintenant.getMonth(), 1)), fin: jour(maintenant) }
}

/**
 * Vue financière de l'admin, tirée du grand livre : ce qui est entré, ce qui revient aux devs,
 * la marge de Souk.dev ; puis factures (avoirs après un remboursement SasPay), reversements
 * et rapprochements quotidiens.
 */
export function Finances() {
  useTitrePage('Finances')
  const [periode, setPeriode] = useState<Periode>('mois')
  const [vue, setVue] = useState<Vue>('factures')
  const intervalle = bornes(periode)
  const finances = useQuery({ queryKey: ['admin', 'finances', intervalle], queryFn: () => lireFinances(intervalle), placeholderData: keepPreviousData })

  if (finances.isError && axios.isAxiosError(finances.error) && finances.error.response?.status === 403) {
    return <AccesRefuse description="La vue financière est réservée à l’équipe de Souk.dev." action={<LienBouton to="/tableau">Retour à mon espace</LienBouton>} />
  }

  return (
    <div className="flex flex-col gap-8">
      <EnTetePage
        titre="Finances"
        description="Tiré du grand livre : chaque franc encaissé, dû ou versé, en entiers FCFA."
        actions={
          <div role="group" aria-label="Période" className="inline-flex rounded-moyen border border-bordure bg-surface p-1 shadow-1">
            {(
              [
                ['mois', 'Ce mois-ci'],
                ['precedent', 'Mois dernier'],
                ['annee', 'Cette année'],
              ] as const
            ).map(([valeur, libelle]) => (
              <button
                key={valeur}
                type="button"
                aria-pressed={periode === valeur}
                onClick={() => setPeriode(valeur)}
                className={cx(
                  'h-11 rounded-petit px-3 text-petit font-medium transition-colors duration-150 tablette:h-8',
                  periode === valeur ? 'bg-primaire-doux text-sur-primaire-doux' : 'text-texte hover:bg-surface-douce',
                )}
              >
                {libelle}
              </button>
            ))}
          </div>
        }
      />

      {finances.isPending ? (
        <div role="status" aria-label="Chargement des finances" className="grid gap-4 tablette:grid-cols-2 bureau:grid-cols-4">
          {Array.from({ length: 8 }, (_, index) => (
            <Squelette key={index} className="h-24 rounded-moyen" />
          ))}
        </div>
      ) : finances.isError || !finances.data ? (
        <EtatErreur onReessayer={() => void finances.refetch()} />
      ) : (
        <section aria-label={`Indicateurs ${formaterPeriode(finances.data.debut, finances.data.fin)}`} className={cx('flex flex-col gap-4', finances.isPlaceholderData && 'opacity-60')}>
          <p className="m-0 text-petit text-texte-doux">Période : {formaterPeriode(finances.data.debut, finances.data.fin)}</p>
          <dl className="m-0 grid gap-4 tablette:grid-cols-2 bureau:grid-cols-4">
            <Indicateur titre="Marge de Souk.dev" valeur={finances.data.marge} detail="Hébergement, moins frais de reversement et avoirs" mis />
            <Indicateur titre="Encaissements" valeur={finances.data.encaissements} detail="Factures payées (montant plein)" />
            <Indicateur titre="Revenus d’hébergement" valeur={finances.data.revenus_hebergement} />
            <Indicateur titre="Parts des devs" valeur={finances.data.parts_devs} detail="Prix des applis, sans commission" />
            <Indicateur titre="Reversé aux devs" valeur={finances.data.reversements} />
            <Indicateur titre="Frais de reversement" valeur={finances.data.frais_reversement} detail="Pris sur la marge" />
            <Indicateur titre="Avoirs" valeur={finances.data.avoirs} />
            <Indicateur titre="Dû aux devs aujourd’hui" valeur={finances.data.solde_devs} />
          </dl>
          {finances.data.trop_percus.length > 0 ? <TropPercus elements={finances.data.trop_percus} /> : null}
          <div className="flex flex-wrap gap-3">
            <Badge ton="info">Solde SasPay attendu : {formaterFcfa(finances.data.solde_saspay_attendu)}</Badge>
            <Badge ton={finances.data.factures_impayees > 0 ? 'alerte' : 'neutre'}>Factures échues impayées : {finances.data.factures_impayees}</Badge>
            <Badge ton={finances.data.copies_suspendues > 0 ? 'danger' : 'neutre'}>Copies suspendues : {finances.data.copies_suspendues}</Badge>
          </div>
        </section>
      )}

      <Onglets
        libelle="Détail des finances"
        valeur={vue}
        onChange={setVue}
        onglets={[
          { valeur: 'factures', libelle: 'Factures' },
          { valeur: 'reversements', libelle: 'Reversements' },
          { valeur: 'rapprochements', libelle: 'Rapprochements' },
        ]}
      >
        <div className="mt-4">
          {vue === 'factures' ? <ListeFactures /> : vue === 'reversements' ? <ListeReversements /> : <ListeRapprochements />}
        </div>
      </Onglets>
    </div>
  )
}

function Indicateur({ titre, valeur, detail, mis = false }: { titre: string; valeur: number; detail?: string; mis?: boolean }) {
  return (
    <Carte as="div" className={cx('flex flex-col gap-1', mis && 'border-primaire bg-surface-accent')}>
      <dt className={cx('text-petit', mis ? 'text-sur-primaire-doux' : 'text-texte-doux')}>{titre}</dt>
      <dd className="m-0 chiffres text-titre-2 text-titre">{formaterFcfa(valeur)}</dd>
      {detail ? <dd className="m-0 text-petit text-texte-doux">{detail}</dd> : null}
    </Carte>
  )
}

function ListeFactures() {
  const [page, setPage] = useState(1)
  const [cible, setCible] = useState<Facture | null>(null)
  const factures = useQuery({ queryKey: ['admin', 'factures', page], queryFn: () => listerFacturesAdmin({ page }), placeholderData: keepPreviousData })

  const colonnes: Colonne<Facture>[] = [
    {
      cle: 'facture',
      entete: 'Facture',
      principale: true,
      rendu: (facture) => (
        <span className="flex flex-col">
          <span className="font-medium text-titre">{facture.numero}</span>
          <span className="text-petit text-texte-doux">{facture.app_nom}</span>
        </span>
      ),
    },
    { cle: 'echeance', entete: 'Échéance', rendu: (facture) => formaterDate(facture.echeance_le) },
    { cle: 'montant', entete: 'Montant', alignement: 'droite', rendu: (facture) => <Montant valeur={facture.montant_total} className="text-titre" /> },
    {
      cle: 'etat',
      entete: 'État',
      rendu: (facture) => {
        const etat = presentationFacture(facture)
        return <PastilleEtat ton={etat.ton} libelle={etat.libelle} />
      },
    },
    {
      cle: 'action',
      entete: <span className="pour-lecteur">Action</span>,
      alignement: 'droite',
      rendu: (facture) =>
        facture.statut === 'payee' ? (
          <Bouton variante="fantome" taille="sm" onClick={() => setCible(facture)} aria-label={`Saisir un avoir sur ${facture.numero}`}>
            Saisir un avoir
          </Bouton>
        ) : null,
    },
  ]

  if (factures.isPending) return <SqueletteLignes lignes={5} />
  if (factures.isError || !factures.data) return <EtatErreur onReessayer={() => void factures.refetch()} />
  if (factures.data.data.length === 0) return <p className="m-0 rounded-moyen border border-dashed border-bordure p-6 text-center text-texte-doux">Aucune facture pour l’instant.</p>

  return (
    <div className="flex flex-col gap-4">
      <Tableau legende="Toutes les factures" colonnes={colonnes} lignes={factures.data.data} cleLigne={(facture) => facture.id} />
      <Pagination page={page} dernierePage={factures.data.meta.last_page} onChange={setPage} />
      {cible ? <SaisieAvoir facture={cible} onFermer={() => setCible(null)} /> : null}
    </div>
  )
}

/** Après un remboursement fait dans le tableau de bord SasPay (pas d'API de remboursement). */
function SaisieAvoir({ facture, onFermer }: { facture: Facture; onFermer: () => void }) {
  const cache = useQueryClient()
  const toast = useToast()
  const cle = useRef(nouvelleCle())
  const [hebergement, setHebergement] = useState(String(facture.montant_hebergement))
  const [appli, setAppli] = useState(String(facture.montant_appli))
  const [motif, setMotif] = useState('')
  const [erreurs, setErreurs] = useState<Record<string, string>>({})
  const [erreur, setErreur] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)
  const total = (Number(hebergement) || 0) + (Number(appli) || 0)

  async function enregistrer() {
    setEnvoi(true)
    setErreur(null)
    setErreurs({})
    try {
      const avoir = await creerAvoir(facture.id, { montant_hebergement: Number(hebergement) || 0, montant_appli: Number(appli) || 0, motif }, cle.current)
      toast.succes(`Avoir ${avoir.numero} enregistré (${formaterFcfa(avoir.montant_total)}).`)
      await cache.invalidateQueries({ queryKey: ['admin'] })
      onFermer()
    } catch (probleme) {
      cle.current = nouvelleCle()
      setErreurs(erreursDeChamps(probleme))
      setErreur(messageErreur(probleme, 'L’avoir n’a pas pu être enregistré.'))
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Modale
      ouverte
      onFermer={onFermer}
      titre={`Avoir sur la facture ${facture.numero}`}
      description="À saisir après avoir remboursé le client depuis le tableau de bord SasPay. Le grand livre recule l’hébergement et la part du dev."
      pied={
        <>
          <Bouton variante="contour" onClick={onFermer}>
            Annuler
          </Bouton>
          <Bouton chargement={envoi} disabled={total === 0 || motif.trim() === ''} onClick={() => void enregistrer()}>
            Enregistrer l’avoir de {formaterFcfa(total)}
          </Bouton>
        </>
      }
    >
      <div className="flex flex-col gap-4">
        <div className="grid gap-4 tablette:grid-cols-2">
          <Champ libelle="Hébergement remboursé" aide={`Au plus ${formaterFcfa(facture.montant_hebergement)}`} erreur={erreurs.montant_hebergement}>
            {(liaison) => <SaisieMontant {...liaison} value={hebergement} onChange={(evenement) => setHebergement(evenement.target.value.replace(/\D/g, ''))} />}
          </Champ>
          <Champ libelle="Part de l’appli remboursée" aide={`Au plus ${formaterFcfa(facture.montant_appli)}`} erreur={erreurs.montant_appli}>
            {(liaison) => <SaisieMontant {...liaison} value={appli} onChange={(evenement) => setAppli(evenement.target.value.replace(/\D/g, ''))} />}
          </Champ>
        </div>
        <Champ libelle="Motif" aide="Visible dans l’historique de la facture." erreur={erreurs.motif}>
          {(liaison) => <ZoneTexte {...liaison} rows={3} maxLength={500} value={motif} onChange={(evenement) => setMotif(evenement.target.value)} />}
        </Champ>
        {erreur && Object.keys(erreurs).length === 0 ? (
          <Alerte ton="danger" urgente>
            {erreur}
          </Alerte>
        ) : null}
      </div>
    </Modale>
  )
}

/** Paiements en double à rendre aux clients : l'admin rembourse chez SasPay, puis le note ici. */
function TropPercus({ elements }: { elements: TropPercu[] }) {
  const [cible, setCible] = useState<TropPercu | null>(null)
  return (
    <section aria-labelledby="titre-trop-percus" className="flex flex-col gap-3">
      <Alerte ton="alerte" titre={`${elements.length} paiement${elements.length > 1 ? 's' : ''} en double à rembourser`}>
        Remboursez chaque client depuis le tableau de bord SasPay, puis notez-le ici : le grand livre solde ce qui lui est dû.
      </Alerte>
      <h2 id="titre-trop-percus" className="pour-lecteur">
        Paiements en double à rembourser
      </h2>
      <ul className="m-0 flex list-none flex-col gap-2 p-0">
        {elements.map((element) => (
          <li key={element.paiement_id}>
            <Carte className="flex flex-wrap items-center justify-between gap-3">
              <span className="flex flex-col">
                <span className="font-medium text-titre">
                  {formaterFcfa(element.montant)} · facture {element.facture_numero}
                </span>
                <span className="text-petit text-texte-doux">
                  Reçu le {formaterDate(element.recu_le)}
                  {element.reference ? ` · référence ${element.reference}` : ''}
                </span>
              </span>
              <Bouton variante="contour" taille="sm" onClick={() => setCible(element)}>
                Noter le remboursement
              </Bouton>
            </Carte>
          </li>
        ))}
      </ul>
      {cible ? <NoterRemboursement element={cible} onFermer={() => setCible(null)} /> : null}
    </section>
  )
}

function NoterRemboursement({ element, onFermer }: { element: TropPercu; onFermer: () => void }) {
  const cache = useQueryClient()
  const toast = useToast()
  const cle = useRef(nouvelleCle())
  const [motif, setMotif] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)

  async function noter() {
    setEnvoi(true)
    setErreur(null)
    try {
      await rembourserTropPercu(element.paiement_id, motif, cle.current)
      toast.succes(`Remboursement de ${formaterFcfa(element.montant)} noté.`)
      await cache.invalidateQueries({ queryKey: ['admin'] })
      onFermer()
    } catch (probleme) {
      cle.current = nouvelleCle()
      setErreur(messageErreur(probleme, 'Le remboursement n’a pas pu être noté.'))
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Modale
      ouverte
      onFermer={onFermer}
      taille="petite"
      titre={`Remboursement de ${formaterFcfa(element.montant)}`}
      description={`Confirmez que le client a été remboursé depuis le tableau de bord SasPay (facture ${element.facture_numero}).`}
      pied={
        <>
          <Bouton variante="contour" onClick={onFermer}>
            Annuler
          </Bouton>
          <Bouton chargement={envoi} disabled={motif.trim() === ''} onClick={() => void noter()}>
            Noter le remboursement
          </Bouton>
        </>
      }
    >
      <Champ libelle="Référence ou motif" aide="Par exemple la date et la référence du remboursement chez SasPay.">
        {(liaison) => <ZoneTexte {...liaison} rows={3} maxLength={500} value={motif} onChange={(evenement) => setMotif(evenement.target.value)} />}
      </Champ>
      {erreur ? (
        <Alerte ton="danger" urgente className="mt-3">
          {erreur}
        </Alerte>
      ) : null}
    </Modale>
  )
}

function ListeReversements() {
  const [page, setPage] = useState(1)
  const reversements = useQuery({ queryKey: ['admin', 'reversements', page], queryFn: () => listerReversementsAdmin(page), placeholderData: keepPreviousData })

  const colonnes: Colonne<ReversementAdmin>[] = [
    {
      cle: 'dev',
      entete: 'Dev',
      principale: true,
      rendu: (element) => (
        <span className="flex flex-col">
          <span className="font-medium text-titre">{element.dev.nom || element.dev.username}</span>
          <span className="text-petit text-texte-doux">{formaterDate(element.cree_le)}</span>
        </span>
      ),
    },
    { cle: 'numero', entete: 'Numéro', rendu: (element) => <span className="font-mono text-petit">{element.numero_masque}</span> },
    { cle: 'montant', entete: 'Montant', alignement: 'droite', rendu: (element) => <Montant valeur={element.montant} className="text-titre" /> },
    { cle: 'frais', entete: 'Frais', alignement: 'droite', rendu: (element) => (element.frais === null ? '—' : formaterFcfa(element.frais)) },
    {
      cle: 'etat',
      entete: 'État',
      rendu: (element) => {
        const etat = presentationReversement(element)
        return <PastilleEtat ton={etat.ton} libelle={etat.libelle} enCours={etat.enCours} />
      },
    },
  ]

  if (reversements.isPending) return <SqueletteLignes lignes={4} />
  if (reversements.isError || !reversements.data) return <EtatErreur onReessayer={() => void reversements.refetch()} />
  if (reversements.data.data.length === 0) return <p className="m-0 rounded-moyen border border-dashed border-bordure p-6 text-center text-texte-doux">Aucun reversement pour l’instant.</p>

  return (
    <div className="flex flex-col gap-4">
      <Tableau legende="Reversements aux devs" colonnes={colonnes} lignes={reversements.data.data} cleLigne={(element) => element.id} />
      <Pagination page={page} dernierePage={reversements.data.meta.last_page} onChange={setPage} />
    </div>
  )
}

function ListeRapprochements() {
  const [page, setPage] = useState(1)
  const rapprochements = useQuery({ queryKey: ['admin', 'rapprochements', page], queryFn: () => listerRapprochements(page), placeholderData: keepPreviousData })

  const colonnes: Colonne<Rapprochement>[] = [
    { cle: 'jour', entete: 'Jour', principale: true, rendu: (element) => <span className="font-medium text-titre">{formaterDate(element.jour)}</span> },
    { cle: 'saspay', entete: 'Transactions SasPay', alignement: 'droite', rendu: (element) => element.transactions_saspay },
    { cle: 'locaux', entete: 'Paiements enregistrés', alignement: 'droite', rendu: (element) => element.paiements_locaux },
    {
      cle: 'ecarts',
      entete: 'Écarts',
      rendu: (element) => {
        const aTraiter = element.ecarts.filter((ecart) => ecart.type !== 'corrige').length
        const corriges = element.ecarts.length - aTraiter
        if (element.ecarts.length === 0) return <PastilleEtat ton="succes" libelle="Aucun" />
        return <PastilleEtat ton={aTraiter > 0 ? 'danger' : 'info'} libelle={aTraiter > 0 ? `${aTraiter} à vérifier` : `${corriges} corrigé${corriges > 1 ? 's' : ''}`} />
      },
    },
  ]

  if (rapprochements.isPending) return <SqueletteLignes lignes={4} />
  if (rapprochements.isError || !rapprochements.data) return <EtatErreur onReessayer={() => void rapprochements.refetch()} />
  if (rapprochements.data.data.length === 0) {
    return <p className="m-0 rounded-moyen border border-dashed border-bordure p-6 text-center text-texte-doux">Le premier rapprochement tourne cette nuit, à 2 h 30.</p>
  }

  return (
    <div className="flex flex-col gap-4">
      <Tableau legende="Rapprochements quotidiens avec SasPay" colonnes={colonnes} lignes={rapprochements.data.data} cleLigne={(element) => element.id} />
      <Pagination page={page} dernierePage={rapprochements.data.meta.last_page} onChange={setPage} />
    </div>
  )
}
