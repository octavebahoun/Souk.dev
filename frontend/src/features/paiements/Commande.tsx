import { useRef, useState, type ReactNode } from 'react'
import { useParams } from 'react-router'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Globe, Power, Server, ShieldCheck } from 'lucide-react'
import { useCompte } from '../../app/compte'
import { useTitrePage } from '../../app/titre'
import { lireAppli, listerCopies } from '../catalogue/api'
import {
  AccesRefuse,
  Alerte,
  Bouton,
  Carte,
  Champ,
  EnTeteCarte,
  EnTetePage,
  EtatErreur,
  Etapes,
  LienBouton,
  Montant,
  Saisie,
  Squelette,
  cx,
  formaterFcfa,
} from '../../ui'
import { allerPayer, commanderCopie, erreursDeChamps, messageErreur, nouvelleCle } from './api'
import { formules } from './formules'
import { MoyensDePaiement } from './MoyensDePaiement'

const avantages = [
  { icone: Server, texte: 'Votre propre copie, isolée de celles des autres clients.' },
  { icone: Globe, texte: 'Une adresse web à vous, dès que la copie est en ligne.' },
  { icone: ShieldCheck, texte: 'Vos données gardées tant que l’abonnement est réglé.' },
  { icone: Power, texte: 'Arrêt à tout moment depuis « Mes copies », sans engagement.' },
]

/**
 * Commande d'une copie et paiement : un client pas forcément technicien doit savoir ce qu'il
 * achète, combien il paie aujourd'hui puis chaque mois, et payer en confiance (un seul bouton).
 */
