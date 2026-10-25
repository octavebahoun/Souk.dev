import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { isAxiosError } from 'axios'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { ecrireMessageMission, fcfa, listerMessagesMission, listerMissions, noterMission, type Mission } from './api'
import { lireMoi, messageAction } from '../echange/api'
import { Modal } from '../../shared/ui/Modal'

export function Missions() {
  const missions = useQuery({ queryKey: ['missions'], queryFn: listerMissions })
  const moi = useQuery({ queryKey: ['moi'], queryFn: lireMoi })
  const [choisie, setChoisie] = useState<number | null>(null)
  const [nouvelle, setNouvelle] = useState(false)
  const [maintenant] = useState(() => Date.now())
  const liste = missions.data?.data ?? []
  const mission = liste.find((item) => item.id === (choisie ?? liste[0]?.id)) ?? null

  return (
    <main className="missions-page min-h-[calc(100svh-80px)] bg-[#F1F3F8]">
      <div className="mx-auto flex max-w-[1440px] flex-col gap-7 px-4 py-8 min-[761px]:px-6 min-[761px]:pt-10">
        <section className="missions-hero relative grid overflow-hidden rounded-[30px] border border-[#E8E4DD] bg-canvas px-6 py-10 shadow-[0_18px_50px_rgba(15,23,42,0.05)] min-[901px]:min-h-[680px] min-[901px]:grid-cols-[minmax(0,0.92fr)_minmax(0,1.08fr)] min-[901px]:items-center min-[901px]:px-[7%] min-[901px]:py-12">
          <div className="relative z-10 flex flex-col items-start gap-5">
            <div aria-hidden="true" className="missions-ornement">
              <span /><span /><span />
            </div>
            <h1 className="m-0 text-[48px] leading-[1.02] font-extrabold tracking-[-0.045em] min-[901px]:text-[72px]">Nos missions</h1>
            <p className="m-0 max-w-[48ch] text-[17px] leading-relaxed text-muted min-[901px]:text-[19px]">
              Personnaliser une appli avec son auteur, ou recruter un dev directement. Des missions privées, sans enchères.
            </p>
            <div className="mt-2 flex flex-col gap-5 min-[761px]:gap-6">
              <AtoutMission icone="cle" titre="Sur mesure" texte="Faites adapter une appli du Store à votre activité, directement avec son auteur." />
              <AtoutMission icone="cible" titre="Sans enchères" texte="Vous choisissez le dev. Pas d’appel d’offres, pas de course au moins cher." />
              <AtoutMission icone="valide" titre="Payé à la livraison" texte="Le dev est payé quand vous validez son travail. Vous le notez ensuite." />
            </div>
            <button type="button" onClick={() => setNouvelle(true)} className="mt-1 inline-flex min-h-[54px] items-center gap-3 rounded-[14px] bg-ink px-6 font-bold text-canvas shadow-[0_12px_24px_rgba(15,23,42,0.14)]">
              <span aria-hidden="true" className="text-xl leading-none">＋</span>Nouvelle mission
            </button>
          </div>
          <MissionArtwork />
        </section>

      {missions.isPending ? <div role="status" className="squelette h-40 rounded-carte" /> : null}
      {missions.isError ? (
        <div className="rounded-carte border border-line bg-canvas p-6">
          <p role="alert" className="m-0">{messageAction(missions.error, 'Les missions n’ont pas pu être chargées.')}</p>
          <div className="mt-3 flex flex-wrap gap-2">
            {isAxiosError(missions.error) && missions.error.response?.status === 401 ? <Link to="/connexion" className="inline-flex min-h-cible items-center rounded-controle bg-accent px-4 font-semibold text-canvas no-underline">Connexion</Link> : null}
            <button type="button" onClick={() => void missions.refetch()} className="min-h-cible rounded-controle border border-line px-4 font-semibold">Réessayer</button>
          </div>
        </div>
      ) : null}
      {missions.isSuccess && liste.length === 0 ? (
        <p className="m-0 rounded-carte border border-dashed border-line bg-canvas p-8 text-center text-subtle">Aucune mission pour le moment. Choisissez une appli ou un dev pour en ouvrir une.</p>
      ) : null}

      {liste.length > 0 ? (
        <div className="grid items-start gap-5 min-[1000px]:grid-cols-[420px_minmax(0,1fr)]">
          <nav aria-label="Mes missions" className="flex min-w-0 flex-col gap-1 rounded-carte border border-line bg-canvas p-2">
            {liste.map((item) => {
              const actif = item.id === mission?.id
              const autre = moi.data?.id === item.client.id ? item.auteur : item.client
              return (
                <button key={item.id} data-reveal-item type="button" aria-pressed={actif} onClick={() => setChoisie(item.id)} className={`flex flex-col gap-1 rounded-[10px] px-3.5 py-3 text-left ${actif ? 'bg-nav' : 'bg-transparent'}`}>
                  <span className="flex w-full justify-between gap-2">
                    <strong className="text-[15px]">{titreMission(item)}</strong>
                    <span className="text-[12.5px] text-muted">{depuis(item.cree_le, maintenant)}</span>
                  </span>
                  <span className="text-[13.5px] text-subtle">avec {nom(autre)}</span>
                  <Puces mission={item} moiId={moi.data?.id} />
                </button>
              )
            })}
          </nav>
          {mission ? <Detail mission={mission} moiId={moi.data?.id} maintenant={maintenant} /> : null}
        </div>
      ) : null}

      {nouvelle ? (
        <Modal titre="Nouvelle mission" onFermer={() => setNouvelle(false)}>
          <p className="m-0 text-subtle">Que voulez-vous faire ?</p>
          <Choix to="/store" titre="Personnaliser une appli" texte="Choisissez une appli du Store et demandez des changements à son auteur." />
          <Choix to="/devs" titre="Recruter un dev" texte="Trouvez un dev par compétence et proposez-lui votre projet." />
        </Modal>
      ) : null}
      </div>
    </main>
  )
}

