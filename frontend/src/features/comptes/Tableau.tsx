import type { ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Award, CreditCard, Eye, Plus, Server, Wallet } from 'lucide-react'
import { useTitrePage } from '../../app/titre'
import { lireMoi, messageAction } from '../echange/api'
import { lireTableau, type Compte } from './api'
import { listerCopies } from '../catalogue/api'
import { listerFactures } from '../paiements/api'
import {
  AccesRefuse,
  Avatar,
  Badge,
  Carte,
  EnTetePage,
  EtatErreur,
  EtatVide,
  LienBouton,
  Squelette,
  Tableau as TableauDonnees,
  formaterFcfa,
} from '../../ui'

const niveaux: Record<Compte['reputation']['niveau'], string> = {
  debutant: 'Débutant',
  contributeur: 'Contributeur',
  confirme: 'Confirmé',
  expert: 'Expert',
}

type LigneAppli = NonNullable<Awaited<ReturnType<typeof lireTableau>>>['applis'][number]

/**
 * Tableau de bord du dev : ce que rapportent ses applis (vues, copies, argent encaissé, tiré du
 * grand livre), sa réputation, et l'action principale : publier une appli.
 */
export function Tableau() {
  useTitrePage('Tableau de bord')
  const moi = useQuery({ queryKey: ['moi'], queryFn: lireMoi })
  // Un client (entreprise, particulier) n'a pas d'applis à suivre : il voit ses copies et ses factures.
  const client = moi.data?.profil === 'client'
  const tableau = useQuery({ queryKey: ['tableau'], queryFn: lireTableau, enabled: Boolean(moi.data) && !client })
  const compte = moi.data

  if (compte && client) {
    return <TableauClient nom={compte.nom || compte.email || 'Mon compte'} />
  }

  const entete = (
    <EnTetePage
      titre="Tableau de bord"
      description="Vos applis, leurs visiteurs, leurs copies et ce qu’elles vous rapportent."
      actions={
        compte ? (
          <LienBouton to="/publier" icone={<Plus aria-hidden="true" className="size-4" />}>
            Publier une appli
          </LienBouton>
        ) : undefined
      }
    />
  )

  if (moi.isPending || (compte && tableau.isPending)) {
    return (
      <div role="status" aria-label="Chargement du tableau de bord" className="flex flex-col gap-6">
        {entete}
        <Squelette className="h-24 rounded-moyen" />
        <div className="grid gap-3 tablette:grid-cols-2 bureau:grid-cols-4">
          {[0, 1, 2, 3].map((indice) => (
            <Squelette key={indice} className="h-28 rounded-moyen" />
          ))}
        </div>
        <Squelette className="h-48 rounded-moyen" />
      </div>
    )
  }

  if (moi.isError) {
    return (
      <div className="flex flex-col gap-6">
        {entete}
        <EtatErreur description={messageAction(moi.error, 'Impossible de vérifier la connexion au compte.')} onReessayer={() => void moi.refetch()} />
      </div>
    )
  }

  if (!compte) {
    return (
      <div className="flex flex-col gap-6">
        {entete}
        <AccesRefuse description="Connectez-vous pour voir vos applis et ce qu’elles rapportent." action={<LienBouton to="/connexion">Se connecter</LienBouton>} />
      </div>
    )
  }

  const totaux = tableau.data?.totaux
  const nom = compte.nom || compte.username || 'Mon compte'

  return (
    <div className="flex flex-col gap-6">
      {entete}

      <Carte className="flex flex-col gap-4 tablette:flex-row tablette:items-center">
        <div className="flex min-w-0 flex-1 items-center gap-3">
          <Avatar nom={nom} url={compte.avatar_url} taille="lg" />
          <div className="min-w-0">
            <p className="m-0 text-titre-3 text-titre">{nom}</p>
            <p className="m-0 flex flex-wrap items-center gap-2 text-petit text-texte-doux">
              <span title="Calculé à partir de vos applis publiées, des bugs que vous avez résolus pour d’autres devs, de vos missions notées et de votre activité récente.">
                Souk Score <strong className="chiffres font-semibold text-titre">{compte.souk_score.total}</strong> / 1000
              </span>
              <Badge ton="alerte" icone={<Award aria-hidden="true" className="size-3.5" />}>
                {niveaux[compte.reputation.niveau]} · {compte.reputation.xp.toLocaleString('fr-FR')} XP
              </Badge>
            </p>
          </div>
        </div>
        {compte.username ? (
          <LienBouton to={`/devs/${compte.username}`} variante="contour" className="shrink-0">
            Voir mon profil public
          </LienBouton>
        ) : null}
      </Carte>

      {tableau.isError ? <EtatErreur description={messageAction(tableau.error, 'Le tableau de bord n’a pas pu être chargé.')} onReessayer={() => void tableau.refetch()} /> : null}

      {totaux ? (
        <section aria-label="Totaux" className="grid gap-3 tablette:grid-cols-2 bureau:grid-cols-4">
          <Tuile icone={<Eye aria-hidden="true" className="size-4" />} titre="Vues" valeur={totaux.vues.toLocaleString('fr-FR')} detail="un visiteur compté une fois par jour" />
          <Tuile icone={<Server aria-hidden="true" className="size-4" />} titre="Copies actives" valeur={String(totaux.copies_actives)} detail="en ligne chez des clients" />
          <Tuile icone={<Wallet aria-hidden="true" className="size-4" />} titre="Encaissé ce mois-ci" valeur={formaterFcfa(totaux.revenus_mois)} detail="factures payées, votre part entière" mise />
          <Tuile icone={<Wallet aria-hidden="true" className="size-4" />} titre="Encaissé depuis le début" valeur={formaterFcfa(totaux.revenus_total)} detail="sans aucune commission" />
        </section>
      ) : null}

      {tableau.data ? (
        tableau.data.applis.length === 0 ? (
          <EtatVide
            illustration="vide-store"
            titre="Aucune appli publiée"
            description="Publiez votre première appli : ses vues, ses copies et ses revenus apparaîtront ici."
            action={<LienBouton to="/publier">Publier une appli</LienBouton>}
          />
        ) : (
          <Carte as="section" marge="aucune" aria-labelledby="titre-par-appli" className="overflow-hidden">
            <h2 id="titre-par-appli" className="m-0 px-5 pt-5 pb-3 text-titre-3 text-titre">
              Par appli
            </h2>
            <TableauDonnees<LigneAppli>
              legende="Vues, copies et revenus de chaque appli"
              lignes={tableau.data.applis}
              cleLigne={(appli) => appli.app_id}
              colonnes={[
                {
                  cle: 'appli',
                  entete: 'Appli',
                  principale: true,
                  rendu: (appli) => (
                    <span className="flex flex-col">
                      <span className="font-medium text-titre">{appli.nom}</span>
                      <span className="text-petit text-texte-doux">
                        {appli.prix === 0 ? 'Gratuit' : `${formaterFcfa(appli.prix)}${appli.type_prix === 'mensuel' ? ' / mois' : ' · paiement unique'}`}
                      </span>
                    </span>
                  ),
                },
                { cle: 'vues', entete: 'Vues', alignement: 'droite', rendu: (appli) => <span className="chiffres">{appli.vues.toLocaleString('fr-FR')}</span> },
                { cle: 'copies', entete: 'Copies actives', alignement: 'droite', rendu: (appli) => <span className="chiffres">{appli.copies_actives}</span> },
                { cle: 'deploiements', entete: 'Commandes depuis le début', alignement: 'droite', rendu: (appli) => <span className="chiffres">{appli.deploiements_total}</span> },
                {
                  cle: 'revenus',
                  entete: 'Encaissé ce mois-ci',
                  alignement: 'droite',
                  rendu: (appli) => (
                    <span className="flex flex-col items-end">
                      <span className="chiffres font-medium text-titre">{formaterFcfa(appli.revenu_mois)}</span>
                      <span className="chiffres text-petit text-texte-doux">{formaterFcfa(appli.revenu_total)} au total</span>
                    </span>
                  ),
                },
              ]}
            />
          </Carte>
        )
      ) : null}
    </div>
  )
}

