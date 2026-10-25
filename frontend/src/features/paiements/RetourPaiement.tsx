import { useRef, useState, type ReactNode } from 'react'
import { useSearchParams } from 'react-router'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { motion, useReducedMotion } from 'motion/react'
import { CircleCheck, CircleX, ExternalLink, Hourglass } from 'lucide-react'
import { useCompte } from '../../app/compte'
import { useCanalPrive } from '../../app/tempsReelPrive'
import { useTitrePage } from '../../app/titre'
import { AccesRefuse, Alerte, Bouton, Carte, EtatErreur, Etapes, LienBouton, Montant, Squelette, cx, formaterDate, type Etape } from '../../ui'
import { allerPayer, lireCopie, messageErreur, nouvelleCle, verifierFacture, type Deploiement, type Facture } from './api'

/** 20 vérifications espacées de 3 secondes : une minute pour valider sur le téléphone. */
const VERIFICATIONS_MAX = 20

type Issue = 'confirme' | 'refuse' | 'attente' | 'annulee'

function issueDe(facture: Facture): Issue {
  if (facture.statut === 'payee' || facture.statut === 'remboursee') return 'confirme'
  if (facture.statut === 'annulee') return 'annulee'
  const dernier = facture.paiements[0]
  if (dernier && (dernier.statut === 'echoue' || dernier.statut === 'annule')) return 'refuse'
  return 'attente'
}

/**
 * Retour de SasPay : le résultat du paiement, vérifié par l'API (jamais sur la foi de l'adresse),
 * puis la copie qui démarre, étape par étape. Mis à jour en direct (Reverb) et par vérifications.
 */
