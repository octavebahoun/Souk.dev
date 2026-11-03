import { useState, type FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { Search, Sparkles } from 'lucide-react'
import { useTitrePage } from '../../app/titre'
import { listerDevs, proposerMissionDev, type DevResume, type Specialite } from './api'
import { messageAction } from '../echange/api'
import { useSelonCompte } from '../../app/compte'
import { ModaleConnexion } from '../../app/ModaleConnexion'
import { nomPays } from '../echange/texte'
import {
  Alerte,
  Avatar,
  Badge,
  Bouton,
  CaseACocher,
  Champ,
  EnTetePage,
  EtatErreur,
  EtatVide,
  LienBouton,
  Liste,
  Modale,
  PuceFiltre,
  Saisie,
  SaisieMontant,
  SqueletteCartes,
  ZoneTexte,
  formaterNote,
  useToast,
} from '../../ui'

const technos = ['laravel', 'php', 'react', 'vue', 'node', 'mysql', 'postgresql'] as const
const specialites: Specialite[] = ['frontend', 'backend', 'mobile', 'devops', 'securite', 'data']
const suggestions = ['Paiement MoMo', 'Appli mobile', 'Site React', 'Data']

const synonymesBesoin: Record<string, string[]> = {
  api: ['backend', 'node', 'laravel', 'php'],
  money: ['mobile', 'momo'],
  momo: ['mobile', 'flutter', 'momo'],
  paiement: ['mobile', 'flutter', 'momo', 'paiement'],
  paiements: ['mobile', 'flutter', 'momo', 'paiement'],
  site: ['frontend', 'react', 'vue'],
}

const libelleSpecialite: Record<Specialite, string> = {
  frontend: 'Frontend',
  backend: 'Backend',
  mobile: 'Mobile',
  devops: 'DevOps',
  securite: 'Sécurité',
  data: 'Data',
}

const libelleNiveau = {
  debutant: 'Débutant',
  contributeur: 'Contributeur',
  confirme: 'Confirmé',
  expert: 'Expert',
} as const

/** « 0 bug résolu », « 1 bug résolu », « 3 bugs résolus » : le français accorde au pluriel dès 2. */
function pluriel(nombre: number, singulier: string, plurielForme: string): string {
  return `${nombre} ${nombre >= 2 ? plurielForme : singulier}`
}

function mots(texte: string): string[] {
  return (
    texte
      .normalize('NFD')
      .replace(/[̀-ͯ]/g, '')
      .toLowerCase()
      .match(/[a-z0-9]+/g) ?? []
  )
}

/** Part des mots du besoin retrouvés dans le profil (compétences, spécialités, bio), synonymes compris. */
function scoreCorrespondance(dev: DevResume, besoin: string): number {
  const ignores = new Set(['a', 'appli', 'application', 'applications', 'au', 'aux', 'avec', 'de', 'des', 'du', 'en', 'et', 'je', 'la', 'le', 'les', 'ma', 'mon', 'pour', 'un', 'une'])
  const requete = mots(besoin).filter((mot) => !ignores.has(mot))
  if (requete.length === 0) return 0

  const profil = new Set(mots([...dev.competences, ...dev.specialites, dev.bio ?? ''].join(' ')))
  const correspondances = requete.filter((mot) => {
    const candidats = synonymesBesoin[mot] ?? [mot]
    return candidats.some((candidat) => profil.has(candidat) || [...profil].some((terme) => terme.includes(candidat)))
  })
  return correspondances.length / requete.length
}

/**
 * Trouver un dev : décrire son besoin classe les profils par correspondance (sinon par Souk Score),
 * les filtres affinent, chaque carte mène au profil ou à une proposition de mission.
 */
export function Recherche() {
  useTitrePage('Trouver un dev')
  const [params, setParams] = useSearchParams()
  const [besoin, setBesoin] = useState('')
  const selonCompte = useSelonCompte()
  const [cible, setCible] = useState<DevResume | null>(null)
  const [connexion, setConnexion] = useState(false)
  const filtres = {
    pays: params.get('pays') ?? '',
    competence: params.get('competence') ?? '',
    specialite: params.get('specialite') ?? '',
    disponible: params.get('disponible') === '1',
  }
  const filtre = Boolean(filtres.pays || filtres.competence || filtres.specialite || filtres.disponible)
  const devs = useQuery({
    queryKey: ['devs', filtres],
    queryFn: () =>
      listerDevs({
        pays: filtres.pays || undefined,
        competence: filtres.competence || undefined,
        specialite: filtres.specialite || undefined,
        disponible: filtres.disponible || undefined,
      }),
  })

  function changer(cle: string, valeur: string) {
    const suivant = new URLSearchParams(params)
    if (valeur) suivant.set(cle, valeur)
    else suivant.delete(cle)
    setParams(suivant)
  }

  const avecBesoin = besoin.trim() !== ''
  const liste = [...(devs.data?.data ?? [])]
  if (avecBesoin) liste.sort((a, b) => scoreCorrespondance(b, besoin) - scoreCorrespondance(a, besoin))

  return (
    <div className="mx-auto flex w-full max-w-site flex-col gap-6 px-4 py-8 tablette:px-6 bureau:px-8">
      <EnTetePage titre="Trouver un dev" description="Classés par Souk Score : la qualité et l’activité récente, validées par d’autres. Contacter un dev est gratuit." />

      <section aria-label="Votre besoin et filtres" className="flex flex-col gap-4 rounded-moyen border border-bordure bg-surface p-4 tablette:p-5">
        <Champ libelle="Votre besoin" aide="Les devs dont le profil correspond le mieux passent en tête.">
          {(liaison) => (
            <div className="relative">
              <Search aria-hidden="true" className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-texte-indice" />
              <Saisie {...liaison} type="search" value={besoin} onChange={(evenement) => setBesoin(evenement.target.value)} placeholder="Ex. : paiement MoMo, appli mobile, API" className="pl-9" />
            </div>
          )}
        </Champ>
        <div role="group" aria-label="Besoins fréquents" className="flex flex-wrap gap-2">
          {suggestions.map((item) => (
            <PuceFiltre key={item} presse={besoin === item} onClick={() => setBesoin(besoin === item ? '' : item)}>
              {item}
            </PuceFiltre>
          ))}
        </div>
        <div className="grid gap-4 border-t border-bordure pt-4 tablette:grid-cols-3 bureau:grid-cols-[repeat(3,minmax(0,1fr))_auto] bureau:items-end">
          <Champ libelle="Compétence">
            {(liaison) => (
              <Liste {...liaison} value={filtres.competence} onChange={(evenement) => changer('competence', evenement.target.value)}>
                <option value="">Toutes</option>
                {technos.map((techno) => (
                  <option key={techno} value={techno}>
                    {techno}
                  </option>
                ))}
              </Liste>
            )}
          </Champ>
          <Champ libelle="Spécialité">
            {(liaison) => (
              <Liste {...liaison} value={filtres.specialite} onChange={(evenement) => changer('specialite', evenement.target.value)}>
                <option value="">Toutes</option>
                {specialites.map((item) => (
                  <option key={item} value={item}>
                    {libelleSpecialite[item]}
                  </option>
                ))}
              </Liste>
            )}
          </Champ>
          <Champ libelle="Pays">
            {(liaison) => (
              <Liste {...liaison} value={filtres.pays} onChange={(evenement) => changer('pays', evenement.target.value)}>
                <option value="">Tous</option>
                {['BJ', 'SN', 'CI', 'TG', 'CM'].map((code) => (
                  <option key={code} value={code}>
                    {nomPays(code)}
                  </option>
                ))}
              </Liste>
            )}
          </Champ>
          <div className="flex min-h-11 items-center tablette:col-span-3 bureau:col-span-1">
            <CaseACocher checked={filtres.disponible} onChange={() => changer('disponible', filtres.disponible ? '' : '1')} libelle="Disponibles seulement" />
          </div>
        </div>
      </section>

      <div className="flex min-h-11 flex-wrap items-center justify-between gap-3">
        <p role="status" className="m-0 text-petit text-texte-doux">
          {devs.isSuccess ? `${liste.length} dev${liste.length > 1 ? 's' : ''} · ${avecBesoin ? 'classés par correspondance' : 'classés par Souk Score'}` : ''}
        </p>
        {filtre || avecBesoin ? (
          <Bouton
            variante="fantome"
            taille="sm"
            onClick={() => {
              setParams({})
              setBesoin('')
            }}
          >
            Réinitialiser
          </Bouton>
        ) : null}
      </div>

      {devs.isPending ? <SqueletteCartes nombre={6} avecMedia={false} /> : null}
      {devs.isError ? <EtatErreur description={messageAction(devs.error, 'Les profils de développeurs n’ont pas pu être chargés.')} onReessayer={() => void devs.refetch()} /> : null}
      {devs.isSuccess && liste.length === 0 ? (
        <EtatVide
          illustration="vide-recherche"
          titre="Aucun dev ne correspond"
          description="Retirez un filtre, ou proposez votre besoin comme mission sur une appli du Store."
          action={<Bouton onClick={() => setParams({})}>Réinitialiser les filtres</Bouton>}
        />
      ) : null}
      {liste.length > 0 ? (
        <ul className="m-0 grid list-none gap-5 p-0 tablette:grid-cols-2 bureau:grid-cols-3">
          {liste.map((dev) => (
            <li key={dev.id} className="flex">
              <CarteDev dev={dev} correspondance={avecBesoin ? scoreCorrespondance(dev, besoin) : null} onMission={() => void selonCompte(() => setCible(dev), () => setConnexion(true))} />
            </li>
          ))}
        </ul>
      ) : null}
      <MissionDev key={cible?.id ?? 'aucun'} dev={cible} onFermer={() => setCible(null)} />
      <ModaleConnexion ouverte={connexion} onFermer={() => setConnexion(false)} action="proposer une mission" />
    </div>
  )
}

function CarteDev({ dev, correspondance, onMission }: { dev: DevResume; correspondance: number | null; onMission: () => void }) {
  const nom = dev.nom || dev.username || 'Dev'
  return (
    <article className="flex w-full flex-col gap-4 rounded-moyen border border-bordure bg-surface p-5 shadow-1">
      <div className="flex items-start gap-3">
        <Avatar nom={nom} url={dev.avatar_url} taille="lg" />
        <div className="min-w-0 flex-1">
          <h2 className="m-0 text-titre-3">
            {dev.username ? (
              <Link to={`/devs/${dev.username}`} className="text-titre no-underline hover:underline">
                {nom}
              </Link>
            ) : (
              <span className="text-titre">{nom}</span>
            )}
          </h2>
          <p className="m-0 text-petit text-texte-doux">
            @{dev.username || 'compte'}
            {dev.pays ? ` · ${nomPays(dev.pays)}` : ''}
          </p>
        </div>
        <p className="m-0 flex flex-col items-end">
          <span className="chiffres text-titre-2 text-sur-primaire-doux">{dev.souk_score.total}</span>
          <span className="text-micro text-texte-doux">Souk Score</span>
        </p>
      </div>
      <div className="flex flex-wrap gap-1.5">
        {correspondance !== null ? (
          <Badge ton={correspondance >= 0.5 ? 'succes' : 'neutre'} icone={<Sparkles aria-hidden="true" className="size-3.5" />}>
            {Math.round(correspondance * 100)} % de correspondance
          </Badge>
        ) : null}
        <Badge ton={dev.disponible ? 'succes' : 'neutre'}>{dev.disponible ? 'Disponible' : 'Pas disponible'}</Badge>
        <Badge ton="alerte">{libelleNiveau[dev.reputation.niveau]}</Badge>
        {dev.specialites.map((item) => (
          <Badge key={item} ton="marque">
            {libelleSpecialite[item]}
          </Badge>
        ))}
      </div>
      <p className="m-0 text-corps text-texte">{dev.competences.join(' · ') || 'Compétences non renseignées'}</p>
      <p className="m-0 text-petit text-texte-doux">
        {pluriel(dev.stats.bugs_resolus, 'bug résolu', 'bugs résolus')} · {pluriel(dev.stats.missions_terminees, 'mission', 'missions')} ·{' '}
        {dev.stats.note_moyenne == null ? 'pas encore noté' : `${formaterNote(dev.stats.note_moyenne)} / 5`}
      </p>
      <div className="mt-auto flex gap-2 border-t border-bordure pt-4">
        {dev.username ? (
          <LienBouton to={`/devs/${dev.username}`} variante="contour" className="flex-1">
            Voir le profil
          </LienBouton>
        ) : null}
        <Bouton variante="secondaire" className="flex-1" disabled={!dev.username} onClick={onMission}>
          Proposer une mission
        </Bouton>
      </div>
    </article>
  )
}

/** Mission proposée directement à un dev : privée, suivie dans « Missions ». */
function MissionDev({ dev, onFermer }: { dev: DevResume | null; onFermer: () => void }) {
  const toast = useToast()
  const [erreur, setErreur] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)

  async function envoyer(evenement: FormEvent<HTMLFormElement>) {
    evenement.preventDefault()
    if (!dev?.username) return
    const donnees = new FormData(evenement.currentTarget)
    const budget = String(donnees.get('budget') ?? '').replace(/\s/g, '')
    setEnvoi(true)
    setErreur(null)
    try {
      await proposerMissionDev(dev.username, {
        message: String(donnees.get('message') ?? ''),
        budget: budget ? Number(budget) : undefined,
        delai: String(donnees.get('delai') ?? '') || undefined,
      })
      toast.succes('Demande envoyée : elle apparaît dans Missions.')
      onFermer()
    } catch (erreurHttp) {
      setErreur(messageAction(erreurHttp, 'La demande n’a pas été envoyée.'))
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Modale
      ouverte={dev !== null}
      onFermer={onFermer}
      titre={`Proposer une mission à ${dev?.nom || dev?.username || 'ce dev'}`}
      description="Privé : visible par vous et ce dev. Contacter un dev est gratuit."
      pied={
        <>
          <Bouton variante="contour" onClick={onFermer}>
            Annuler
          </Bouton>
          <Bouton type="submit" form="mission-dev" chargement={envoi}>
            Envoyer la demande
          </Bouton>
        </>
      }
    >
      <form id="mission-dev" onSubmit={(evenement) => void envoyer(evenement)} className="flex flex-col gap-4">
        {dev && !dev.disponible ? <Alerte ton="alerte">Ce dev s’est marqué indisponible : il peut quand même répondre.</Alerte> : null}
        <Champ libelle="Votre besoin">
          {(liaison) => <ZoneTexte {...liaison} name="message" required rows={4} placeholder="Ex. : ajouter le paiement MoMo à ma boutique en ligne" data-autofocus />}
        </Champ>
        <div className="grid gap-4 tablette:grid-cols-2">
          <Champ libelle="Budget" facultatif>
            {(liaison) => <SaisieMontant {...liaison} name="budget" placeholder="150 000" />}
          </Champ>
          <Champ libelle="Date souhaitée" facultatif>
            {(liaison) => <Saisie {...liaison} name="delai" type="date" />}
          </Champ>
        </div>
        {erreur ? <Alerte ton="danger">{erreur}</Alerte> : null}
      </form>
    </Modale>
  )
}
