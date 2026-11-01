import { useState, type FormEvent } from 'react'
import { Link, useParams } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { ExternalLink, Lock } from 'lucide-react'
import { useTitrePage } from '../../app/titre'
import { useCompte } from '../../app/compte'
import { fcfa, libelleCategorie, lireAppli, listerDiscussionsAppli, proposerMissionAppli } from './api'
import { BadgeSecurite } from './BadgeSecurite'
import { messageAction } from '../echange/api'
import { nomPays } from '../echange/texte'
import { formules } from '../paiements/formules'
import { RapportSecurite } from '../securite/RapportSecurite'
import { AvisClients } from './AvisClients'
import { VersionsAppli } from './VersionsAppli'
import { Signaler } from '../admin/Signaler'
import { ModaleConnexion } from '../../app/ModaleConnexion'
import {
  Alerte,
  Avatar,
  Badge,
  Bouton,
  Carte,
  Champ,
  EnTeteCarte,
  EtatErreur,
  Etoiles,
  FilAriane,
  LienBouton,
  Modale,
  Saisie,
  SaisieMontant,
  Squelette,
  ZoneTexte,
  classesBouton,
  cx,
  formaterNote,
  useToast,
} from '../../ui'

/**
 * Fiche d'une appli : décider de la commander. Le prix détaillé et « Déployer ma copie » sont
 * dans un panneau fixe sur bureau, juste sous le titre sur mobile ; captures, README, sécurité,
 * avis, versions et discussions suivent.
 */
