import { useState, type FormEvent } from 'react'
import { useParams } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { Award, BadgeCheck, ExternalLink, MapPin, MessagesSquare, Send, Star } from 'lucide-react'
import { useSelonCompte } from '../../app/compte'
import { useTitrePage } from '../../app/titre'
import { lireMoi, messageAction } from '../echange/api'
import { nomPays } from '../echange/texte'
import { CarteAppli } from '../catalogue/CarteAppli'
import { ModaleConnexion } from '../../app/ModaleConnexion'
import { InviterDev } from '../messages/InviterDev'
import { lireProfil, proposerMission, type ProfilDev } from './api'
import {
  Alerte,
  Avatar,
  Badge,
  Bouton,
  Carte,
  Champ,
  EnTeteCarte,
  EtatErreur,
  LienBouton,
  Modale,
  Saisie,
  SaisieMontant,
  Squelette,
  ZoneTexte,
  classesBouton,
  cx,
  useToast,
} from '../../ui'

const roles: Record<ProfilDev['specialites'][number], string> = {
  frontend: 'Frontend',
  backend: 'Backend',
  mobile: 'Mobile',
  devops: 'DevOps',
  securite: 'Sécurité',
  data: 'Data',
}

const niveaux: Record<ProfilDev['reputation']['niveau'], string> = {
  debutant: 'Débutant',
  contributeur: 'Contributeur',
  confirme: 'Confirmé',
  expert: 'Expert',
}

const partsScore = [
  { cle: 'entraide', nom: 'Entraide', max: 250 },
  { cle: 'projets', nom: 'Projets', max: 200 },
  { cle: 'clients', nom: 'Clients', max: 250 },
  { cle: 'securite', nom: 'Sécurité', max: 150 },
  { cle: 'activite', nom: 'Activité', max: 150 },
] as const

const badges = [
  { id: 'premier_correctif', nom: 'Premier correctif', regle: '1 correctif accepté' },
  { id: 'chasseur_de_bugs', nom: 'Chasseur de bugs', regle: '10 bugs résolus' },
  { id: 'publie', nom: 'Publié', regle: '1 appli publiée' },
  { id: 'fiable', nom: 'Fiable', regle: 'Note ≥ 4,5' },
  { id: 'securise', nom: 'Sécurisé', regle: '1 appli vérifiée' },
  { id: 'organisateur', nom: 'Organisateur', regle: '1 événement passé' },
] as const

const conteneur = 'mx-auto flex w-full max-w-site flex-col gap-6 px-4 py-8 tablette:px-6 bureau:px-8'

/**
 * Profil public d'un dev : qui il est, ce qu'il sait faire, la preuve (Souk Score, réputation,
 * applis, dépôts), et une seule action pour un visiteur : lui proposer une mission.
 */