function AtoutMission({ icone, titre, texte }: { icone: 'cle' | 'cible' | 'valide'; titre: string; texte: string }) {
  return (
    <article className="flex items-center gap-5">
      <span className="missions-icone grid size-[66px] shrink-0 rotate-45 place-items-center rounded-[15px] border border-[#60749A] bg-white shadow-[0_10px_22px_rgba(15,23,42,0.08)]">
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" className="size-6 -rotate-45 text-accent">
          {icone === 'cle' ? <><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.6 2.6-2.4-.6-.6-2.4z" /></> : null}
          {icone === 'cible' ? <><circle cx="12" cy="12" r="9" /><circle cx="12" cy="12" r="4" /><circle cx="12" cy="12" r="1" /></> : null}
          {icone === 'valide' ? <><circle cx="12" cy="12" r="9" /><path d="m8 12 2.5 2.5L16.5 9" /></> : null}
        </svg>
      </span>
      <span className="flex max-w-[430px] flex-col gap-1">
        <strong className="text-[19px] leading-tight min-[901px]:text-[22px]">{titre}</strong>
        <span className="text-[15px] leading-relaxed text-muted min-[901px]:text-[17px]">{texte}</span>
      </span>
    </article>
  )
}

function MissionArtwork() {
  return (
    <div className="missions-art relative mx-auto mt-8 h-[380px] w-full max-w-[660px] min-[901px]:mt-0 min-[901px]:h-[620px]" aria-hidden="true">
      <div className="missions-art-halo" />
      <div className="missions-art-image missions-art-team"><SceneEquipe /></div>
      <div className="missions-art-image missions-art-dev"><SceneDev /></div>
      <div className="missions-art-image missions-art-code"><SceneCode /></div>
      <div className="missions-art-image missions-art-meeting"><SceneRencontre /></div>
      <span className="missions-art-tag missions-tag-react">React</span>
      <span className="missions-art-tag missions-tag-laravel">Laravel</span>
      <span className="missions-art-tag missions-tag-momo">MoMo</span>
      <span className="missions-art-tag missions-tag-mysql">MySQL</span>
    </div>
  )
}

function SceneEquipe() {
  return <img src="https://images.unsplash.com/photo-1521737711867-e3b97375f902?auto=format&fit=crop&w=1000&q=84" alt="" loading="eager" decoding="async" fetchPriority="high" className="size-full object-cover" />
}

function SceneDev() {
  return <img src="https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=900&q=82" alt="" loading="lazy" decoding="async" className="size-full object-cover" />
}

function SceneCode() {
  return <img src="https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=900&q=82" alt="" loading="lazy" decoding="async" className="size-full object-cover" />
}

function SceneRencontre() {
  return <img src="https://images.unsplash.com/photo-1522071820081-009f0129c71c?auto=format&fit=crop&w=900&q=82" alt="" loading="lazy" decoding="async" className="size-full object-cover" />
}

function Detail({ mission, moiId, maintenant }: { mission: Mission; moiId: number | undefined; maintenant: number }) {
  const messages = useQuery({ queryKey: ['mission-messages', mission.id], queryFn: () => listerMessagesMission(mission.id) })
  const cache = useQueryClient()
  const [texte, setTexte] = useState('')
  const [noterOuvert, setNoterOuvert] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)
  const client = moiId === mission.client.id
  const enCours = mission.statut === 'en_cours'

  async function envoyer(evenement: FormEvent) {
    evenement.preventDefault()
    const propre = texte.trim()
    if (!propre) return
    try {
      await ecrireMessageMission(mission.id, propre)
      setTexte('')
      setErreur(null)
      await cache.invalidateQueries({ queryKey: ['mission-messages', mission.id] })
    } catch (erreurHttp) {
      setErreur(messageAction(erreurHttp, 'Le message n’a pas été envoyé.'))
    }
  }

  return (
    <section aria-labelledby="titre-mission" className="flex min-w-0 flex-col rounded-carte border border-line bg-canvas min-[1000px]:min-h-[560px]">
      <div className="flex flex-col gap-2.5 border-b border-[#EEF2F9] px-6 py-5">
        <div className="flex flex-wrap items-center gap-2">
          <h2 id="titre-mission" className="m-0 text-xl">
            {mission.app_id ? <Link to={`/store/${mission.app_id}`} className="text-ink underline decoration-[#BFD0FA] underline-offset-4">{titreMission(mission)}</Link> : titreMission(mission)}
          </h2>
          <Puces mission={mission} moiId={moiId} />
        </div>
        {mission.app_id ? null : <p className="m-0 text-sm text-subtle">Pas d’appli liée : le client vous a trouvé dans « Trouver un dev ».</p>}
        <dl className="m-0 flex flex-wrap gap-x-6 gap-y-1.5 text-sm">
          <Meta cle="Client" valeur={nom(mission.client)} />
          <Meta cle={mission.app_id ? 'Auteur' : 'Dev'} valeur={nom(mission.auteur)} />
          <Meta cle="Budget" valeur={mission.budget != null ? fcfa(mission.budget) : 'Non précisé'} />
          <Meta cle="Pour le" valeur={mission.delai ? new Date(mission.delai).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' }) : 'Non précisé'} />
        </dl>
        <div className="flex flex-wrap items-center justify-between gap-3">
          <p className="m-0 text-[13px] text-muted">Visible uniquement par le client et l’auteur.</p>
          {client && enCours ? (
            <button type="button" onClick={() => setNoterOuvert(true)} className="min-h-cible rounded-controle border border-accent bg-canvas px-[18px] font-semibold text-accent">Terminer et noter</button>
          ) : null}
        </div>
        {mission.note ? <NoteBloc note={mission.note.note} commentaire={mission.note.commentaire} mienne={client} /> : null}
      </div>

      <ol aria-label="Messages" className="m-0 flex list-none flex-col gap-3.5 px-6 py-5">
        <li className="flex flex-col gap-1.5 rounded-xl border border-[#FDE68A] bg-[#FEF9E7] px-4 py-3.5">
          <span className="text-[12.5px] font-bold tracking-wide text-warning uppercase">Le besoin</span>
          <span className="text-nav-texte">{mission.message}</span>
        </li>
        {messages.data?.data.map((message) => {
          const mien = message.auteur.id === moiId
          return (
            <li key={message.id} className={`flex flex-col gap-1 ${mien ? 'items-end' : 'items-start'}`}>
              <span className="text-[12.5px] text-muted">{nom(message.auteur)} · {depuis(message.cree_le, maintenant)}</span>
              <span className={`max-w-[78%] rounded-[14px] px-3.5 py-2.5 ${mien ? 'bg-accent text-canvas' : 'bg-page text-ink'}`}>{message.texte}</span>
            </li>
          )
        })}
      </ol>

      {enCours ? (
        <form onSubmit={(e) => void envoyer(e)} className="flex flex-col gap-1.5 border-t border-[#EEF2F9] px-6 py-4">
          <div className="flex gap-2.5">
            <label className="sr-only" htmlFor="message-mission">Votre message</label>
            <input id="message-mission" value={texte} onChange={(e) => setTexte(e.target.value)} placeholder="Écrire un message…" className="min-h-cible min-w-0 flex-1 rounded-controle border border-line px-3" />
            <button type="submit" className="min-h-cible rounded-controle bg-accent px-5 font-semibold text-canvas">Envoyer</button>
          </div>
          {erreur ? <p role="alert" className="m-0 text-sm text-danger">{erreur}</p> : null}
        </form>
      ) : (
        <div className="flex flex-wrap items-center gap-2 rounded-b-carte bg-[#F0FDF4] px-6 py-4">
          <span aria-hidden="true" className="grid size-7 place-items-center rounded-full bg-online text-canvas">✓</span>
          <strong className="text-success">Mission terminée</strong>
          <span className="text-sm text-subtle">La discussion est fermée.</span>
        </div>
      )}

      {noterOuvert ? <Noter mission={mission} onFerme={() => setNoterOuvert(false)} /> : null}
    </section>
  )
}

function Noter({ mission, onFerme }: { mission: Mission; onFerme: () => void }) {
  const cache = useQueryClient()
  const [note, setNote] = useState(0)
  const [commentaire, setCommentaire] = useState('')
  const [erreur, setErreur] = useState<string | null>(null)

  async function envoyer(evenement: FormEvent) {
    evenement.preventDefault()
    if (note < 1) {
      setErreur('Choisissez une note de 1 à 5.')
      return
    }
    try {
      await noterMission(mission.id, note, commentaire.trim())
      await cache.invalidateQueries({ queryKey: ['missions'] })
      onFerme()
    } catch (erreurHttp) {
      setErreur(messageAction(erreurHttp, 'La note n’a pas été enregistrée.'))
    }
  }

  return (
    <Modal titre="Terminer et noter la mission" onFermer={onFerme}>
      <form onSubmit={(e) => void envoyer(e)} className="flex flex-col gap-4">
        <p className="m-0 text-subtle">Comment s’est passée la mission avec <strong className="text-ink">{nom(mission.auteur)}</strong> ?</p>
        <fieldset className="m-0 border-0 p-0">
          <legend className="mb-2 font-semibold">Votre note <span className="font-normal text-muted">(obligatoire)</span></legend>
          <div className="flex flex-wrap items-center gap-1">
            {[1, 2, 3, 4, 5].map((valeur) => (
              <button key={valeur} type="button" aria-label={`${valeur} sur 5`} aria-pressed={note === valeur} onClick={() => setNote(valeur)} className="grid size-11 place-items-center rounded-controle bg-page text-xl text-[#F59E0B]">
                {valeur <= note ? '★' : '☆'}
              </button>
            ))}
          </div>
        </fieldset>
        <label className="font-semibold">Commentaire <span className="font-normal text-muted">(facultatif)</span>
          <textarea value={commentaire} onChange={(e) => setCommentaire(e.target.value)} rows={3} className="mt-1.5 w-full rounded-controle border border-line p-3 font-normal" placeholder="Ce qui vous a plu, ce qui peut être amélioré…" />
        </label>
        <p className="m-0 rounded-controle border border-[#FDE68A] bg-[#FEF9E7] px-3 py-2.5 text-sm text-warning">Une seule fois. La note compte dans la réputation et le Souk Score de l’auteur.</p>
        {erreur ? <p role="alert" className="m-0 text-sm text-danger">{erreur}</p> : null}
        <div className="flex justify-end gap-2.5">
          <button type="button" onClick={onFerme} className="min-h-cible rounded-controle border border-line px-[18px] font-semibold">Annuler</button>
          <button type="submit" className="min-h-cible rounded-controle bg-accent px-5 font-semibold text-canvas">Terminer la mission</button>
        </div>
      </form>
    </Modal>
  )
}

function Puces({ mission, moiId }: { mission: Mission; moiId: number | undefined }) {
  const client = moiId === mission.client.id
  const directe = mission.app_id == null
  const terminee = mission.statut === 'terminee'
  return (
    <span className="flex flex-wrap gap-1.5">
      <span className={`rounded-full px-2 text-xs font-bold ${terminee ? 'bg-success-bg text-success' : 'bg-[#E0F2FE] text-[#075985]'}`}>{terminee ? 'Terminée' : 'En cours'}</span>
      <span className={`rounded-full px-2 text-xs font-bold ${client ? 'bg-[#FFE4DE] text-[#C2410C]' : 'bg-nav text-accent'}`}>{client ? 'Vous êtes le client' : directe ? 'Vous êtes le dev' : 'Vous êtes l’auteur'}</span>
      <span className={`rounded-full px-2 text-xs font-bold ${directe ? 'bg-[#CCFBF1] text-[#115E59]' : 'bg-chip text-nav-texte'}`}>{directe ? 'Recrutement direct' : 'Personnalisation'}</span>
    </span>
  )
}

function NoteBloc({ note, commentaire, mienne }: { note: number; commentaire: string | null; mienne: boolean }) {
  return (
    <div className="flex flex-col gap-1 rounded-xl border border-line bg-[#F8FAFD] px-3.5 py-3">
      <div className="flex flex-wrap items-center gap-2">
        <span className="text-[13px] font-bold text-subtle">{mienne ? 'Votre note' : 'Note du client'}</span>
        <span aria-label={`${note} étoiles sur 5`} className="text-[#F59E0B]">{'★'.repeat(note)}{'☆'.repeat(5 - note)}</span>
        <strong className="tabular-nums">{note} / 5</strong>
      </div>
      {commentaire ? <p className="m-0 text-sm text-nav-texte">« {commentaire} »</p> : null}
    </div>
  )
}

function Meta({ cle, valeur }: { cle: string; valeur: string }) {
  return (
    <div className="flex gap-1.5">
      <dt className="text-muted">{cle}</dt>
      <dd className="m-0 font-semibold">{valeur}</dd>
    </div>
  )
}

function Choix({ to, titre, texte }: { to: string; titre: string; texte: string }) {
  return (
    <Link to={to} className="flex items-center gap-3.5 rounded-xl border border-line p-4 text-ink no-underline hover:bg-page">
      <span className="flex min-w-0 flex-1 flex-col leading-snug">
        <strong>{titre}</strong>
        <span className="text-[13.5px] text-muted">{texte}</span>
      </span>
      <span aria-hidden="true" className="text-muted">›</span>
    </Link>
  )
}

function titreMission(mission: Mission) {
  return mission.app_id == null ? 'Recrutement direct' : `Appli ${mission.app_id}`
}

function nom(personne: { nom: string | null; username: string | null }) {
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
