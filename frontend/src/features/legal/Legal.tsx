import type { ReactNode } from 'react'
import { useTitrePage } from '../../app/titre'
import { Alerte, EnTetePage, FilAriane } from '../../ui'

/**
 * Pages légales de Souk.dev : des brouillons rédigés par l'équipe, à faire valider par un
 * juriste avant le lancement. Le bandeau le dit sur chaque page, et le restera tant que
 * la validation n'est pas faite.
 */

function PageLegale({ titre, description, children }: { titre: string; description: string; children: ReactNode }) {
  useTitrePage(titre)
  return (
    <div className="mx-auto flex w-full max-w-prose flex-col gap-6 px-4 py-8 tablette:px-6">
      <FilAriane miettes={[{ libelle: 'Accueil', vers: '/' }, { libelle: titre }]} />
      <EnTetePage titre={titre} description={description} />
      <Alerte ton="alerte" titre="Brouillon à faire valider par un juriste">
        Ce texte est un brouillon rédigé par l’équipe de Souk.dev. Il n’a pas de valeur contractuelle tant qu’un juriste ne l’a pas relu et validé.
      </Alerte>
      <div className="flex flex-col gap-5 text-corps leading-relaxed">{children}</div>
    </div>
  )
}

function Titre({ children }: { children: ReactNode }) {
  return <h2 className="m-0 mt-2 text-titre-3">{children}</h2>
}

function Paragraphe({ children }: { children: ReactNode }) {
  return <p className="m-0">{children}</p>
}

function Liste({ elements }: { elements: string[] }) {
  return (
    <ul className="m-0 flex list-disc flex-col gap-1.5 pl-6">
      {elements.map((element) => (
        <li key={element}>{element}</li>
      ))}
    </ul>
  )
}

export function Cgu() {
  return (
    <PageLegale titre="Conditions d’utilisation" description="Les règles du service Souk.dev : comptes, publications, échange et modération.">
      <Titre>1. Le service</Titre>
      <Paragraphe>
        Souk.dev est le marché des développeurs africains : ils y publient leurs applications, que chacun peut déployer en un clic (une copie
        isolée par client), et un espace d’échange leur ouvre entraide, missions et revenus. Poster un problème, lire un dépôt et proposer un
        correctif sont gratuits et ne déclenchent aucun déploiement.
      </Paragraphe>
      <Titre>2. Comptes</Titre>
      <Paragraphe>
        L’entrée « Développeur » se fait par GitHub, l’entrée « Entreprise, agence, particulier » par lien magique envoyé par e-mail. Un seul
        compte par personne : le choix d’entrée oriente la connexion et la page d’arrivée, sans étiquette définitive. GitHub n’est obligatoire que
        pour publier une application ou proposer un correctif. Seuls les dépôts publics sont acceptés.
      </Paragraphe>
      <Titre>3. Contenus publiés</Titre>
      <Paragraphe>
        En publiant (application, discussion, message, correctif, événement), le membre garantit qu’il a le droit de le faire et que le contenu
        respecte la loi et la politique d’abus. Les applications sont analysées avant publication (secrets, dépendances) : un secret bloque la
        publication, des failles connues donnent un badge orange avec le rapport.
      </Paragraphe>
      <Titre>4. Modération</Titre>
      <Paragraphe>
        Tout membre peut signaler un contenu. L’équipe examine, puis peut supprimer un contenu, suspendre une copie ou suspendre un compte, avec un
        motif visible par le compte concerné. Toute décision se conteste en répondant à l’e-mail reçu. Voir la politique d’abus.
      </Paragraphe>
      <Titre>5. Responsabilités</Titre>
      <Paragraphe>
        Souk.dev héberge les copies des clients et fournit la plateforme en l’état, sans garantie d’absence d’interruption. L’auteur d’une
        application reste responsable de son code et de ses données ; le client d’une copie reste responsable de l’usage qu’il en fait. Les missions
        se règlent entre le client et le développeur, hors de la plateforme.
      </Paragraphe>
    </PageLegale>
  )
}

export function Cgv() {
  return (
    <PageLegale titre="Conditions de vente" description="Ce que paie le client, tous les mois : l’hébergement de sa copie et le prix de l’appli.">
      <Titre>1. Prix</Titre>
      <Paragraphe>Le client paie chaque mois deux montants, en francs CFA :</Paragraphe>
      <Liste
        elements={[
          'l’hébergement de sa copie, fixé par Souk.dev selon sa formule : petite 3 500 F, moyenne 7 000 F, grande 13 500 F par mois ;',
          'le prix de l’appli, fixé par son auteur : chaque mois si le prix est mensuel, une seule fois s’il est unique.',
        ]}
      />
      <Paragraphe>
        Le prix de l’appli va entièrement à son auteur, sans aucune commission : Souk.dev ne gagne que sur l’hébergement. Les frais de paiement
        s’ajoutent au total payé par le client ; les frais de reversement aux développeurs sont pris sur la marge de Souk.dev.
      </Paragraphe>
      <Titre>2. Facturation</Titre>
      <Paragraphe>
        Une facture par copie et par période, à date anniversaire du premier paiement. Rappel par e-mail 3 jours avant l’échéance, avec un lien de
        paiement. Le paiement se fait en Mobile Money ou par carte, via le prestataire SasPay.
      </Paragraphe>
      <Titre>3. Impayé : grâce, suspension, arrêt</Titre>
      <Paragraphe>
        Sans paiement à l’échéance : 7 jours de grâce, puis suspension (conteneurs arrêtés, page neutre sur le sous-domaine, données gardées).
        Réactivation dès le paiement, pour un mois à partir du paiement. Après 30 jours de suspension, arrêt définitif et suppression des données,
        avec un e-mail d’avertissement 7 jours avant.
      </Paragraphe>
      <Titre>4. Reversements aux développeurs</Titre>
      <Paragraphe>
        Le solde d’un développeur (ses parts encaissées moins ses reversements) est versé chaque mois, ou sur demande au-delà de 5 000 F, sur le
        numéro Mobile Money saisi et confirmé sur son profil.
      </Paragraphe>
      <Titre>5. Remboursements</Titre>
      <Paragraphe>
        Le prestataire de paiement n’offrant pas de remboursement par API, tout remboursement se fait depuis son tableau de bord, puis est saisi
        comme avoir dans Souk.dev. En cas de paiement en double, le trop-perçu est remboursé et soldé dans le grand livre.
      </Paragraphe>
    </PageLegale>
  )
}