export function Fiche() {
  const compte = useCompte()
  const { id = '' } = useParams()
  // Clé numérique, comme la commande et le rapport de sécurité : une invalidation les touche tous.
  const appli = useQuery({
    queryKey: ['appli', Number(id)],
    queryFn: () => lireAppli(Number(id)),
    enabled: Number.isFinite(Number(id)),
    // Analyse de sécurité en cours : la fiche se relit jusqu'au résultat.
    refetchInterval: (requete) => (requete.state.data?.securite.statut === 'en_cours' ? 10_000 : false),
  })
  const discussions = useQuery({
    queryKey: ['discussions-appli', id],
    queryFn: () => listerDiscussionsAppli(Number(id)),
    enabled: appli.isSuccess,
  })
  const [ecran, setEcran] = useState(0)
  const [mission, setMission] = useState(false)
  // Chaque ouverture repart d'un formulaire vierge (une erreur passée ne revient pas).
  const [ouvertures, setOuvertures] = useState(0)
  const [connexion, setConnexion] = useState(false)
  useTitrePage(appli.data?.nom ?? 'Fiche')

  const conteneur = 'mx-auto flex w-full max-w-site flex-col gap-6 px-4 py-8 tablette:px-6 bureau:px-8'

  // Le squelette tient toute la hauteur de l'écran : le pied de page ne remonte pas puis ne saute pas.
  if (appli.isPending) {
    return (
      <div role="status" aria-label="Chargement de la fiche" className={cx(conteneur, 'min-h-dvh')}>
        <Squelette className="h-4 w-40" />
        <Squelette className="h-10 w-2/3" />
        <div className="grid gap-6 bureau:grid-cols-[minmax(0,1fr)_22rem]">
          <Squelette className="aspect-video w-full rounded-moyen" />
          <Squelette className="h-80 rounded-moyen" />
        </div>
      </div>
    )
  }
  if (appli.isError || !appli.data) {
    return (
      <div className={conteneur}>
        <EtatErreur
          titre="Fiche indisponible"
          description={messageAction(appli.error, 'Cette appli est introuvable, ou le chargement a échoué.')}
          onReessayer={() => void appli.refetch()}
        />
        <LienBouton to="/store" variante="fantome" className="self-center">
          Retour au Store
        </LienBouton>
      </div>
    )
  }

  const fiche = appli.data
  const formule = formules[fiche.taille]
  const prix = fiche.prix === 0 ? 'Gratuit' : fcfa(fiche.prix)
  const mensuel = fiche.prix > 0 && fiche.type_prix === 'mensuel'
  const captures = fiche.captures
  const index = captures.length === 0 ? 0 : Math.min(ecran, captures.length - 1)
  const auteur = fiche.auteur.nom || fiche.auteur.username || 'un dev'
  const depotCourt = fiche.depot_url.split('/').pop()
  const estAuteur = compte.data?.id === fiche.auteur.id

  return (
    <div className={conteneur}>
      <FilAriane miettes={[{ libelle: 'Store', vers: '/store' }, { libelle: fiche.nom }]} />

      <div className="flex flex-col gap-8 bureau:grid bureau:grid-cols-[minmax(0,1fr)_22rem] bureau:items-start bureau:gap-x-10">
        <header className="flex flex-col gap-4 bureau:col-start-1 bureau:row-start-1">
          <div className="flex flex-wrap gap-1.5">
            <Badge ton="marque">{libelleCategorie(fiche.categorie)}</Badge>
            <BadgeSecurite statut={fiche.securite.statut} />
            {fiche.stack.map((techno) => (
              <Badge key={techno}>{techno}</Badge>
            ))}
          </div>
          <h1 className="m-0 text-titre-1 text-titre tablette:text-affiche">{fiche.nom}</h1>
          <p className="m-0 max-w-prose text-corps-l text-texte">{fiche.description}</p>
          <div className="flex flex-wrap items-center gap-x-5 gap-y-2 text-petit text-texte-doux">
            <span className="flex items-center gap-2">
              <Avatar nom={auteur} taille="sm" />
              <span>
                par {fiche.auteur.username ? <Link to={`/devs/${fiche.auteur.username}`} className="font-medium underline underline-offset-2">{auteur}</Link> : auteur}
                {fiche.auteur.pays ? ` · ${nomPays(fiche.auteur.pays)}` : ''}
              </span>
            </span>
            {fiche.note_moyenne !== null ? (
              <a href="#titre-avis" className="inline-flex min-h-11 items-center gap-2 text-titre no-underline tablette:min-h-0">
                <Etoiles note={fiche.note_moyenne} taille="sm" />
                <span>
                  <span aria-hidden="true" className="font-medium">{formaterNote(fiche.note_moyenne)} · </span>
                  {fiche.nb_avis} avis
                </span>
              </a>
            ) : (
              <span>Pas encore d’avis</span>
            )}
          </div>
        </header>

        <aside aria-label="Commander cette appli" className="bureau:sticky bureau:top-24 bureau:col-start-2 bureau:row-span-2 bureau:row-start-1">
          <Carte marge="large" className="flex flex-col gap-5">
            <div className="flex flex-col gap-1">
              <p className="m-0 flex items-baseline gap-1.5">
                <span className="chiffres text-titre-1 text-titre">{prix}</span>
                {mensuel ? <span className="text-texte-doux">/ mois</span> : null}
              </p>
              <p className="m-0 text-petit text-texte-doux">{fiche.type_prix === 'unique' && fiche.prix > 0 ? 'Payé une fois à l’auteur, plus l’hébergement chaque mois.' : 'Plus l’hébergement de votre copie, chaque mois.'}</p>
            </div>
            <dl className="m-0 flex flex-col gap-2 border-y border-bordure py-4 text-petit">
              <div className="flex justify-between gap-3">
                <dt className="text-texte">Appli, versée à {auteur}</dt>
                <dd className="chiffres m-0 font-medium text-titre">{prix}</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-texte">Hébergement {formule.nom} ({formule.memoire})</dt>
                <dd className="chiffres m-0 font-medium text-titre">{fcfa(formule.prix)} / mois</dd>
              </div>
              <div className="flex justify-between gap-3">
                <dt className="text-texte">Commission Souk.dev</dt>
                <dd className="chiffres m-0 font-medium text-succes">0 F</dd>
              </div>
              <div className="flex justify-between gap-3 border-t border-bordure pt-2 text-corps font-semibold text-titre">
                <dt>{fiche.type_prix === 'unique' && fiche.prix > 0 ? 'Premier mois' : 'Total par mois'}</dt>
                <dd className="chiffres m-0">{fcfa(fiche.prix + formule.prix)}</dd>
              </div>
            </dl>
            <div className="flex flex-col gap-2">
              <LienBouton to={`/store/${fiche.id}/deployer`} pleineLargeur taille="lg">
                Déployer ma copie
              </LienBouton>
              {fiche.demo_url ? (
                <a href={fiche.demo_url} target="_blank" rel="noreferrer" className={classesBouton('contour', 'md', true)}>
                  Essayer la démo
                  <ExternalLink aria-hidden="true" className="size-4" />
                  <span className="pour-lecteur"> (nouvel onglet)</span>
                </a>
              ) : null}
              <Bouton
                variante="fantome"
                pleineLargeur
                onClick={() => {
                  if (!compte.data) {
                    setConnexion(true)
                    return
                  }
                  setOuvertures((nombre) => nombre + 1)
                  setMission(true)
                }}
              >
                Demander une version sur mesure
              </Bouton>
            </div>
            <p className="m-0 flex gap-2 text-petit text-texte-doux">
              <Lock aria-hidden="true" className="mt-0.5 size-4 shrink-0" />
              Votre copie tourne à part, avec ses propres données. Paiement par Mobile Money, en FCFA.
            </p>
            {fiche.variables_client.length > 0 ? (
              <Alerte titre="Au déploiement, vous remplirez :">
                <ul className="m-0 list-disc pl-4">
                  {fiche.variables_client.map((variable) => (
                    <li key={variable.nom}>{variable.libelle}</li>
                  ))}
                </ul>
              </Alerte>
            ) : null}
            <div className="flex flex-wrap items-center justify-between gap-2 border-t border-bordure pt-4 text-petit">
              <a href={fiche.depot_url} target="_blank" rel="noreferrer" className="inline-flex min-h-11 items-center gap-1.5 tablette:min-h-0">
                Dépôt GitHub
                <ExternalLink aria-hidden="true" className="size-3.5" />
                <span className="pour-lecteur"> (nouvel onglet)</span>
              </a>
              {compte.data && !estAuteur ? <Signaler cible="appli" cibleId={fiche.id} etiquette="cette appli" /> : null}
            </div>
          </Carte>
        </aside>

        <div className="flex min-w-0 flex-col gap-8 bureau:col-start-1 bureau:row-start-2">
          <section aria-label="Captures de l’application" className="flex flex-col gap-3">
            {captures.length > 0 ? (
              <>
                <div className="overflow-hidden rounded-moyen border border-bordure bg-surface-douce shadow-1">
                  <img src={captures[index].url} alt={`Capture ${index + 1} de ${fiche.nom}`} width={1280} height={720} className="aspect-video w-full object-contain" />
                </div>
                {captures.length > 1 ? (
                  <div className="flex gap-2 overflow-x-auto pb-1">
                    {captures.map((capture, numero) => (
                      <button
                        key={capture.url}
                        type="button"
                        aria-current={index === numero ? 'true' : undefined}
                        aria-label={`Capture ${numero + 1} sur ${captures.length}`}
                        onClick={() => setEcran(numero)}
                        className={cx('w-28 shrink-0 overflow-hidden rounded-petit border-2', index === numero ? 'border-primaire' : 'border-bordure hover:border-bordure-champ')}
                      >
                        <img src={capture.url} alt="" className="aspect-video w-full object-cover" />
                      </button>
                    ))}
                  </div>
                ) : null}
              </>
            ) : (
              <div className="grid aspect-video place-items-center rounded-moyen border border-dashed border-bordure bg-surface-accent p-6 text-center">
                <p className="m-0 flex flex-col gap-1">
                  <strong className="text-titre-3 text-titre">Aucune capture publiée</strong>
                  <span className="text-petit text-texte-doux">L’auteur n’a pas fourni d’image pour cette application.</span>
                </p>
              </div>
            )}
          </section>

          <Carte as="section" aria-labelledby="titre-readme" className="flex flex-col gap-4">
            <EnTeteCarte titre={<span id="titre-readme">À propos</span>} description="Affiché depuis le README du dépôt." />
            <p className="m-0 whitespace-pre-wrap text-corps text-texte">{fiche.readme || fiche.description}</p>
            <div className="flex flex-col gap-2">
              <h3 className="m-0 text-corps font-semibold text-titre">Lancer en local</h3>
              <pre className="m-0 overflow-x-auto rounded-moyen border border-bordure bg-surface-douce p-3 font-mono text-petit text-titre">{`git clone ${fiche.depot_url}\ncd ${depotCourt}`}</pre>
            </div>
          </Carte>

          <RapportSecurite appId={fiche.id} securite={fiche.securite} estAuteur={estAuteur} />

          <AvisClients appId={fiche.id} noteMoyenne={fiche.note_moyenne} nbAvis={fiche.nb_avis} />

          <VersionsAppli appId={fiche.id} estAuteur={estAuteur} />

          <Carte as="section" aria-labelledby="titre-discussions" className="flex flex-col gap-4">
            <EnTeteCarte
              titre={
                <span id="titre-discussions">
                  Discussions{discussions.isSuccess ? <span className="ml-2 text-corps font-normal text-texte-doux">{discussions.data.data.length}</span> : null}
                </span>
              }
              description="Ouvertes à tous : clients et devs. Un dev peut y proposer un correctif."
            />
            {discussions.isPending ? <Squelette className="h-12 w-full" /> : null}
            {discussions.isError ? (
              <p className="m-0 flex flex-wrap items-center gap-2 text-corps text-texte-doux">
                Les discussions n’ont pas pu être chargées.
                <Bouton variante="fantome" taille="sm" onClick={() => void discussions.refetch()}>
                  Réessayer
                </Bouton>
              </p>
            ) : null}
            {discussions.isSuccess && discussions.data.data.length === 0 ? <p className="m-0 text-corps text-texte-doux">Aucune discussion sur cette appli pour le moment.</p> : null}
            {discussions.isSuccess && discussions.data.data.length > 0 ? (
              <ul className="m-0 flex list-none flex-col p-0">
                {discussions.data.data.map((discussion) => (
                  <li key={discussion.id} className="flex flex-col gap-1 border-t border-bordure py-3 first:border-t-0 first:pt-0">
                    <Link to={`/echange/${discussion.id}`} className="font-medium text-titre no-underline hover:underline">
                      {discussion.titre}
                    </Link>
                    <span className="flex flex-wrap items-center gap-2.5 text-petit text-texte-doux">
                      <Badge ton={discussion.resolue ? 'succes' : 'marque'}>{discussion.resolue ? 'Résolue' : 'Ouverte'}</Badge>
                      <span>{discussion.auteur.nom || discussion.auteur.username}</span>
                      <span>
                        {discussion.nb_messages} message{discussion.nb_messages > 1 ? 's' : ''}
                      </span>
                    </span>
                  </li>
                ))}
              </ul>
            ) : null}
            <LienBouton to="/echange" variante="secondaire" className="self-start">
              Poser une question sur cette appli
            </LienBouton>
          </Carte>
        </div>
      </div>
      <DemandeSurMesure key={ouvertures} id={fiche.id} ouverte={mission} onFermer={() => setMission(false)} />
      <ModaleConnexion ouverte={connexion} onFermer={() => setConnexion(false)} action="demander une version sur mesure" />
    </div>
  )
}

