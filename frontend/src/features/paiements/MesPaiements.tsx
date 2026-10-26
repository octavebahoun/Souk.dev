import { useRef, useState } from 'react'
import { useSearchParams } from 'react-router'
import { keepPreviousData, useQuery, useQueryClient } from '@tanstack/react-query'
import { CircleCheck } from 'lucide-react'
import { useCompte } from '../../app/compte'
import { useCanalPrive } from '../../app/tempsReelPrive'
import { useTitrePage } from '../../app/titre'
import {
  Alerte,
  AvisDonneesPartielles,
  Bouton,
  Carte,
  EnTetePage,
  EtatErreur,
  EtatVide,
  LienBouton,
  Montant,
  Onglets,
  Pagination,
  PastilleEtat,
  SqueletteLignes,
  Tableau,
  cx,
  formaterDate,
  formaterFcfa,
  formaterPeriode,
  type Colonne,
} from '../../ui'
import { listerCopies } from '../catalogue/api'
import { allerPayer, listerFactures, messageErreur, nouvelleCle, verifierFacture, type Facture } from './api'
import { enValidation, phraseEcheance, presentationFacture } from './presentation'

type Filtre = 'toutes' | 'a_payer' | 'payee'

/**
 * « Mes paiements » : ce qui est à payer d'abord (une action par facture), puis l'historique
 * et les reçus. Mis à jour en direct quand une facture est émise ou payée.
 */
