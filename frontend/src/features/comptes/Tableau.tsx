import type { ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Award, Eye, Plus, Server, Wallet } from 'lucide-react'
import { useTitrePage } from '../../app/titre'
import { lireMoi, messageAction } from '../echange/api'
import { lireTableau, type Compte } from './api'
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
  const tableau = useQuery({ queryKey: ['tableau'], queryFn: lireTableau, enabled: Boolean(moi.data) })
  const compte = moi.data

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
              <span>
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
            description="Publiez votre première appli : ses vues, ses copies et ses revenus apparaîtront ici."
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
                { cle: 'deploiements', entete: 'Déploiements', alignement: 'droite', rendu: (appli) => <span className="chiffres">{appli.deploiements_total}</span> },
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