export function RetourPaiement() {
  const [parametres] = useSearchParams()
  const factureId = Number(parametres.get('facture'))
  const valide = Number.isInteger(factureId) && factureId > 0
  const cache = useQueryClient()
  const compte = useCompte()
  const [erreur, setErreur] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)
  const cle = useRef(nouvelleCle())

  const verification = useQuery({
    queryKey: ['facture', factureId, 'verification'],
    queryFn: () => verifierFacture(factureId),
    enabled: valide && Boolean(compte.data),
    retry: 1,
    refetchInterval: (requete) => {
      const facture = requete.state.data
      return facture && issueDe(facture) === 'attente' && requete.state.dataUpdateCount < VERIFICATIONS_MAX ? 3000 : false
    },
  })
  const facture = verification.data
  const issue = facture ? issueDe(facture) : null

  const copie = useQuery({
    queryKey: ['copie', facture?.deploiement_id],
    queryFn: () => lireCopie(facture?.deploiement_id ?? 0),
    enabled: issue === 'confirme' && Boolean(facture?.deploiement_id),
    refetchInterval: (requete) => (requete.state.data && ['en_ligne', 'echec', 'arrete'].includes(requete.state.data.etat) ? false : 5000),
  })

  useCanalPrive<Facture>(compte.data ? `utilisateur.${compte.data.id}` : null, 'facture.modifiee', (recue) => {
    if (recue.id === factureId) cache.setQueryData(['facture', factureId, 'verification'], recue)
    void cache.invalidateQueries({ queryKey: ['factures'] })
  })
  useCanalPrive<Deploiement>(
    facture?.deploiement_id ? `deploiement.${facture.deploiement_id}` : null,
    'deploiement.etat',
    (recue) => cache.setQueryData(['copie', recue.id], recue),
    () => void cache.invalidateQueries({ queryKey: ['copie', facture?.deploiement_id] }),
  )

  useTitrePage(issue === 'confirme' ? 'Paiement confirmé' : issue === 'refuse' ? 'Paiement refusé' : 'Résultat du paiement')

  async function reessayer() {
    if (!facture) return
    setEnvoi(true)
    setErreur(null)
    try {
      // Nouvelle tentative = nouvelle intention, donc nouvelle clé.
      cle.current = nouvelleCle()
      await allerPayer(facture.id, cle.current)
    } catch (probleme) {
      setErreur(messageErreur(probleme, 'Le paiement n’a pas pu s’ouvrir. Réessayez dans un instant.'))
      setEnvoi(false)
    }
  }

  if (!valide) {
    return (
      <Conteneur>
        <EtatErreur titre="Lien de retour incomplet" description="Retrouvez vos factures et leur état dans « Mes paiements »." />
        <div className="mt-4 flex justify-center">
          <LienBouton to="/paiements">Voir mes paiements</LienBouton>
        </div>
      </Conteneur>
    )
  }

  if (compte.isPending || (verification.isPending && verification.fetchStatus !== 'idle')) {
    return (
      <Conteneur>
        <div role="status" aria-label="Vérification du paiement auprès de SasPay" className="flex flex-col items-center gap-4 py-10">
          <Squelette className="size-16 rounded-pastille" />
          <Squelette className="h-8 w-64" />
          <Squelette className="h-4 w-80" />
        </div>
      </Conteneur>
    )
  }

  if (!compte.data) {
    return (
      <Conteneur>
        <AccesRefuse description="Connectez-vous pour voir le résultat de votre paiement." action={<LienBouton to="/connexion">Se connecter</LienBouton>} />
      </Conteneur>
    )
  }

  if (!facture || !issue) {
    return (
      <Conteneur>
        <EtatErreur
          titre="Impossible de vérifier ce paiement"
          description={messageErreur(verification.error, 'SasPay ne répond pas pour le moment. Votre paiement n’est pas perdu : réessayez dans un instant.')}
          onReessayer={() => void verification.refetch()}
        />
      </Conteneur>
    )
  }

  const verificationsFaites = cache.getQueryState(['facture', factureId, 'verification'])?.dataUpdateCount ?? 0
  const enAttenteLongue = issue === 'attente' && verificationsFaites >= VERIFICATIONS_MAX

  return (
    <Conteneur>
      <Carte marge="large" className="flex flex-col items-center gap-5 text-center shadow-2">
        <IconeIssue issue={issue} />
        <div aria-live="polite" className="flex flex-col gap-2">
          <h1 className="m-0 text-titre-1 text-titre">{titres[issue]}</h1>
          <p className="m-0 max-w-[48ch] text-corps-l text-texte">{description(issue, facture, enAttenteLongue)}</p>
        </div>

        <dl className="m-0 grid w-full max-w-md grid-cols-2 gap-x-6 gap-y-2 rounded-moyen border border-bordure bg-surface-douce p-4 text-left text-corps">
          <dt className="text-texte-doux">Facture</dt>
          <dd className="m-0 text-right font-medium text-titre">{facture.numero}</dd>
          <dt className="text-texte-doux">Appli</dt>
          <dd className="m-0 text-right text-titre">{facture.app_nom}</dd>
          <dt className="text-texte-doux">Montant</dt>
          <dd className="m-0 text-right text-titre">
            <Montant valeur={facture.montant_total} />
          </dd>
          {facture.paiements[0]?.reference ? (
            <>
              <dt className="text-texte-doux">Référence SasPay</dt>
              <dd className="m-0 truncate text-right font-mono text-petit text-titre">{facture.paiements[0].reference}</dd>
            </>
          ) : null}
        </dl>

        {issue === 'confirme' ? <SuiviCopie copie={copie.data} /> : null}

        {erreur ? (
          <Alerte ton="danger" urgente className="w-full max-w-md text-left">
            {erreur}
          </Alerte>
        ) : null}

        <div className="flex w-full max-w-md flex-col gap-3 tablette:flex-row tablette:justify-center">
          {issue === 'refuse' ? (
            <>
              <Bouton taille="lg" chargement={envoi} onClick={() => void reessayer()}>
                Réessayer le paiement
              </Bouton>
              <LienBouton to="/paiements" variante="contour" taille="lg">
                Voir mes paiements
              </LienBouton>
            </>
          ) : null}
          {issue === 'attente' ? (
            <>
              {enAttenteLongue ? (
                <Bouton taille="lg" onClick={() => void verification.refetch()} chargement={verification.isFetching}>
                  Vérifier à nouveau
                </Bouton>
              ) : null}
              <LienBouton to="/paiements" variante="contour" taille="lg">
                Voir mes paiements
              </LienBouton>
            </>
          ) : null}
          {issue === 'confirme' ? (
            <>
              {copie.data?.etat === 'en_ligne' && copie.data.url ? (
                <a href={copie.data.url} target="_blank" rel="noopener noreferrer" className={classeLienPrincipal}>
                  Ouvrir ma copie
                  <ExternalLink aria-hidden="true" className="size-4" />
                </a>
              ) : null}
              <LienBouton to="/copies" variante={copie.data?.etat === 'en_ligne' ? 'contour' : 'primaire'} taille="lg">
                Suivre mes copies
              </LienBouton>
              <LienBouton to={`/paiements/${facture.id}`} variante="fantome" taille="lg">
                Voir le reçu
              </LienBouton>
            </>
          ) : null}
          {issue === 'annulee' ? (
            <LienBouton to="/store" taille="lg">
              Retour au Store
            </LienBouton>
          ) : null}
        </div>
      </Carte>
    </Conteneur>
  )
}

