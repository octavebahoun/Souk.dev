import { useState, type FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router'
import { isAxiosError } from 'axios'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ChevronRight, CircleCheck, Plus, Send, Star } from 'lucide-react'
import { useTitrePage } from '../../app/titre'
import { ecrireMessageMission, fcfa, listerMessagesMission, listerMissions, noterMission, type Mission } from './api'
import { lireMoi, messageAction } from '../echange/api'
import {
  Alerte,
  Avatar,
  Badge,
  Bouton,
  Carte,
  Champ,
  EnTetePage,
  EtatErreur,
  EtatVide,
  LienBouton,
  Modale,
  Saisie,
  SqueletteListe,
  SqueletteLignes,
  ZoneTexte,
  cx,
} from '../../ui'

type Personne = { nom: string | null; username: string | null }

function nom(personne: Personne) {
  return personne.nom || personne.username || 'Quelqu’un'
}

function depuis(iso: string, maintenant: number) {
  const minutes = Math.round((maintenant - new Date(iso).getTime()) / 60000)
  if (minutes < 1) return 'à l’instant'
  if (minutes < 60) return `il y a ${minutes} min`
  const heures = Math.round(minutes / 60)
  if (heures < 24) return `il y a ${heures} h`
  return `il y a ${Math.round(heures / 24)} j`
}

/**
 * Missions : les échanges privés entre un client et un dev (personnalisation d'une appli ou
 * recrutement direct). La liste à gauche, la mission ouverte à droite ; la mission ouverte vit dans
 * l'URL (?mission=), pour que le lien d'une notification l'ouvre.
 */
export function Missions() {
  useTitrePage('Missions')
  const missions = useQuery({ queryKey: ['missions'], queryFn: listerMissions })
  const moi = useQuery({ queryKey: ['moi'], queryFn: lireMoi })
  const [parametres, setParametres] = useSearchParams()
  const choisie = Number(parametres.get('mission')) || null
  const choisir = (id: number) => setParametres({ mission: String(id) }, { replace: true })
  const [nouvelle, setNouvelle] = useState(false)
  const [maintenant] = useState(() => Date.now())
  const liste = missions.data?.data ?? []
  const mission = liste.find((item) => item.id === (choisie ?? liste[0]?.id)) ?? null

  return (
    <div className="flex flex-col gap-6">
      <EnTetePage
        titre="Missions"
        description="Personnaliser une appli avec son auteur, ou recruter un dev directement : des missions privées, sans enchères, payées à la livraison."
        actions={
          <Bouton icone={<Plus aria-hidden="true" className="size-4" />} onClick={() => setNouvelle(true)}>
            Nouvelle mission
          </Bouton>
        }
      />

      {missions.isPending ? <SqueletteListe nombre={3} /> : null}
      {missions.isError ? (
        isAxiosError(missions.error) && missions.error.response?.status === 401 ? (
          <EtatVide illustration="acces-refuse" titre="Connectez-vous pour voir vos missions" action={<LienBouton to="/connexion">Se connecter</LienBouton>} />
        ) : (
          <EtatErreur description={messageAction(missions.error, 'Les missions n’ont pas pu être chargées.')} onReessayer={() => void missions.refetch()} />
        )
      ) : null}
      {missions.isSuccess && liste.length === 0 ? (
        <EtatVide
          illustration="vide-missions"
          titre="Aucune mission pour le moment"
          description="Faites adapter une appli du Store par son auteur, ou proposez votre projet à un dev. Vous choisissez qui, il est payé quand vous validez."
          action={<LienBouton to="/store">Personnaliser une appli</LienBouton>}
          actionSecondaire={
            <LienBouton to="/devs" variante="contour">
              Recruter un dev
            </LienBouton>
          }
        />
      ) : null}

      {liste.length > 0 ? (
        <div className="grid gap-6 bureau:grid-cols-[20rem_minmax(0,1fr)] bureau:items-start">
          <nav aria-label="Mes missions" className="flex min-w-0 flex-col gap-1 rounded-moyen border border-bordure bg-surface p-2">
            {liste.map((item) => {
              const actif = item.id === mission?.id
              const autre = moi.data?.id === item.client.id ? item.auteur : item.client
              return (
                <button
                  key={item.id}
                  type="button"
                  aria-current={actif ? 'true' : undefined}
                  onClick={() => choisir(item.id)}
                  className={cx('flex items-start gap-3 rounded-petit p-3 text-left transition-colors duration-150', actif ? 'bg-surface-accent' : 'hover:bg-surface-douce')}
                >
                  <Avatar nom={nom(autre)} taille="sm" />
                  <span className="flex min-w-0 flex-1 flex-col gap-1">
                    <span className="flex w-full items-baseline justify-between gap-2">
                      <span className={cx('truncate text-corps font-medium', actif ? 'text-sur-primaire-doux' : 'text-titre')}>Mission avec {nom(autre)}</span>
                      <span className="shrink-0 text-micro text-texte-doux">{depuis(item.cree_le, maintenant)}</span>
                    </span>
                    <Etiquettes mission={item} moiId={moi.data?.id} />
                  </span>
                </button>
              )
            })}
          </nav>
          {mission ? <Detail key={mission.id} mission={mission} moiId={moi.data?.id} maintenant={maintenant} /> : null}
        </div>
      ) : null}

      <Modale ouverte={nouvelle} onFermer={() => setNouvelle(false)} titre="Nouvelle mission" description="Que voulez-vous faire ?" taille="petite">
        <div className="flex flex-col gap-2">
          <Choix to="/store" titre="Personnaliser une appli" texte="Choisissez une appli du Store et demandez des changements à son auteur." />
          <Choix to="/devs" titre="Recruter un dev" texte="Trouvez un dev par compétence et proposez-lui votre projet." />
        </div>
      </Modale>
    </div>
  )
}

