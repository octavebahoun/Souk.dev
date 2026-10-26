import { useEffect, useRef, useState } from 'react'
import { useSearchParams } from 'react-router'
import { keepPreviousData, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowDownToLine, CalendarClock } from 'lucide-react'
import { useCompte } from '../../app/compte'
import { useTitrePage } from '../../app/titre'
import {
  Alerte,
  Badge,
  Bouton,
  Carte,
  EnTeteCarte,
  EnTetePage,
  EtatErreur,
  EtatVide,
  LienBouton,
  Modale,
  Montant,
  Onglets,
  Pagination,
  PastilleEtat,
  Squelette,
  SqueletteLignes,
  Tableau,
  formaterDate,
  formaterFcfa,
  useToast,
  type Colonne,
} from '../../ui'
import { demanderReversement, lireRevenus, listerMouvements, listerReseaux, listerReversements, messageErreur, nouvelleCle, type Mouvement, type Reversement } from '../paiements/api'
import { LogoReseau } from '../paiements/LogoReseau'
import { libellesMouvement, presentationReversement } from '../paiements/presentation'
import { FormulaireCoordonnees } from './FormulaireCoordonnees'

type Vue = 'mouvements' | 'reversements'

/**
 * « Mes revenus » : le solde réel (grand livre), l'action pour être payé, le numéro Mobile Money
 * où l'argent arrive, puis l'historique. Le prix des applis revient en entier au dev.
 */