const classeLienPrincipal =
  'inline-flex h-12 items-center justify-center gap-2 rounded-moyen bg-primaire px-5 text-corps-l font-medium text-sur-primaire no-underline shadow-1 transition-colors duration-150 ease-souk hover:bg-primaire-survol'

const titres: Record<Issue, string> = {
  confirme: 'Paiement confirmé',
  refuse: 'Paiement refusé',
  attente: 'Paiement en attente de validation',
  annulee: 'Commande annulée',
}

function description(issue: Issue, facture: Facture, longue: boolean): string {
  if (issue === 'confirme') return `Merci ! SasPay a confirmé votre paiement. Le reçu est dans « Mes paiements ».`
  if (issue === 'refuse') return 'Aucun montant n’a été débité. Le réseau a refusé l’opération : vérifiez votre solde ou le code saisi, puis réessayez.'
  if (issue === 'annulee') return `La facture ${facture.numero} a été annulée : rien ne vous sera demandé.`
  return longue
    ? 'SasPay n’a pas encore la réponse de votre réseau. Vous recevrez un e-mail dès que le paiement sera confirmé ; vous pouvez fermer cette page.'
    : 'Validez l’opération sur votre téléphone si ce n’est pas déjà fait. Cette page se met à jour toute seule.'
}

function IconeIssue({ issue }: { issue: Issue }) {
  const reduit = useReducedMotion()
  const Icone = issue === 'confirme' ? CircleCheck : issue === 'attente' ? Hourglass : CircleX
  const teinte = issue === 'confirme' ? 'bg-fond-succes text-succes' : issue === 'attente' ? 'bg-fond-info text-info' : issue === 'annulee' ? 'bg-surface-douce text-texte-doux' : 'bg-fond-danger text-danger'
  return (
    <motion.span
      key={issue}
      aria-hidden="true"
      initial={reduit ? { opacity: 0 } : { opacity: 0, scale: 0.6 }}
      animate={{ opacity: 1, scale: 1 }}
      transition={{ duration: 0.3, ease: [0.2, 0.7, 0.2, 1] }}
      className={cx('grid size-16 place-items-center rounded-pastille', teinte)}
    >
      <Icone className={cx('size-8', issue === 'attente' && !reduit && 'animate-pulsation')} />
    </motion.span>
  )
}

/** Les étapes après le paiement : la copie démarre (ou reprend) jusqu'à être en ligne. */
function SuiviCopie({ copie }: { copie: Deploiement | undefined }) {
  const etat = copie?.etat
  const enCours = etat === undefined || ['en_file', 'construction', 'demarrage', 'suspendu'].includes(etat)
  const etapes: Etape[] = [
    { id: 'paiement', libelle: 'Paiement reçu', etat: 'terminee' },
    {
      id: 'construction',
      libelle: etat === 'suspendu' ? 'Réactivation de votre copie' : 'Construction de votre copie',
      description: 'Quelques minutes. Vous pouvez quitter cette page : la copie continue de démarrer.',
      etat: etat === 'echec' ? 'echec' : etat === 'en_ligne' ? 'terminee' : enCours ? 'en_cours' : 'a_venir',
    },
    {
      id: 'en_ligne',
      libelle: etat === 'en_ligne' ? 'Votre copie est en ligne' : 'Mise en ligne',
      description: etat === 'en_ligne' && copie?.abonnement?.prochaine_echeance ? `Prochaine facture le ${formaterDate(copie.abonnement.prochaine_echeance)}, avec un rappel 3 jours avant.` : undefined,
      etat: etat === 'en_ligne' ? 'terminee' : 'a_venir',
    },
  ]

  return (
    <div className="w-full max-w-md text-left">
      <Etapes libelle="Démarrage de votre copie" etapes={etapes} />
      {etat === 'echec' ? (
        <Alerte ton="danger" className="mt-4" titre="La copie n’a pas pu démarrer">
          {copie?.erreur ?? 'Le support est prévenu et revient vers vous. Votre paiement est bien enregistré.'}
        </Alerte>
      ) : null}
    </div>
  )
}

function Conteneur({ children }: { children: ReactNode }) {
  return <div className="mx-auto w-full max-w-2xl px-4 py-8 tablette:py-12">{children}</div>
}