function Detail({ mission, moiId, maintenant }: { mission: Mission; moiId: number | undefined; maintenant: number }) {
  const messages = useQuery({ queryKey: ['mission-messages', mission.id], queryFn: () => listerMessagesMission(mission.id) })
  const cache = useQueryClient()
  const [texte, setTexte] = useState('')
  const [noterOuvert, setNoterOuvert] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)
  const client = moiId === mission.client.id
  const enCours = mission.statut === 'en_cours'
  const autre = client ? mission.auteur : mission.client

  async function envoyer(evenement: FormEvent) {
    evenement.preventDefault()
    const propre = texte.trim()
    if (!propre) return
    setEnvoi(true)
    try {
      await ecrireMessageMission(mission.id, propre)
      setTexte('')
      setErreur(null)
      await cache.invalidateQueries({ queryKey: ['mission-messages', mission.id] })
    } catch (erreurHttp) {
      setErreur(messageAction(erreurHttp, 'Le message n’a pas été envoyé.'))
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Carte as="section" marge="aucune" aria-labelledby="titre-mission" className="flex min-w-0 flex-col overflow-hidden">
      <div className="flex flex-col gap-3 border-b border-bordure p-5">
        <div className="flex flex-col gap-3 tablette:flex-row tablette:items-start tablette:justify-between">
          <div className="flex flex-col gap-1.5">
            <h2 id="titre-mission" className="m-0 text-titre-3 text-titre">
              Mission avec {nom(autre)}
            </h2>
            <Etiquettes mission={mission} moiId={moiId} />
          </div>
          {client && enCours ? (
            <Bouton variante="contour" className="shrink-0" icone={<CircleCheck aria-hidden="true" className="size-4" />} onClick={() => setNoterOuvert(true)}>
              Terminer et noter
            </Bouton>
          ) : null}
        </div>
        <dl className="m-0 grid gap-x-6 gap-y-1.5 text-petit tablette:grid-cols-2">
          <Meta cle="Client" valeur={nom(mission.client)} />
          <Meta cle={mission.app_id ? 'Auteur' : 'Dev'} valeur={nom(mission.auteur)} />
          <Meta cle="Budget" valeur={mission.budget != null ? fcfa(mission.budget) : 'Non précisé'} />
          <Meta cle="Pour le" valeur={mission.delai ? new Date(mission.delai).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }) : 'Non précisé'} />
        </dl>
        <p className="m-0 text-petit text-texte-doux">
          {mission.app_id ? (
            <>
              Personnalisation d’une appli du Store : <Link to={`/store/${mission.app_id}`} className="underline underline-offset-2">voir sa fiche</Link>.{' '}
            </>
          ) : (
            'Recrutement direct : le client a trouvé le dev dans « Trouver un dev ». '
          )}
          Visible uniquement par le client et le dev.
        </p>
        {mission.note ? <NoteBloc note={mission.note.note} commentaire={mission.note.commentaire} mienne={client} /> : null}
      </div>

      <ol aria-label="Messages" className="m-0 flex list-none flex-col gap-4 p-5">
        <li className="flex flex-col gap-1 rounded-moyen border border-bordure-alerte bg-fond-alerte p-4">
          <span className="text-etiquette text-alerte uppercase">Le besoin</span>
          <span className="text-corps whitespace-pre-wrap text-titre">{mission.message}</span>
        </li>
        {messages.isPending ? <SqueletteLignes lignes={2} /> : null}
        {messages.data?.data.map((message) => {
          const mien = message.auteur.id === moiId
          return (
            <li key={message.id} className={cx('flex flex-col gap-1', mien ? 'items-end' : 'items-start')}>
              <span className="text-micro text-texte-doux">
                {nom(message.auteur)} · {depuis(message.cree_le, maintenant)}
              </span>
              <span className={cx('max-w-[85%] rounded-grand px-4 py-2.5 text-corps whitespace-pre-wrap', mien ? 'rounded-br-petit bg-primaire text-sur-primaire' : 'rounded-bl-petit bg-surface-douce text-titre')}>
                {message.texte}
              </span>
            </li>
          )
        })}
      </ol>

      {enCours ? (
        <form onSubmit={(evenement) => void envoyer(evenement)} className="flex flex-col gap-2 border-t border-bordure p-4">
          <div className="flex items-end gap-2">
            <Champ libelle="Votre message" libelleCache className="flex-1">
              {(liaison) => <Saisie {...liaison} value={texte} onChange={(evenement) => setTexte(evenement.target.value)} placeholder="Écrire un message…" />}
            </Champ>
            <Bouton type="submit" chargement={envoi} icone={<Send aria-hidden="true" className="size-4" />}>
              Envoyer
            </Bouton>
          </div>
          {erreur ? <Alerte ton="danger">{erreur}</Alerte> : null}
        </form>
      ) : (
        <div className="border-t border-bordure p-4">
          <Alerte ton="succes" titre="Mission terminée">
            La discussion est fermée.
          </Alerte>
        </div>
      )}

      <Noter mission={mission} ouverte={noterOuvert} onFermer={() => setNoterOuvert(false)} />
    </Carte>
  )
}