/** Demande de version sur mesure à l'auteur : une mission privée, suivie dans « Missions ». */
function DemandeSurMesure({ id, ouverte, onFermer }: { id: number; ouverte: boolean; onFermer: () => void }) {
  const toast = useToast()
  const [erreur, setErreur] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)

  async function envoyer(evenement: FormEvent<HTMLFormElement>) {
    evenement.preventDefault()
    const donnees = new FormData(evenement.currentTarget)
    const budget = String(donnees.get('budget') ?? '').replace(/\s/g, '')
    setEnvoi(true)
    setErreur(null)
    try {
      await proposerMissionAppli(id, {
        message: String(donnees.get('message') ?? ''),
        budget: budget ? Number(budget) : undefined,
        delai: String(donnees.get('delai') ?? '') || undefined,
      })
      toast.succes('Demande envoyée : elle apparaît dans Missions.')
      onFermer()
    } catch (erreurHttp) {
      setErreur(messageAction(erreurHttp, 'La demande n’a pas pu partir. Réessayez dans un moment.'))
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Modale
      ouverte={ouverte}
      onFermer={onFermer}
      titre="Demander une version sur mesure"
      description="L’auteur reçoit votre besoin en privé et vous répond dans Missions."
      pied={
        <>
          <Bouton variante="contour" onClick={onFermer}>
            Annuler
          </Bouton>
          <Bouton type="submit" form="demande-sur-mesure" chargement={envoi}>
            Envoyer la demande
          </Bouton>
        </>
      }
    >
      <form id="demande-sur-mesure" onSubmit={(evenement) => void envoyer(evenement)} className="flex flex-col gap-4">
        <Champ libelle="Votre besoin" aide="Ce que l’appli doit faire en plus, ou autrement.">
          {(liaison) => <ZoneTexte {...liaison} name="message" required rows={4} data-autofocus />}
        </Champ>
        <div className="grid gap-4 tablette:grid-cols-2">
          <Champ libelle="Budget" facultatif>
            {(liaison) => <SaisieMontant {...liaison} name="budget" />}
          </Champ>
          <Champ libelle="Délai souhaité" facultatif>
            {(liaison) => <Saisie {...liaison} name="delai" type="date" />}
          </Champ>
        </div>
        {erreur ? <Alerte ton="danger">{erreur}</Alerte> : null}
      </form>
    </Modale>
  )
}