export function Profil() {
  const { username = '' } = useParams()
  const profil = useQuery({
    queryKey: ['profil', username],
    queryFn: () => lireProfil(username),
    enabled: username.length > 0,
  })
  const moi = useQuery({ queryKey: ['moi'], queryFn: lireMoi })
  const selonCompte = useSelonCompte()
  const [mission, setMission] = useState(false)
  const [invitation, setInvitation] = useState(false)
  const [ouvertures, setOuvertures] = useState(0)
  const [connexion, setConnexion] = useState(false)
  useTitrePage(profil.data?.nom || profil.data?.username || 'Profil', { description: profil.data?.bio, image: profil.data?.avatar_url })

  if (profil.isPending) {
    return (
      <div role="status" aria-label="Chargement du profil" className={conteneur}>
        <Squelette className="h-56 rounded-moyen" />
        <Squelette className="h-24 rounded-moyen" />
        <Squelette className="h-64 rounded-moyen" />
      </div>
    )
  }

  if (profil.isError || !profil.data) {
    return (
      <div className={conteneur}>
        <EtatErreur titre="Profil indisponible" description="Ce profil est introuvable, ou le chargement a échoué." onReessayer={() => void profil.refetch()} />
      </div>
    )
  }

  const dev = profil.data
  const nom = dev.nom || dev.username || 'Dev'
  const etoiles = dev.depots.reduce((somme, depot) => somme + depot.etoiles, 0)
  const note = dev.stats.note_moyenne
  const moiMeme = moi.data?.id === dev.id

  return (
    <div className={conteneur}>
      <Carte as="section" marge="large" aria-labelledby="titre-profil" className="grid gap-6 bureau:grid-cols-[minmax(0,1fr)_16rem] bureau:items-start">
        <div className="flex flex-col gap-4 tablette:flex-row tablette:items-start tablette:gap-6">
          <Avatar nom={nom} url={dev.avatar_url} taille="xl" />
          <div className="flex min-w-0 flex-col gap-3">
            <div className="flex flex-col gap-1">
              <h1 id="titre-profil" className="m-0 text-titre-1 text-titre">
                {nom}
              </h1>
              <p className="m-0 text-corps-l text-texte">
                Dev {dev.specialites.length > 0 ? dev.specialites.map((item) => roles[item].toLowerCase()).join(' & ') : 'sur Souk.dev'}
                {dev.username ? <span className="text-texte-doux"> · @{dev.username}</span> : null}
              </p>
            </div>
            <p className="m-0 max-w-prose text-corps text-texte">{dev.bio || 'Ce dev n’a pas encore écrit de présentation.'}</p>
            <div className="flex flex-wrap items-center gap-2 text-petit text-texte-doux">
              <span className="inline-flex items-center gap-1.5">
                <MapPin aria-hidden="true" className="size-4" />
                {dev.pays ? nomPays(dev.pays) : 'Pays non renseigné'}
              </span>
              <Badge ton={dev.disponible ? 'succes' : 'neutre'}>{dev.disponible ? 'Disponible pour une mission' : 'Pas disponible'}</Badge>
            </div>
            {dev.competences.length > 0 ? (
              <ul aria-label="Compétences" className="m-0 flex list-none flex-wrap gap-1.5 p-0">
                {dev.competences.map((item) => (
                  <li key={item}>
                    <Badge ton="marque">{item}</Badge>
                  </li>
                ))}
              </ul>
            ) : null}
            <div className="mt-1 flex flex-col gap-2 tablette:flex-row tablette:items-center">
              {moiMeme ? (
                <>
                  <LienBouton to="/profil">Modifier mon profil</LienBouton>
                  <LienBouton to="/tableau" variante="contour">
                    Mon tableau de bord
                  </LienBouton>
                </>
              ) : (
                <Bouton
                  icone={<Send aria-hidden="true" className="size-4" />}
                  onClick={() =>
                    void selonCompte(
                      () => {
                        setOuvertures((nombre) => nombre + 1)
                        setMission(true)
                      },
                      () => setConnexion(true),
                    )
                  }
                >
                  Proposer une mission
                </Bouton>
              )}
              {!moiMeme ? (
                <Bouton
                  variante="secondaire"
                  icone={<MessagesSquare aria-hidden="true" className="size-4" />}
                  onClick={() =>
                    void selonCompte(
                      () => {
                        setOuvertures((nombre) => nombre + 1)
                        setInvitation(true)
                      },
                      () => setConnexion(true),
                    )
                  }
                >
                  Inviter à échanger
                </Bouton>
              ) : null}
              {dev.github_url ? (
                <a href={dev.github_url} target="_blank" rel="noreferrer" className={classesBouton('contour', 'md')}>
                  Voir son GitHub
                  <ExternalLink aria-hidden="true" className="size-4" />
                  <span className="pour-lecteur"> (nouvel onglet)</span>
                </a>
              ) : null}
              {!moiMeme ? <span className="text-petit text-texte-doux">Contacter un dev est gratuit.</span> : null}
            </div>
          </div>
        </div>
        <div className="flex flex-col gap-2 rounded-moyen bg-surface-accent p-5">
          <p className="m-0 text-etiquette text-sur-primaire-doux uppercase">Souk Score</p>
          <p className="m-0 flex items-baseline gap-1.5">
            <span className="chiffres text-affiche text-titre">{dev.souk_score.total}</span>
            <span className="text-petit text-texte-doux">/ 1000</span>
          </p>
          <Badge ton="alerte" icone={<Award aria-hidden="true" className="size-3.5" />} className="self-start">
            {niveaux[dev.reputation.niveau]} · {dev.reputation.xp.toLocaleString('fr-FR')} XP
          </Badge>
        </div>
      </Carte>

      <section aria-label="Statistiques" className="grid grid-cols-2 gap-3 tablette:grid-cols-5 [&>:last-child]:col-span-2 tablette:[&>:last-child]:col-span-1">
        <Chiffre valeur={String(dev.stats.bugs_resolus)} legende={dev.stats.bugs_resolus >= 2 ? 'bugs résolus' : 'bug résolu'} />
        <Chiffre valeur={String(dev.stats.applis_publiees)} legende={dev.stats.applis_publiees >= 2 ? 'applis publiées' : 'appli publiée'} />
        <Chiffre valeur={String(dev.stats.missions_terminees)} legende={dev.stats.missions_terminees >= 2 ? 'missions terminées' : 'mission terminée'} />
        <Chiffre valeur={note === null ? '—' : note.toLocaleString('fr-FR')} legende={note === null ? 'pas encore de note' : `note des clients (${dev.stats.nb_notes})`} />
        <Chiffre valeur={String(etoiles)} legende="étoiles GitHub" />
      </section>

      <div className="grid gap-6 bureau:grid-cols-2">
        <Carte as="section" aria-labelledby="titre-score" className="flex flex-col gap-4">
          <EnTeteCarte titre={<span id="titre-score">Détail du Souk Score</span>} description="Calculé à partir d’actions validées par d’autres membres." />
          <ul className="m-0 flex list-none flex-col gap-3 p-0">
            {partsScore.map((part) => {
              const valeur = dev.souk_score[part.cle]
              const largeur = Math.min(100, Math.round((valeur / part.max) * 100))
              return (
                <li key={part.cle} className="flex flex-col gap-1.5">
                  <div className="flex justify-between text-petit">
                    <span className="font-medium text-titre">{part.nom}</span>
                    <span className="chiffres text-texte-doux">
                      {valeur} / {part.max}
                    </span>
                  </div>
                  <div role="progressbar" aria-label={part.nom} aria-valuenow={valeur} aria-valuemin={0} aria-valuemax={part.max} className="h-2 overflow-hidden rounded-pastille bg-surface-douce">
                    <div className="h-full rounded-pastille bg-primaire" style={{ width: `${largeur}%` }} />
                  </div>
                </li>
              )
            })}
          </ul>
        </Carte>
        <Carte as="section" aria-labelledby="titre-badges" className="flex flex-col gap-4">
          <EnTeteCarte titre={<span id="titre-badges">Badges</span>} description={`${dev.reputation.badges.length} sur ${badges.length} obtenus.`} />
          <ul className="m-0 grid list-none grid-cols-2 gap-2 p-0">
            {badges.map((badge) => {
              const obtenu = dev.reputation.badges.includes(badge.id)
              return (
                <li key={badge.id} className={cx('flex items-start gap-2 rounded-moyen border p-3', obtenu ? 'border-bordure-succes bg-fond-succes' : 'border-bordure bg-surface-douce')}>
                  <BadgeCheck aria-hidden="true" className={cx('mt-0.5 size-4 shrink-0', obtenu ? 'text-succes' : 'text-texte-indice')} />
                  <span className="flex flex-col">
                    <span className={cx('text-petit font-medium', obtenu ? 'text-titre' : 'text-texte-doux')}>
                      {badge.nom}
                      <span className="pour-lecteur">{obtenu ? ' : obtenu' : ' : pas encore obtenu'}</span>
                    </span>
                    <span className="text-micro text-texte-doux">{badge.regle}</span>
                  </span>
                </li>
              )
            })}
          </ul>
        </Carte>
      </div>

      <section aria-labelledby="titre-applis" className="flex flex-col gap-3">
        <h2 id="titre-applis" className="m-0 text-titre-3 text-titre">
          Applis publiées
        </h2>
        {dev.applis.length === 0 ? <p className="m-0 text-corps text-texte-doux">Aucune appli publiée pour le moment.</p> : null}
        {dev.applis.length > 0 ? (
          <ul className="m-0 grid list-none gap-5 p-0 tablette:grid-cols-2 bureau:grid-cols-3">
            {dev.applis.map((appli) => (
              <li key={appli.id} className="flex">
                <CarteAppli appli={appli} niveau={3} />
              </li>
            ))}
          </ul>
        ) : null}
      </section>

      <section aria-labelledby="titre-depots" className="flex flex-col gap-3">
        <h2 id="titre-depots" className="m-0 text-titre-3 text-titre">
          Dépôts GitHub publics
        </h2>
        {dev.depots.length === 0 ? <p className="m-0 text-corps text-texte-doux">Aucun dépôt public.</p> : null}
        {dev.depots.length > 0 ? (
          <ul className="m-0 grid list-none gap-3 p-0 tablette:grid-cols-2">
            {dev.depots.map((depot) => (
              <li key={depot.url} className="flex flex-col gap-1 rounded-moyen border border-bordure bg-surface p-4">
                <a href={depot.url} target="_blank" rel="noreferrer" className="font-medium">
                  {depot.nom}
                  <span className="pour-lecteur"> (nouvel onglet)</span>
                </a>
                {depot.description ? <p className="m-0 text-petit text-texte">{depot.description}</p> : null}
                <p className="m-0 flex items-center gap-1 text-petit text-texte-doux">
                  {depot.langage ? `${depot.langage} · ` : ''}
                  <Star aria-hidden="true" className="size-3.5" />
                  <span className="chiffres">{depot.etoiles}</span>
                  <span className="pour-lecteur"> étoiles</span>
                </p>
              </li>
            ))}
          </ul>
        ) : null}
      </section>

      {!moiMeme ? <DemandeMission key={ouvertures} username={username} nom={nom} ouverte={mission} onFermer={() => setMission(false)} /> : null}
      {!moiMeme ? <InviterDev key={`invitation-${ouvertures}`} username={username} nom={nom} ouverte={invitation} onFermer={() => setInvitation(false)} /> : null}
      <ModaleConnexion ouverte={connexion} onFermer={() => setConnexion(false)} action="écrire à ce dev" />
    </div>
  )
}