function Noter({ mission, ouverte, onFermer }: { mission: Mission; ouverte: boolean; onFermer: () => void }) {
  const cache = useQueryClient()
  const [note, setNote] = useState(0)
  const [commentaire, setCommentaire] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)
  const [envoi, setEnvoi] = useState(false)

  async function envoyer(evenement: FormEvent) {
    evenement.preventDefault()
    if (note < 1) {
      setErreur('Choisissez une note de 1 à 5.')
      return
    }
    setEnvoi(true)
    try {
      await noterMission(mission.id, note, commentaire.trim())
      await cache.invalidateQueries({ queryKey: ['missions'] })
      onFermer()
    } catch (erreurHttp) {
      setErreur(messageAction(erreurHttp, 'La note n’a pas été enregistrée.'))
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <Modale
      ouverte={ouverte}
      onFermer={onFermer}
      titre="Terminer et noter la mission"
      description={`Comment s’est passée la mission avec ${nom(mission.auteur)} ? Une seule fois : la note compte dans sa réputation et son Souk Score.`}
      pied={
        <>
          <Bouton variante="contour" onClick={onFermer}>
            Annuler
          </Bouton>
          <Bouton type="submit" form="noter-mission" chargement={envoi}>
            Terminer la mission
          </Bouton>
        </>
      }
    >
      <form id="noter-mission" onSubmit={(evenement) => void envoyer(evenement)} className="flex flex-col gap-4">
        <fieldset className="m-0 border-0 p-0">
          <legend className="mb-2 text-petit font-medium text-titre">Votre note</legend>
          <div className="flex flex-wrap items-center gap-1">
            {[1, 2, 3, 4, 5].map((valeur) => (
              <button
                key={valeur}
                type="button"
                aria-label={`${valeur} sur 5`}
                aria-pressed={note === valeur}
                onClick={() => {
                  setNote(valeur)
                  setErreur(null)
                }}
                className="grid size-11 place-items-center rounded-moyen hover:bg-surface-douce"
              >
                <Star aria-hidden="true" className={cx('size-6 text-alerte', valeur <= note && 'fill-current')} />
              </button>
            ))}
          </div>
        </fieldset>
        <Champ libelle="Commentaire" facultatif>
          {(liaison) => <ZoneTexte {...liaison} value={commentaire} onChange={(evenement) => setCommentaire(evenement.target.value)} rows={3} placeholder="Ce qui vous a plu, ce qui peut être amélioré…" />}
        </Champ>
        {erreur ? <Alerte ton="danger">{erreur}</Alerte> : null}
      </form>
    </Modale>
  )
}

