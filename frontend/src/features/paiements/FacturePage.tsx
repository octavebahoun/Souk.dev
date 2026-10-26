import { useRef, useState } from 'react'
import { useParams } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { Printer } from 'lucide-react'
import { useCompte } from '../../app/compte'
import { useTitrePage } from '../../app/titre'
import { Alerte, Bouton, Carte, EnTetePage, EtatErreur, EtatVide, LienBouton, Logo, Montant, PastilleEtat, Squelette, SqueletteLignes, formaterDate, formaterFcfa } from '../../ui'
import { allerPayer, lireFacture, messageErreur, nouvelleCle, statutHttp } from './api'
import { enValidation, phraseEcheance, presentationFacture, presentationPaiement } from './presentation'

/**
 * Une facture et, une fois payée, son reçu imprimable (la page s'imprime seule, sans la coquille
 * de l'application). Action principale : payer, ou imprimer le reçu.
 */
export function FacturePage() {
  const { id = '' } = useParams()
  const factureId = Number(id)
  const compte = useCompte()
  const facture = useQuery({
    queryKey: ['facture', factureId],
    queryFn: () => lireFacture(factureId),
    enabled: Number.isInteger(factureId) && factureId > 0,
    // Paiement en cours de validation : relu toutes les 10 secondes jusqu'à la réponse du réseau.
    refetchInterval: (requete) => (requete.state.data && enValidation(requete.state.data) ? 10_000 : false),
  })
  const [envoi, setEnvoi] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)
  const cle = useRef(nouvelleCle())
  const donnees = facture.data
  const payee = donnees?.statut === 'payee'
  useTitrePage(donnees ? `${payee ? 'Reçu' : 'Facture'} ${donnees.numero}` : 'Facture')

  if (facture.isPending && facture.fetchStatus !== 'idle') {
    return (
      <div role="status" aria-label="Chargement de la facture" className="flex flex-col gap-6">
        <Squelette className="h-4 w-56" />
        <Squelette className="h-9 w-72" />
        <Carte marge="large">
          <SqueletteLignes lignes={6} />
        </Carte>
      </div>
    )
  }

  if (!donnees) {
    return statutHttp(facture.error) === 404 ? (
      <EtatVide
        illustration="vide-paiements"
        titre="Facture introuvable"
        description="Elle n’existe pas ou n’est pas à votre nom. Retrouvez vos factures dans « Mes paiements »."
        action={<LienBouton to="/paiements">Mes paiements</LienBouton>}
      />
    ) : (
      <EtatErreur onReessayer={() => void facture.refetch()} />
    )
  }

  const etat = presentationFacture(donnees)
  const reussi = donnees.paiements.find((paiement) => paiement.statut === 'reussi')

  async function payer() {
    setEnvoi(true)
    setErreur(null)
    try {
      await allerPayer(factureId, cle.current)
    } catch (probleme) {
      cle.current = nouvelleCle()
      setErreur(messageErreur(probleme, 'Le paiement n’a pas pu s’ouvrir. Réessayez dans un instant.'))
      setEnvoi(false)
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <EnTetePage
        className="ne-pas-imprimer"
        filAriane={[{ libelle: 'Mes paiements', vers: '/paiements' }, { libelle: donnees.numero }]}
        titre={`${payee ? 'Reçu' : 'Facture'} ${donnees.numero}`}
        description={`${donnees.app_nom} · ${phraseEcheance(donnees)}`}
        actions={
          donnees.statut === 'a_payer' && !enValidation(donnees) ? (
            <Bouton chargement={envoi} onClick={() => void payer()}>
              Payer {formaterFcfa(donnees.montant_total)}
            </Bouton>
          ) : payee ? (
            <Bouton variante="contour" icone={<Printer aria-hidden="true" className="size-4" />} onClick={() => window.print()}>
              Imprimer le reçu
            </Bouton>
          ) : null
        }
      />

      {enValidation(donnees) ? (
        <Alerte ton="info" titre="Paiement en cours de validation" className="ne-pas-imprimer">
          Confirmez l’opération sur votre téléphone. Ne payez pas une seconde fois : cette page se met à jour dès la réponse du réseau.
        </Alerte>
      ) : null}
      {erreur ? (
        <Alerte ton="danger" urgente className="ne-pas-imprimer">
          {erreur}
        </Alerte>
      ) : null}

      <Carte as="article" marge="large" aria-label={`${payee ? 'Reçu' : 'Facture'} ${donnees.numero}`} className="imprimable flex flex-col gap-6">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div className="flex flex-col gap-1">
            <Logo />
            <p className="m-0 text-petit text-texte-doux">Hébergement de copies d’applis · Afrique de l’Ouest</p>
          </div>
          <div className="flex flex-col items-end gap-1 text-right">
            <p className="m-0 text-titre-3 text-titre">{payee ? 'Reçu de paiement' : 'Facture'}</p>
            <p className="m-0 font-mono text-petit text-texte">{donnees.numero}</p>
            <PastilleEtat ton={etat.ton} libelle={etat.libelle} />
          </div>
        </div>

        <dl className="m-0 grid gap-x-8 gap-y-3 text-corps tablette:grid-cols-2">
          <div>
            <dt className="text-petit text-texte-doux">Client</dt>
            <dd className="m-0 text-titre">{compte.data?.nom ?? compte.data?.username ?? '—'}</dd>
            {compte.data?.email ? <dd className="m-0 text-petit text-texte">{compte.data.email}</dd> : null}
          </div>
          <div>
            <dt className="text-petit text-texte-doux">Émise le</dt>
            <dd className="m-0 text-titre">{formaterDate(donnees.emise_le)}</dd>
          </div>
          <div>
            <dt className="text-petit text-texte-doux">Échéance</dt>
            <dd className="m-0 text-titre">{formaterDate(donnees.echeance_le)}</dd>
          </div>
          {donnees.payee_le ? (
            <div>
              <dt className="text-petit text-texte-doux">Payée le</dt>
              <dd className="m-0 text-titre">{formaterDate(donnees.payee_le, 'avecHeure')}</dd>
            </div>
          ) : null}
        </dl>

        <table className="w-full border-collapse text-left text-corps">
          <caption className="pour-lecteur">Détail de la facture</caption>
          <thead>
            <tr className="border-b border-bordure">
              <th scope="col" className="py-2 text-etiquette text-texte-doux uppercase">
                Désignation
              </th>
              <th scope="col" className="py-2 text-right text-etiquette text-texte-doux uppercase">
                Montant
              </th>
            </tr>
          </thead>
          <tbody>
            {donnees.lignes.map((ligne) => (
              <tr key={ligne.libelle} className="border-b border-bordure">
                <td className="py-3 pr-4 text-texte">{ligne.libelle}</td>
                <td className="py-3 text-right">
                  <Montant valeur={ligne.montant} className="text-titre" />
                </td>
              </tr>
            ))}
          </tbody>
          <tfoot>
            <tr>
              <th scope="row" className="pt-4 text-left font-medium text-titre">
                Total
              </th>
              <td className="pt-4 text-right">
                <Montant valeur={donnees.montant_total} className="text-titre-3 text-titre" />
              </td>
            </tr>
            {donnees.montant_rembourse > 0 ? (
              <tr>
                <th scope="row" className="pt-1 text-left font-normal text-texte-doux">
                  Remboursé
                </th>
                <td className="pt-1 text-right text-texte">− {formaterFcfa(donnees.montant_rembourse)}</td>
              </tr>
            ) : null}
          </tfoot>
        </table>

        {reussi ? (
          <p className="m-0 rounded-moyen bg-surface-douce p-4 text-petit text-texte">
            Payé {formaterFcfa(reussi.montant_debite ?? reussi.montant)} par SasPay
            {reussi.frais_client ? `, dont ${formaterFcfa(reussi.frais_client)} de frais du moyen de paiement` : ''}
            {reussi.reference ? ` · référence ${reussi.reference}` : ''}.
          </p>
        ) : null}

        <p className="m-0 border-t border-bordure pt-4 text-petit text-texte-doux">
          Le prix de l’appli est reversé en entier à son auteur ; Souk.dev facture l’hébergement de la copie. Montants en francs CFA (XOF).
        </p>
      </Carte>

      {donnees.paiements.length > 0 ? (
        <section aria-labelledby="titre-tentatives" className="ne-pas-imprimer flex flex-col gap-3">
          <h2 id="titre-tentatives" className="m-0 text-titre-3 text-titre">
            Tentatives de paiement
          </h2>
          <Carte marge="aucune">
            <ul className="m-0 list-none divide-y divide-bordure p-0">
              {donnees.paiements.map((paiement) => {
                const presentation = presentationPaiement(paiement)
                return (
                  <li key={paiement.id} className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <span className="flex flex-col">
                      <span className="text-corps text-titre">{formaterDate(paiement.cree_le, 'avecHeure')}</span>
                      {paiement.reference ? <span className="font-mono text-petit text-texte-doux">{paiement.reference}</span> : null}
                    </span>
                    <span className="flex items-center gap-4">
                      <Montant valeur={paiement.montant_debite ?? paiement.montant} className="text-titre" />
                      <PastilleEtat ton={presentation.ton} libelle={presentation.libelle} enCours={presentation.enCours} />
                    </span>
                  </li>
                )
              })}
            </ul>
          </Carte>
        </section>
      ) : null}
    </div>
  )
}