function Chiffre({ valeur, legende }: { valeur: string; legende: string }) {
  return (
    <div className="flex flex-col gap-0.5 rounded-moyen border border-bordure bg-surface p-4">
      <p className="chiffres m-0 text-titre-2 text-titre">{valeur}</p>
      <p className="m-0 text-petit text-texte-doux">{legende}</p>
    </div>
  )
}

function DemandeMission({ username, nom, ouverte, onFermer }: { username: string; nom: string; ouverte: boolean; onFermer: () => void }) {
  const toast = useToast()
  const [message, setMessage] = useState('')
  const [budget, setBudget] = useState('')
  const [delai, setDelai] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)
  const [erreurServeur, setErreurServeur] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)

  async function envoyer(evenement: FormEvent) {
    evenement.preventDefault()
    if (message.trim().length < 3) {
      setErreur('Décrivez votre besoin en quelques mots.')
      return
    }
    setEnvoi(true)
    setErreur(null)
    setErreurServeur(null)
    try {
      await proposerMission(username, {
        message: message.trim(),
        budget: budget ? Number(budget.replace(/\s/g, '')) : undefined,
        delai: delai || undefined,
      })
      toast.succes('Demande envoyée : elle apparaît dans Missions.')
      setMessage('')
      setBudget('')
      setDelai('')
      onFermer()
    } catch (erreurHttp) {
      setErreurServeur(messageAction(erreurHttp, '10 missions par heure. Réessayez plus tard.'))
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Modale
      ouverte={ouverte}
      onFermer={onFermer}
      titre={`Proposer une mission à ${nom}`}
      description="Privé : visible par vous et ce dev. Contacter un dev est gratuit."
      pied={
        <>
          <Bouton variante="contour" onClick={onFermer}>
            Annuler
          </Bouton>
          <Bouton type="submit" form="mission-profil" chargement={envoi}>
            Envoyer la demande
          </Bouton>
        </>
      }
    >
      <form id="mission-profil" onSubmit={(evenement) => void envoyer(evenement)} className="flex flex-col gap-4" noValidate>
        <Champ libelle="Votre besoin" erreur={erreur}>
          {(liaison) => <ZoneTexte {...liaison} rows={4} value={message} onChange={(evenement) => setMessage(evenement.target.value)} data-autofocus />}
        </Champ>
        <div className="grid gap-4 tablette:grid-cols-2">
          <Champ libelle="Budget" facultatif>
            {(liaison) => <SaisieMontant {...liaison} value={budget} onChange={(evenement) => setBudget(evenement.target.value.replace(/[^\d ]/g, ''))} placeholder="150 000" />}
          </Champ>
          <Champ libelle="Date souhaitée" facultatif>
            {(liaison) => <Saisie {...liaison} type="date" value={delai} onChange={(evenement) => setDelai(evenement.target.value)} />}
          </Champ>
        </div>
        {erreurServeur ? <Alerte ton="danger">{erreurServeur}</Alerte> : null}
      </form>
    </Modale>
  )
}