function Etiquettes({ mission, moiId }: { mission: Mission; moiId: number | undefined }) {
  const client = moiId === mission.client.id
  const directe = mission.app_id == null
  const terminee = mission.statut === 'terminee'
  return (
    <span className="flex flex-wrap gap-1.5">
      <Badge ton={terminee ? 'succes' : 'info'}>{terminee ? 'Terminée' : 'En cours'}</Badge>
      <Badge ton={client ? 'alerte' : 'marque'}>{client ? 'Vous êtes le client' : directe ? 'Vous êtes le dev' : 'Vous êtes l’auteur'}</Badge>
      <Badge>{directe ? 'Recrutement direct' : 'Personnalisation'}</Badge>
    </span>
  )
}

function NoteBloc({ note, commentaire, mienne }: { note: number; commentaire: string | null; mienne: boolean }) {
  return (
    <div className="flex flex-col gap-1 rounded-moyen border border-bordure bg-surface-douce p-3">
      <div className="flex flex-wrap items-center gap-2 text-petit">
        <span className="font-medium text-texte">{mienne ? 'Votre note' : 'Note du client'}</span>
        <span role="img" aria-label={`${note} étoiles sur 5`} className="flex">
          {[1, 2, 3, 4, 5].map((valeur) => (
            <Star key={valeur} aria-hidden="true" className={cx('size-4 text-alerte', valeur <= note && 'fill-current')} />
          ))}
        </span>
        <strong className="chiffres text-titre">{note} / 5</strong>
      </div>
      {commentaire ? <p className="m-0 text-petit text-texte">« {commentaire} »</p> : null}
    </div>
  )
}

function Meta({ cle, valeur }: { cle: string; valeur: string }) {
  return (
    <div className="flex gap-1.5">
      <dt className="text-texte-doux">{cle}</dt>
      <dd className="m-0 font-medium text-titre">{valeur}</dd>
    </div>
  )
}

function Choix({ to, titre, texte }: { to: string; titre: string; texte: string }) {
  return (
    <Link to={to} className="flex items-center gap-3 rounded-moyen border border-bordure p-4 text-titre no-underline hover:border-bordure-champ hover:bg-surface-douce">
      <span className="flex min-w-0 flex-1 flex-col gap-0.5">
        <strong className="font-medium">{titre}</strong>
        <span className="text-petit text-texte-doux">{texte}</span>
      </span>
      <ChevronRight aria-hidden="true" className="size-4 text-texte-doux" />
    </Link>
  )
}