export function Commande() {
  const { id = '' } = useParams()
  const appId = Number(id)
  const cache = useQueryClient()
  const compte = useCompte()
  const appli = useQuery({ queryKey: ['appli', appId], queryFn: () => lireAppli(appId), enabled: Number.isInteger(appId) && appId > 0 })
  const copies = useQuery({ queryKey: ['copies'], queryFn: listerCopies, enabled: Boolean(compte.data) })
  const [valeurs, setValeurs] = useState<Record<string, string>>({})
  const [erreursChamps, setErreursChamps] = useState<Record<string, string>>({})
  const [erreur, setErreur] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)
  // Une intention de paiement = une clé : un double clic ou un retour arrière ne recommence rien.
  const cle = useRef(nouvelleCle())
  const factureCommandee = useRef<number | null>(null)
  useTitrePage(appli.data ? `Commander ${appli.data.nom}` : 'Commander une copie')

  if (compte.isPending || (appli.isPending && appli.fetchStatus !== 'idle')) {
    return <SqueletteCommande />
  }

  if (!compte.data) {
    return (
      <Conteneur>
        <AccesRefuse
          description="Connectez-vous pour commander votre copie : elle sera à votre nom, et vous retrouverez vos factures dans « Mes paiements »."
          action={<LienBouton to="/connexion">Se connecter</LienBouton>}
        />
      </Conteneur>
    )
  }

  if (!appli.data) {
    return (
      <Conteneur>
        <EtatErreur
          titre="Cette appli est introuvable"
          description="Elle a peut-être été retirée du Store. Revenez au Store pour en choisir une autre."
          onReessayer={() => void appli.refetch()}
        />
      </Conteneur>
    )
  }

  const fiche = appli.data
  const formule = formules[fiche.taille]
  const enAttente = copies.data?.data.find((copie) => copie.app_id === fiche.id && copie.etat === 'attente_paiement' && copie.facture_a_payer)
  const premiereFacture = formule.prix + fiche.prix
  const mensuel = formule.prix + (fiche.type_prix === 'mensuel' ? fiche.prix : 0)
  const total = enAttente?.facture_a_payer?.montant_total ?? premiereFacture
  const variables = enAttente ? [] : fiche.variables_client
  const incomplet = variables.some((variable) => variable.requis && !(valeurs[variable.nom] ?? '').trim())
  const auteur = fiche.auteur.nom || fiche.auteur.username || 'son auteur'

  async function payer() {
    setEnvoi(true)
    setErreur(null)
    setErreursChamps({})
    try {
      let factureId = enAttente?.facture_a_payer?.id ?? factureCommandee.current
      if (factureId === null || factureId === undefined) {
        const copie = await commanderCopie(fiche.id, valeurs)
        factureId = copie.facture_a_payer?.id ?? null
        factureCommandee.current = factureId
        void cache.invalidateQueries({ queryKey: ['copies'] })
      }
      if (factureId === null) throw new Error('Facture absente')
      await allerPayer(factureId, cle.current)
    } catch (probleme) {
      const parChamp = erreursDeChamps(probleme)
      setErreursChamps(Object.fromEntries(Object.entries(parChamp).map(([champ, texte]) => [champ.replace(/^variables\./, ''), texte])))
      setErreur(messageErreur(probleme, 'Le paiement n’a pas pu s’ouvrir. Réessayez dans un instant.'))
      setEnvoi(false)
    }
  }

  return (
    <Conteneur>
      <EnTetePage
        filAriane={[{ libelle: 'Store', vers: '/store' }, { libelle: fiche.nom, vers: `/store/${fiche.id}` }, { libelle: 'Commander' }]}
        surTitre="Votre copie"
        titre={`Commander votre copie de ${fiche.nom}`}
        description="Une copie neuve et isolée, à votre nom, en ligne quelques minutes après le paiement."
      />

      {enAttente ? (
        <Alerte ton="info" titre="Une commande attend déjà votre paiement" className="mt-6">
          Payez-la ci-dessous : aucune seconde copie ne sera créée. Sans paiement, elle s’annule d’elle-même au bout de 7 jours.
        </Alerte>
      ) : null}

      <div className="mt-6 grid gap-6 bureau:grid-cols-[minmax(0,1fr)_24rem] bureau:items-start">
        <div className="flex min-w-0 flex-col gap-6">
          {variables.length > 0 ? (
            <Carte>
              <EnTeteCarte titre="Vos informations" description="Demandées par l’auteur de l’appli. Elles ne servent qu’à votre copie." />
              <div className="mt-4 flex flex-col gap-4">
                {variables.map((variable) => (
                  <Champ key={variable.nom} libelle={variable.libelle} facultatif={!variable.requis} erreur={erreursChamps[variable.nom]}>
                    {(liaison) => (
                      <Saisie
                        {...liaison}
                        value={valeurs[variable.nom] ?? ''}
                        onChange={(evenement) => setValeurs((courant) => ({ ...courant, [variable.nom]: evenement.target.value }))}
                        autoComplete="off"
                      />
                    )}
                  </Champ>
                ))}
              </div>
            </Carte>
          ) : null}

          <Carte>
            <EnTeteCarte titre="Ce que vous obtenez" />
            <ul className="m-0 mt-4 grid list-none gap-3 p-0 tablette:grid-cols-2">
              {avantages.map(({ icone: Icone, texte }) => (
                <li key={texte} className="flex items-start gap-3">
                  <span aria-hidden="true" className="grid size-9 shrink-0 place-items-center rounded-moyen bg-surface-accent text-sur-primaire-doux">
                    <Icone className="size-4.5" />
                  </span>
                  <span className="pt-1.5 text-corps">{texte}</span>
                </li>
              ))}
            </ul>
          </Carte>

          <Carte>
            <EnTeteCarte titre="Comment ça se passe" />
            <Etapes
              className="mt-4"
              libelle="Étapes de la commande"
              etapes={[
                { id: 'paiement', etat: 'a_venir', libelle: 'Vous payez chez SasPay', description: 'Mobile Money ou carte ; les frais du moyen de paiement s’ajoutent au montant.' },
                { id: 'construction', etat: 'a_venir', libelle: 'Nous construisons votre copie', description: 'Quelques minutes, à partir du code publié par l’auteur.' },
                { id: 'en_ligne', etat: 'a_venir', libelle: 'Votre copie est en ligne', description: 'Son adresse apparaît dans « Mes copies ». La facture suivante arrive un mois plus tard, avec un rappel 3 jours avant.' },
              ]}
            />
          </Carte>
        </div>

        <aside aria-labelledby="recapitulatif" className={cx('bureau:sticky bureau:top-24', variables.length > 0 ? '' : 'max-bureau:-order-1')}>
          <Carte marge="large" className="flex flex-col gap-5 shadow-2">
            <div className="flex flex-col gap-1">
              <h2 id="recapitulatif" className="m-0 text-titre-3 text-titre">Récapitulatif</h2>
              <p className="m-0 text-petit text-texte-doux">
                {fiche.nom}, par {auteur}
              </p>
            </div>

            <dl className="m-0 flex flex-col gap-3 text-corps">
              <div className="flex items-baseline justify-between gap-4">
                <dt className="text-texte">Appli {fiche.type_prix === 'unique' && fiche.prix > 0 ? '(paiement unique)' : ''}</dt>
                <dd className="m-0 text-titre">
                  <Montant valeur={fiche.prix} periode={fiche.prix > 0 ? (fiche.type_prix === 'mensuel' ? 'mois' : 'unique') : undefined} gratuitSiZero />
                </dd>
              </div>
              <div className="flex items-baseline justify-between gap-4">
                <dt className="text-texte">
                  Hébergement, formule {formule.nom.toLowerCase()}
                  <span className="block text-petit text-texte-doux">
                    {formule.memoire}, {formule.coeurs}
                  </span>
                </dt>
                <dd className="m-0 text-titre">
                  <Montant valeur={formule.prix} periode="mois" />
                </dd>
              </div>
              <div className="flex items-baseline justify-between gap-4">
                <dt className="text-texte">Commission Souk.dev</dt>
                <dd className="m-0 text-titre">
                  <Montant valeur={0} />
                </dd>
              </div>
            </dl>

            <div className="flex flex-col gap-1 border-t border-bordure pt-4">
              <div className="flex items-baseline justify-between gap-4">
                <span className="font-medium text-titre">À payer aujourd’hui</span>
                <Montant valeur={total} className="text-titre-2 text-titre" />
              </div>
              <p className="m-0 text-petit text-texte-doux">
                {mensuel === total ? 'Puis le même montant chaque mois.' : `Puis ${formaterFcfa(mensuel)} par mois.`} Frais Mobile Money ou carte en plus, affichés avant de valider.
              </p>
            </div>

            {erreur ? (
              <Alerte ton="danger" urgente>
                {erreur}
              </Alerte>
            ) : null}

            <Bouton taille="lg" pleineLargeur chargement={envoi} disabled={incomplet} onClick={() => void payer()}>
              Payer {formaterFcfa(total)}
            </Bouton>
            {incomplet ? <p className="-mt-3 m-0 text-petit text-texte-doux">Renseignez d’abord les informations demandées.</p> : null}

            <MoyensDePaiement />
            <p className="m-0 border-t border-bordure pt-4 text-petit text-texte-doux">
              Le prix de l’appli va entièrement à son auteur : Souk.dev ne prend aucune commission et ne gagne que sur l’hébergement.
            </p>
          </Carte>
        </aside>
      </div>
    </Conteneur>
  )
}

function Conteneur({ children }: { children: ReactNode }) {
  return <div className="mx-auto w-full max-w-espace px-4 py-6 tablette:px-6 tablette:py-8 bureau:px-8">{children}</div>
}

function SqueletteCommande() {
  return (
    <Conteneur>
      <div role="status" aria-label="Chargement de la commande" className="flex flex-col gap-6">
        <Squelette className="h-4 w-48" />
        <Squelette className="h-9 w-3/4 max-w-xl" />
        <div className="grid gap-6 bureau:grid-cols-[minmax(0,1fr)_24rem]">
          <div className="flex flex-col gap-6">
            <Squelette className="h-40 rounded-moyen" />
            <Squelette className="h-56 rounded-moyen" />
          </div>
          <Squelette className="h-96 rounded-moyen" />
        </div>
      </div>
    </Conteneur>
  )
}