export function MesRevenus() {
  useTitrePage('Mes revenus')
  const cache = useQueryClient()
  const toast = useToast()
  const compte = useCompte()
  const [parametres, setParametres] = useSearchParams()
  const [vue, setVue] = useState<Vue>('mouvements')
  const [pageMouvements, setPageMouvements] = useState(1)
  const [pageReversements, setPageReversements] = useState(1)
  const [confirmation, setConfirmation] = useState(false)
  const [modification, setModification] = useState(false)
  const [envoi, setEnvoi] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)
  const cle = useRef(nouvelleCle())
  const formulaire = useRef<HTMLDivElement>(null)

  const revenus = useQuery({
    queryKey: ['revenus'],
    queryFn: lireRevenus,
    refetchInterval: (requete) => (requete.state.data?.reversement_en_cours ? 5000 : false),
  })
  const reseaux = useQuery({ queryKey: ['reseaux-mobile-money'], queryFn: listerReseaux, staleTime: 3_600_000 })
  const mouvements = useQuery({ queryKey: ['revenus', 'mouvements', pageMouvements], queryFn: () => listerMouvements(pageMouvements), placeholderData: keepPreviousData })
  const reversements = useQuery({ queryKey: ['revenus', 'reversements', pageReversements], queryFn: () => listerReversements(pageReversements), placeholderData: keepPreviousData })

  // Un reversement en cours qui se termine : l'historique (mouvements, reversements) se relit.
  const enCoursId = revenus.data?.reversement_en_cours?.id ?? null
  const precedent = useRef<number | null>(null)
  useEffect(() => {
    if (precedent.current !== null && enCoursId === null) {
      void cache.invalidateQueries({ queryKey: ['revenus', 'mouvements'] })
      void cache.invalidateQueries({ queryKey: ['revenus', 'reversements'] })
    }
    precedent.current = enCoursId
  }, [enCoursId, cache])

  const retourConfirmation = parametres.get('confirmation')
  useEffect(() => {
    if (retourConfirmation === 'ok') {
      toast.succes('Numéro confirmé : vos revenus partiront sur ce numéro.')
      setParametres((courants) => {
        courants.delete('confirmation')
        return courants
      }, { replace: true })
    }
  }, [retourConfirmation, setParametres, toast])

  async function recevoir() {
    setEnvoi(true)
    setErreur(null)
    try {
      const reversement = await demanderReversement(cle.current)
      cle.current = nouvelleCle()
      setConfirmation(false)
      if (reversement.statut === 'reussi') toast.succes(`Reversement effectué : ${formaterFcfa(reversement.montant)} envoyés sur votre Mobile Money.`)
      else if (reversement.statut === 'echoue') toast.erreur('Le réseau a refusé le reversement. Votre solde vous est rendu ; vérifiez votre numéro.')
      else toast.info('Reversement en cours : l’argent arrive sur votre Mobile Money dans quelques minutes.')
      await cache.invalidateQueries({ queryKey: ['revenus'] })
    } catch (probleme) {
      setErreur(messageErreur(probleme, 'Le reversement n’a pas pu partir. Réessayez dans un instant.'))
    } finally {
      setEnvoi(false)
    }
  }

  if (revenus.isPending) {
    return (
      <div role="status" aria-label="Chargement de vos revenus" className="flex flex-col gap-6">
        <Squelette className="h-9 w-56" />
        <Squelette className="h-44 rounded-grand" />
        <div className="grid gap-4 tablette:grid-cols-3">
          <Squelette className="h-24 rounded-moyen" />
          <Squelette className="h-24 rounded-moyen" />
          <Squelette className="h-24 rounded-moyen" />
        </div>
      </div>
    )
  }

  if (revenus.isError || !revenus.data) {
    return <EtatErreur onReessayer={() => void revenus.refetch()} />
  }

  const donnees = revenus.data
  const coordonnees = donnees.coordonnees
  const reseau = reseaux.data?.find((element) => element.code === coordonnees?.reseau)
  const nomReseau = reseau?.nom ?? coordonnees?.reseau ?? ''
  const enCours = donnees.reversement_en_cours
  const afficherFormulaire = modification || !coordonnees

  const colonnesMouvements: Colonne<Mouvement>[] = [
    {
      cle: 'libelle',
      entete: 'Mouvement',
      principale: true,
      rendu: (mouvement) => (
        <span className="flex flex-col">
          <span className="text-titre">{libellesMouvement[mouvement.type]}</span>
          <span className="text-petit text-texte-doux">{mouvement.libelle}</span>
        </span>
      ),
    },
    { cle: 'date', entete: 'Date', rendu: (mouvement) => formaterDate(mouvement.cree_le) },
    {
      cle: 'montant',
      entete: 'Montant',
      alignement: 'droite',
      rendu: (mouvement) => (
        <span className={mouvement.montant >= 0 ? 'text-succes' : 'text-titre'}>
          {mouvement.montant >= 0 ? '+ ' : '− '}
          {formaterFcfa(Math.abs(mouvement.montant))}
        </span>
      ),
    },
  ]

  const colonnesReversements: Colonne<Reversement>[] = [
    {
      cle: 'date',
      entete: 'Demandé le',
      principale: true,
      rendu: (element) => (
        <span className="flex flex-col">
          <span className="text-titre">{formaterDate(element.cree_le)}</span>
          <span className="text-petit text-texte-doux">{element.declenchement === 'mensuel' ? 'Reversement du mois' : 'À votre demande'}</span>
        </span>
      ),
    },
    {
      cle: 'numero',
      entete: 'Numéro',
      rendu: (element) => (
        <span className="flex items-center gap-2">
          <LogoReseau code={element.reseau} nom={element.reseau} taille="petit" />
          <span className="font-mono text-petit">{element.numero_masque}</span>
        </span>
      ),
    },
    { cle: 'montant', entete: 'Montant', alignement: 'droite', rendu: (element) => <Montant valeur={element.montant} className="text-titre" /> },
    {
      cle: 'etat',
      entete: 'État',
      rendu: (element) => {
        const etat = presentationReversement(element)
        return <PastilleEtat ton={etat.ton} libelle={etat.libelle} enCours={etat.enCours} />
      },
    },
  ]

  return (
    <div className="flex flex-col gap-8">
      <EnTetePage titre="Mes revenus" description="Le prix de vos applis vous revient en entier : Souk.dev ne prend aucune commission et paie les frais des reversements." />

      {retourConfirmation === 'expiree' ? (
        <Alerte ton="alerte" titre="Ce lien de confirmation n’est plus valable">
          Il a expiré ou a déjà servi. Enregistrez à nouveau votre numéro pour recevoir un nouveau lien.
        </Alerte>
      ) : null}

      <section aria-labelledby="titre-solde" className="relative overflow-hidden rounded-grand bg-nuit p-6 text-sur-couleur shadow-3 tablette:p-8">
        <div aria-hidden="true" className="pointer-events-none absolute -top-24 -right-16 size-72 rounded-pastille bg-primaire opacity-40 blur-3xl" />
        <div className="relative flex flex-col gap-6 tablette:flex-row tablette:items-end tablette:justify-between">
          <div className="flex flex-col gap-2">
            <h2 id="titre-solde" className="m-0 text-petit font-medium text-sur-couleur/80">
              Solde à recevoir
            </h2>
            <p data-testid="solde" className="m-0 chiffres text-affiche leading-none">
              {formaterFcfa(donnees.solde)}
            </p>
            <p className="m-0 flex items-center gap-1.5 text-petit text-sur-couleur/80">
              <CalendarClock aria-hidden="true" className="size-4 shrink-0" />
              Reversement automatique le {formaterDate(donnees.prochain_reversement_le)}
            </p>
          </div>
          <div className="flex flex-col gap-2 tablette:items-end">
            {donnees.peut_demander ? (
              <Bouton taille="lg" variante="secondaire" icone={<ArrowDownToLine aria-hidden="true" className="size-4.5" />} onClick={() => setConfirmation(true)}>
                Recevoir {formaterFcfa(donnees.solde)}
              </Bouton>
            ) : (
              <p className="m-0 max-w-xs rounded-moyen bg-sur-couleur/10 px-3 py-2 text-petit text-sur-couleur">{donnees.raison_blocage}</p>
            )}
          </div>
        </div>
      </section>

      {enCours ? (
        <Alerte ton="info" titre={`Reversement de ${formaterFcfa(enCours.montant)} en cours`}>
          Vers {enCours.numero_masque}. L’argent arrive en général en quelques minutes ; cette page se met à jour toute seule.
        </Alerte>
      ) : null}

      <dl className="m-0 grid gap-4 tablette:grid-cols-3">
        {[
          { titre: 'Encaissé ce mois-ci', valeur: donnees.encaisse_mois },
          { titre: 'Encaissé depuis le début', valeur: donnees.encaisse_total },
          { titre: 'Déjà reçu sur Mobile Money', valeur: donnees.reverse_total },
        ].map((indicateur) => (
          <Carte as="div" key={indicateur.titre} className="flex flex-col gap-1">
            <dt className="text-petit text-texte-doux">{indicateur.titre}</dt>
            <dd className="m-0 chiffres text-titre-2 text-titre">{formaterFcfa(indicateur.valeur)}</dd>
          </Carte>
        ))}
      </dl>

      <div ref={formulaire}>
        <Carte marge="large" className="flex flex-col gap-4">
          <EnTeteCarte
            titre="Où recevoir vos revenus"
            description="Un numéro Mobile Money à votre nom. Chaque changement se confirme par e-mail : une session volée ne peut pas détourner votre argent."
          />
          {coordonnees && !afficherFormulaire ? (
            <div className="flex flex-wrap items-center gap-3 rounded-moyen border border-bordure p-4">
              <LogoReseau code={coordonnees.reseau} nom={nomReseau} />
              <div className="flex min-w-0 flex-col">
                <span className="text-titre">{nomReseau}</span>
                <span className="font-mono text-corps text-texte">{coordonnees.numero_masque}</span>
              </div>
              <Badge ton={coordonnees.confirme ? 'succes' : 'alerte'}>{coordonnees.confirme ? 'Confirmé' : 'À confirmer par e-mail'}</Badge>
              <Bouton variante="contour" taille="sm" className="tablette:ml-auto" onClick={() => setModification(true)}>
                Changer de numéro
              </Bouton>
            </div>
          ) : (
            <FormulaireCoordonnees
              reseaux={reseaux.data ?? []}
              paysParDefaut={coordonnees?.pays ?? compte.data?.pays ?? 'BJ'}
              email={compte.data?.email ?? null}
              onAnnuler={coordonnees ? () => setModification(false) : undefined}
              onEnregistre={() => {
                setModification(false)
                void cache.invalidateQueries({ queryKey: ['revenus'] })
              }}
            />
          )}
        </Carte>
      </div>

      <section aria-labelledby="titre-historique-revenus" className="flex flex-col gap-3">
        <h2 id="titre-historique-revenus" className="m-0 text-titre-3 text-titre">
          Historique
        </h2>
        <Onglets
          libelle="Historique des revenus"
          valeur={vue}
          onChange={setVue}
          onglets={[
            { valeur: 'mouvements', libelle: 'Mouvements', compteur: mouvements.data?.meta.total },
            { valeur: 'reversements', libelle: 'Reversements', compteur: reversements.data?.meta.total },
          ]}
        >
          {vue === 'mouvements' ? (
            mouvements.isPending ? (
              <SqueletteLignes lignes={4} className="mt-4" />
            ) : mouvements.isError || !mouvements.data ? (
              <EtatErreur className="mt-4" onReessayer={() => void mouvements.refetch()} />
            ) : mouvements.data.data.length === 0 ? (
              <EtatVide
                className="mt-4"
                niveau={3}
                illustration="vide-revenus"
                titre="Pas encore de revenus"
                description="Ils apparaissent ici dès qu’un client paie l’une de vos applis."
                action={<LienBouton to="/publier">Publier une appli</LienBouton>}
              />
            ) : (
              <div className="mt-4 flex flex-col gap-4">
                <Tableau legende="Mouvements de votre compte" colonnes={colonnesMouvements} lignes={mouvements.data.data} cleLigne={(mouvement) => mouvement.id} />
                <Pagination page={pageMouvements} dernierePage={mouvements.data.meta.last_page} onChange={setPageMouvements} />
              </div>
            )
          ) : reversements.isPending ? (
            <SqueletteLignes lignes={3} className="mt-4" />
          ) : reversements.isError || !reversements.data ? (
            <EtatErreur className="mt-4" onReessayer={() => void reversements.refetch()} />
          ) : reversements.data.data.length === 0 ? (
            <p className="m-0 mt-4 rounded-moyen border border-dashed border-bordure p-6 text-center text-texte-doux">
              Aucun reversement pour l’instant. Le premier part le 1er du mois, ou dès que vous le demandez au-delà de {formaterFcfa(donnees.seuil_demande)}.
            </p>
          ) : (
            <div className="mt-4 flex flex-col gap-4">
              <Tableau legende="Vos reversements" colonnes={colonnesReversements} lignes={reversements.data.data} cleLigne={(element) => element.id} />
              <Pagination page={pageReversements} dernierePage={reversements.data.meta.last_page} onChange={setPageReversements} />
            </div>
          )}
        </Onglets>
      </section>

      <Modale
        ouverte={confirmation}
        onFermer={() => setConfirmation(false)}
        titre={`Recevoir ${formaterFcfa(donnees.solde)} maintenant ?`}
        description="Les frais du réseau sont pris en charge par Souk.dev : vous recevez le montant entier."
        taille="petite"
        pied={
          <>
            <Bouton variante="contour" onClick={() => setConfirmation(false)}>
              Annuler
            </Bouton>
            <Bouton chargement={envoi} onClick={() => void recevoir()}>
              Confirmer le reversement
            </Bouton>
          </>
        }
      >
        {coordonnees ? (
          <div className="flex items-center gap-3 rounded-moyen border border-bordure p-3">
            <LogoReseau code={coordonnees.reseau} nom={nomReseau} />
            <div className="flex flex-col">
              <span className="text-titre">{nomReseau}</span>
              <span className="font-mono text-petit text-texte">{coordonnees.numero_masque}</span>
            </div>
          </div>
        ) : null}
        {erreur ? (
          <Alerte ton="danger" urgente className="mt-3">
            {erreur}
          </Alerte>
        ) : null}
      </Modale>
    </div>
  )
}