export function Confidentialite() {
  return (
    <PageLegale titre="Confidentialité" description="Les données personnelles collectées, leur usage, leur conservation et vos droits (APDP Bénin).">
      <Titre>1. Données collectées</Titre>
      <Liste
        elements={[
          'compte : nom, e-mail, identifiant GitHub et avatar quand GitHub est lié, pays et bio déclarés ;',
          'paiement : factures, références de transaction ; jamais les codes Mobile Money ni les numéros de carte, qui restent chez le prestataire ;',
          'reversement : pays, réseau et numéro Mobile Money du développeur, chiffrés ;',
          'technique : journaux de connexion et d’erreur, sans clé ni mot de passe, numéros tronqués.',
        ]}
      />
      <Titre>2. Usage</Titre>
      <Paragraphe>
        Ces données servent à fournir le service (comptes, déploiements, facturation, reversements, notifications), à le sécuriser (limites de débit,
        modération) et à l’améliorer (mesures d’audience internes). Elles ne sont ni vendues ni louées.
      </Paragraphe>
      <Titre>3. Sous-traitants</Titre>
      <Paragraphe>
        Hébergement des serveurs (Datacloud), paiement (SasPay), e-mails (Resend), analyse IA sur consentement explicite (DeepSeek : le bug et son
        code partent chez le fournisseur, secrets masqués). Chacun ne reçoit que ce dont il a besoin.
      </Paragraphe>
      <Titre>4. Conservation</Titre>
      <Paragraphe>
        Compte actif : le temps du service. Compte supprimé sur demande : effacement sous 30 jours, sauf les écritures comptables (10 ans) et les
        journaux de sécurité (1 an). Copies arrêtées : données supprimées ; copies suspendues : données gardées jusqu’à l’arrêt définitif.
      </Paragraphe>
      <Titre>5. Vos droits (APDP Bénin)</Titre>
      <Paragraphe>
        Accès, correction, suppression, opposition : écrivez à l’équipe depuis votre adresse de compte. Réponse sous 30 jours. En cas de désaccord,
        l’Autorité de protection des données personnelles du Bénin (APDP) peut être saisie.
      </Paragraphe>
    </PageLegale>
  )
}

export function PolitiqueAbus() {
  return (
    <PageLegale titre="Politique d’abus" description="Ce qui est interdit sur Souk.dev, et ce qui arrive en cas d’abus : examen, suspension, appel.">
      <Titre>1. Interdits</Titre>
      <Liste
        elements={[
          'minage de cryptomonnaie : une copie dont le processeur reste saturé (90 % de sa formule) pendant une heure est suspendue automatiquement ;',
          'hameçonnage et fraude : une copie signalée pour hameçonnage est suspendue aussitôt, le temps que l’équipe examine (elle la rétablit si le signalement n’est pas fondé) ;',
          'spam, harcèlement, insultes, contenus illégaux, atteintes aux droits d’autrui.',
        ]}
      />
      <Titre>2. Signalement</Titre>
      <Paragraphe>
        Le bouton « Signaler » d’une discussion ou d’une appli envoie le motif et des faits vérifiables à l’équipe. L’hameçonnage, le minage et les
        contenus illégaux la préviennent aussitôt ; les autres motifs attendent dans la file de modération.
      </Paragraphe>
      <Titre>3. Examen et sanctions</Titre>
      <Paragraphe>
        Hors minage et hameçonnage, suspendus d’office pour protéger les visiteurs, l’équipe examine chaque signalement avant d’agir : suppression du contenu, suspension de la copie (conteneurs arrêtés, page neutre,
        données gardées) ou suspension du compte (plus de connexion ni d’API), toujours avec un motif. Un signalement non fondé est rejeté.
      </Paragraphe>
      <Titre>4. Contestation</Titre>
      <Paragraphe>
        Chaque suspension part avec un e-mail qui donne le motif : il suffit d’y répondre pour contester, l’équipe relit. Les données restent
        gardées pendant l’examen ; en cas d’erreur, la copie ou le compte est rétabli.
      </Paragraphe>
    </PageLegale>
  )
}