/** Tableau de bord d'un client : ses copies en ligne, ce qu'il doit payer, et où aller ensuite. */
function TableauClient({ nom }: { nom: string }) {
  const copies = useQuery({ queryKey: ['copies'], queryFn: listerCopies })
  const aPayer = useQuery({ queryKey: ['factures', 'a_payer', 1], queryFn: () => listerFactures({ statut: 'a_payer' }) })
  const enLigne = copies.data?.data.filter((copie) => copie.etat === 'en_ligne').length ?? 0
  const total = copies.data?.data.filter((copie) => !['arrete', 'echec'].includes(copie.etat)).length ?? 0
  const du = aPayer.data?.data.reduce((somme, facture) => somme + facture.montant_total, 0) ?? 0
  const factures = aPayer.data?.data.length ?? 0

  return (
    <div className="flex flex-col gap-6">
      <EnTetePage
        titre={`Bonjour ${nom}`}
        description="Vos applis en ligne, vos factures et vos demandes sur mesure, au même endroit."
        actions={<LienBouton to="/store">Parcourir le Store</LienBouton>}
      />

      {copies.isError || aPayer.isError ? (
        <EtatErreur
          description="Vos copies ou vos factures n’ont pas pu être chargées."
          onReessayer={() => {
            void copies.refetch()
            void aPayer.refetch()
          }}
        />
      ) : null}

      {copies.isPending || aPayer.isPending ? (
        <div role="status" aria-label="Chargement du tableau de bord" className="grid gap-3 tablette:grid-cols-3">
          {[0, 1, 2].map((indice) => (
            <Squelette key={indice} className="h-28 rounded-moyen" />
          ))}
        </div>
      ) : null}

      {copies.data && aPayer.data ? (
        <>
          <section aria-label="Résumé" className="grid gap-3 tablette:grid-cols-3">
            <Tuile icone={<Server aria-hidden="true" className="size-4" />} titre="Copies en ligne" valeur={String(enLigne)} detail={total > enLigne ? `${total - enLigne} autre${total - enLigne > 1 ? 's' : ''} en préparation ou suspendue${total - enLigne > 1 ? 's' : ''}` : 'vos applis qui tournent pour vous'} />
            <Tuile icone={<CreditCard aria-hidden="true" className="size-4" />} titre="À payer" valeur={formaterFcfa(du)} detail={factures === 0 ? 'vous êtes à jour' : `${factures} facture${factures > 1 ? 's' : ''} en attente`} mise={factures > 0} />
            <Tuile icone={<Wallet aria-hidden="true" className="size-4" />} titre="Moyen de paiement" valeur="Mobile Money" detail="un rappel 3 jours avant chaque échéance" />
          </section>

          {total === 0 ? (
            <EtatVide
              illustration="vide-copies"
              titre="Vous n’avez pas encore d’appli en ligne"
              description="Choisissez une appli dans le Store, essayez sa démo, puis commandez votre copie : elle tourne pour vous, sur sa propre adresse."
              action={<LienBouton to="/store">Trouver mon appli</LienBouton>}
            />
          ) : (
            <div className="flex flex-wrap gap-2">
              {factures > 0 ? <LienBouton to="/paiements">Payer mes factures</LienBouton> : null}
              <LienBouton to="/copies" variante={factures > 0 ? 'contour' : 'primaire'}>
                Voir mes copies
              </LienBouton>
              <LienBouton to="/missions" variante="contour">
                Demandes sur mesure
              </LienBouton>
            </div>
          )}
        </>
      ) : null}
    </div>
  )
}

function Tuile({ icone, titre, valeur, detail, mise = false }: { icone: ReactNode; titre: string; valeur: string; detail: string; mise?: boolean }) {
  return (
    <div className={mise ? 'flex flex-col gap-1 rounded-moyen border border-primaire bg-surface-accent p-4' : 'flex flex-col gap-1 rounded-moyen border border-bordure bg-surface p-4'}>
      <p className="m-0 flex items-center gap-2 text-petit font-medium text-texte">
        <span className="text-sur-primaire-doux">{icone}</span>
        {titre}
      </p>
      <p className="chiffres m-0 text-titre-1 text-titre">{valeur}</p>
      <p className="m-0 text-petit text-texte-doux">{detail}</p>
    </div>
  )
}