export function MesPaiements() {
  useTitrePage('Mes paiements')
  const cache = useQueryClient()
  const compte = useCompte()
  const [parametres] = useSearchParams()
  const [filtre, setFiltre] = useState<Filtre>('toutes')
  const [page, setPage] = useState(1)

  const aPayer = useQuery({ queryKey: ['factures', 'a_payer', 1], queryFn: () => listerFactures({ statut: 'a_payer' }) })
  const historique = useQuery({
    queryKey: ['factures', filtre, page],
    queryFn: () => listerFactures({ statut: filtre === 'toutes' ? undefined : filtre, page }),
    placeholderData: keepPreviousData,
  })

  useCanalPrive<Facture>(
    compte.data ? `utilisateur.${compte.data.id}` : null,
    'facture.modifiee',
    () => void cache.invalidateQueries({ queryKey: ['factures'] }),
    () => void cache.invalidateQueries({ queryKey: ['factures'] }),
  )

  const copies = useQuery({ queryKey: ['copies'], queryFn: listerCopies })
  const dues = aPayer.data?.data ?? []
  const totalDu = dues.reduce((somme, facture) => somme + facture.montant_total, 0)
  // La prochaine facture, pour rassurer quand rien n'est dû.
  const prochaine = (copies.data?.data ?? [])
    .filter((copie) => copie.abonnement?.prochaine_echeance && ['actif', 'en_retard'].includes(copie.abonnement.statut))
    .sort((a, b) => String(a.abonnement?.prochaine_echeance).localeCompare(String(b.abonnement?.prochaine_echeance)))[0]
  const aucuneFacture = filtre === 'toutes' && page === 1 && historique.data?.meta.total === 0

  const colonnes: Colonne<Facture>[] = [
    {
      cle: 'facture',
      entete: 'Facture',
      principale: true,
      rendu: (facture) => (
        <span className="flex flex-col">
          <span className="font-medium text-titre">{facture.app_nom}</span>
          <span className="text-petit text-texte-doux">
            {facture.numero} · copie n° {facture.deploiement_id}
          </span>
        </span>
      ),
    },
    { cle: 'periode', entete: 'Période', rendu: (facture) => <span className="text-texte">{formaterPeriode(facture.periode_debut, facture.periode_fin)}</span> },
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
      rendu: (facture) => (
        <LienBouton to={`/paiements/${facture.id}`} variante="fantome" taille="sm" aria-label={`${facture.statut === 'payee' ? 'Reçu' : 'Détails'} de la facture ${facture.numero}`}>
          {facture.statut === 'payee' ? 'Reçu' : 'Détails'}
        </LienBouton>
      ),
    },
  ]

  return (
    <div className="flex flex-col gap-8">
      <EnTetePage titre="Mes paiements" description="Les factures de vos copies (hébergement et applis), leur état et leurs reçus." />

      {parametres.get('indisponible') ? (
        <Alerte ton="alerte" titre="Le paiement est momentanément indisponible">
          SasPay ne répond pas pour le moment. Votre facture reste à payer ; réessayez dans quelques minutes.
        </Alerte>
      ) : null}

      {aPayer.isError ? (
        <AvisDonneesPartielles>Les factures à payer n’ont pas pu être chargées. Rechargez la page dans un instant ; l’historique ci-dessous reste juste.</AvisDonneesPartielles>
      ) : null}

      {dues.length > 0 ? (
        <section aria-labelledby="titre-a-payer" className="flex flex-col gap-3">
          <div className="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="titre-a-payer" className="m-0 text-titre-3 text-titre">
              À payer
            </h2>
            {dues.length > 1 ? <p className="m-0 text-petit text-texte-doux">{formaterFcfa(totalDu)} au total</p> : null}
          </div>
          <ul className="m-0 flex list-none flex-col gap-3 p-0">
            {dues.map((facture) => (
              <li key={facture.id}>
                <FactureAPayer facture={facture} />
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      {aPayer.isSuccess && dues.length === 0 && prochaine?.abonnement?.prochaine_echeance ? (
        <Carte className="flex items-start gap-3">
          <CircleCheck aria-hidden="true" className="mt-0.5 size-5 shrink-0 text-succes" />
          <div className="flex flex-col gap-0.5">
            <p className="m-0 font-medium text-titre">Vous êtes à jour</p>
            <p className="m-0 text-petit text-texte">
              Prochaine facture : {prochaine.app_nom}, le {formaterDate(prochaine.abonnement.prochaine_echeance)} (
              {formaterFcfa(prochaine.abonnement.montant_hebergement + (prochaine.abonnement.type_prix === 'mensuel' ? prochaine.abonnement.prix_appli : 0))}). Elle arrive 3 jours
              avant, avec un lien de paiement.
            </p>
          </div>
        </Carte>
      ) : null}

      {aucuneFacture ? (
        <EtatVide
          illustration="vide-paiements"
          titre="Aucune facture pour l’instant"
          description="Quand vous commandez la copie d’une appli, ses factures et leurs reçus arrivent ici."
          action={<LienBouton to="/store">Parcourir le Store</LienBouton>}
        />
      ) : (
        <section aria-labelledby="titre-historique" className="flex flex-col gap-3">
          <h2 id="titre-historique" className="m-0 text-titre-3 text-titre">
            Historique
          </h2>
          <Onglets
            libelle="Filtrer les factures"
            valeur={filtre}
            onChange={(valeur) => {
              setFiltre(valeur)
              setPage(1)
            }}
            onglets={[
              { valeur: 'toutes', libelle: 'Toutes' },
              { valeur: 'a_payer', libelle: 'À payer', compteur: aPayer.data?.meta.total },
              { valeur: 'payee', libelle: 'Payées' },
            ]}
          >
            {historique.isPending ? (
              <SqueletteLignes lignes={4} className="mt-4" />
            ) : historique.isError || !historique.data ? (
              <EtatErreur className="mt-4" onReessayer={() => void historique.refetch()} />
            ) : historique.data.data.length === 0 ? (
              <p className="m-0 mt-4 rounded-moyen border border-dashed border-bordure p-6 text-center text-texte-doux">Aucune facture dans cette liste.</p>
            ) : (
              <div className={cx('mt-4 flex flex-col gap-4 transition-opacity duration-150', historique.isPlaceholderData && 'opacity-60')}>
                <Tableau legende="Factures" colonnes={colonnes} lignes={historique.data.data} cleLigne={(facture) => facture.id} />
                <Pagination page={page} dernierePage={historique.data.meta.last_page} onChange={setPage} />
              </div>
            )}
          </Onglets>
        </section>
      )}
    </div>
  )
}

function FactureAPayer({ facture }: { facture: Facture }) {
  const cache = useQueryClient()
  const [envoi, setEnvoi] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)
  const cle = useRef(nouvelleCle())
  const etat = presentationFacture(facture)
  const validation = enValidation(facture)

  async function payer() {
    setEnvoi(true)
    setErreur(null)
    try {
      await allerPayer(facture.id, cle.current)
    } catch (probleme) {
      // Une erreur clôt cette tentative : la suivante est une nouvelle intention.
      cle.current = nouvelleCle()
      setErreur(messageErreur(probleme, 'Le paiement n’a pas pu s’ouvrir. Réessayez dans un instant.'))
      setEnvoi(false)
    }
  }

  async function verifier() {
    setEnvoi(true)
    setErreur(null)
    try {
      await verifierFacture(facture.id)
      await cache.invalidateQueries({ queryKey: ['factures'] })
    } catch (probleme) {
      setErreur(messageErreur(probleme, 'La vérification n’a pas abouti. Réessayez dans un instant.'))
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Carte className={cx('flex flex-col gap-3 border-l-4', etat.ton === 'danger' ? 'border-l-danger-solide' : 'border-l-alerte-solide')}>
      <div className="flex flex-col gap-3 tablette:flex-row tablette:items-center tablette:justify-between">
        <div className="flex min-w-0 flex-col gap-1">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="m-0 text-corps-l font-medium text-titre">{facture.app_nom}</h3>
            <PastilleEtat ton={etat.ton} libelle={etat.libelle} />
          </div>
          <p className="m-0 text-petit text-texte-doux">
            {facture.numero} · copie n° {facture.deploiement_id} · {formaterPeriode(facture.periode_debut, facture.periode_fin)} · {phraseEcheance(facture)}
          </p>
        </div>
        <div className="flex flex-col gap-2 tablette:flex-row tablette:items-center">
          <LienBouton to={`/paiements/${facture.id}`} variante="fantome" aria-label={`Détails de la facture ${facture.numero}`}>
            Détails
          </LienBouton>
          {validation ? (
            <Bouton variante="secondaire" chargement={envoi} onClick={() => void verifier()}>
              Vérifier le paiement
            </Bouton>
          ) : (
            <Bouton chargement={envoi} onClick={() => void payer()}>
              Payer {formaterFcfa(facture.montant_total)}
            </Bouton>
          )}
        </div>
      </div>
      {validation ? (
        <Alerte ton="info" titre="Paiement en cours de validation">
          Confirmez l’opération sur votre téléphone. Ne payez pas une seconde fois : la facture se met à jour dès la réponse du réseau.
        </Alerte>
      ) : null}
      {erreur ? (
        <Alerte ton="danger" urgente>
          {erreur}
        </Alerte>
      ) : null}
    </Carte>
  )
}
