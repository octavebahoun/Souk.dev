import { useId, useState, type FormEvent } from 'react'
import { useInfiniteQuery, useQueryClient } from '@tanstack/react-query'
import { Star } from 'lucide-react'
import { listerAvisAppli, noterAppli, type AvisAppli, type PageAvis } from './api'
import { erreursDeChamps, messageErreur } from '../paiements/api'
import { Alerte, Avatar, Bouton, Carte, Champ, Etoiles, SqueletteLignes, ZoneTexte, cx, formaterDate, formaterNote, useToast } from '../../ui'

const libellesNote = ['', 'Décevante', 'Moyenne', 'Correcte', 'Très bien', 'Excellente']

/**
 * Avis des clients sur la fiche, publics. Seul un client qui a eu une copie payée de l'appli la
 * note, une fois ; il peut ensuite modifier son avis. La moyenne vient de l'appli (tri du Store).
 */
export function AvisClients({ appId, noteMoyenne, nbAvis }: { appId: number; noteMoyenne: number | null; nbAvis: number }) {
  const avis = useInfiniteQuery({
    queryKey: ['avis-appli', appId],
    queryFn: ({ pageParam }) => listerAvisAppli(appId, pageParam),
    initialPageParam: 1,
    getNextPageParam: (derniere: PageAvis) => (derniere.meta.current_page < derniere.meta.last_page ? derniere.meta.current_page + 1 : undefined),
  })
  const [edition, setEdition] = useState(false)
  const premiere = avis.data?.pages[0]
  const monAvis = premiere?.mon_avis ?? null
  const autres = (avis.data?.pages.flatMap((page) => page.data) ?? []).filter((un) => un.id !== monAvis?.id)

  return (
    <Carte as="section" aria-labelledby="titre-avis" className="flex flex-col gap-5">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div className="flex flex-col gap-0.5">
          <h2 id="titre-avis" className="m-0 text-titre-3 text-titre">
            Avis des clients
          </h2>
          <p className="m-0 text-petit text-texte-doux">Seuls les clients qui ont eu une copie payée de l’appli peuvent la noter.</p>
        </div>
        {noteMoyenne !== null ? (
          <p className="m-0 flex items-center gap-2.5">
            <span className="pour-lecteur">
              Note moyenne : {formaterNote(noteMoyenne)} sur 5, {nbAvis} avis
            </span>
            <span aria-hidden="true" className="chiffres text-titre-2 font-bold text-titre">
              {formaterNote(noteMoyenne)}
            </span>
            <span aria-hidden="true" className="flex flex-col gap-0.5">
              <Etoiles note={noteMoyenne} />
              <span className="text-petit text-texte-doux">{nbAvis} avis</span>
            </span>
          </p>
        ) : null}
      </div>

      {avis.isPending ? <SqueletteLignes lignes={3} /> : null}
      {avis.isError ? (
        <Alerte
          ton="danger"
          action={
            <Bouton variante="contour" taille="sm" onClick={() => void avis.refetch()}>
              Réessayer
            </Bouton>
          }
        >
          {messageErreur(avis.error, 'Les avis n’ont pas pu être chargés.')}
        </Alerte>
      ) : null}

      {premiere?.peut_noter ? <FormulaireAvis appId={appId} initial={null} onFini={() => undefined} /> : null}
      {monAvis && edition ? <FormulaireAvis appId={appId} initial={monAvis} onFini={() => setEdition(false)} onAnnuler={() => setEdition(false)} /> : null}
      {monAvis && !edition ? (
        <div className="flex flex-col gap-2 rounded-moyen border border-bordure bg-surface-douce p-4">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <h3 className="m-0 text-corps font-semibold text-titre">Votre avis</h3>
            <Bouton variante="contour" taille="sm" onClick={() => setEdition(true)}>
              Modifier
            </Bouton>
          </div>
          <Etoiles note={monAvis.note} />
          {monAvis.commentaire ? <p className="m-0 whitespace-pre-line text-texte">{monAvis.commentaire}</p> : null}
        </div>
      ) : null}

      {avis.isSuccess && autres.length === 0 && !monAvis ? <p className="m-0 text-texte-doux">Pas encore d’avis sur cette appli.</p> : null}
      {autres.length > 0 ? (
        <ul aria-label="Avis" className="m-0 flex list-none flex-col gap-4 p-0">
          {autres.map((un) => (
            <LigneAvis key={un.id} avis={un} />
          ))}
        </ul>
      ) : null}
      {avis.hasNextPage ? (
        <Bouton variante="contour" className="self-start" chargement={avis.isFetchingNextPage} onClick={() => void avis.fetchNextPage()}>
          Voir plus d’avis
        </Bouton>
      ) : null}
    </Carte>
  )
}

function LigneAvis({ avis }: { avis: AvisAppli }) {
  const nom = avis.auteur.nom || avis.auteur.username || 'Un client'
  return (
    <li className="flex gap-3 border-t border-bordure pt-4 first:border-t-0 first:pt-0">
      <Avatar nom={avis.auteur.nom} secours={avis.auteur.username} url={avis.auteur.avatar_url} />
      <div className="flex min-w-0 flex-1 flex-col gap-1">
        <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
          <span className="font-semibold text-titre">{nom}</span>
          <Etoiles note={avis.note} taille="sm" />
          <time dateTime={avis.cree_le} className="text-petit text-texte-doux">
            {formaterDate(avis.cree_le)}
            {avis.modifie_le !== avis.cree_le ? ' · modifié' : ''}
          </time>
        </div>
        {avis.commentaire ? <p className="m-0 whitespace-pre-line text-texte">{avis.commentaire}</p> : null}
      </div>
    </li>
  )
}

function FormulaireAvis({ appId, initial, onFini, onAnnuler }: { appId: number; initial: AvisAppli | null; onFini: () => void; onAnnuler?: () => void }) {
  const cache = useQueryClient()
  const toast = useToast()
  const id = useId()
  const [note, setNote] = useState(initial?.note ?? 0)
  const [commentaire, setCommentaire] = useState(initial?.commentaire ?? '')
  const [envoi, setEnvoi] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)
  const [erreurs, setErreurs] = useState<Record<string, string>>({})

  async function envoyer(evenement: FormEvent<HTMLFormElement>) {
    evenement.preventDefault()
    if (note === 0) {
      setErreurs({ note: 'Choisissez une note, de 1 à 5 étoiles.' })
      return
    }
    setEnvoi(true)
    setErreur(null)
    setErreurs({})
    try {
      await noterAppli(appId, { note, commentaire: commentaire.trim() || null }, initial !== null)
      await Promise.all([
        cache.invalidateQueries({ queryKey: ['avis-appli', appId] }),
        cache.invalidateQueries({ queryKey: ['appli', appId] }),
      ])
      toast.succes(initial ? 'Votre avis est modifié.' : 'Merci, votre avis est publié.')
      onFini()
    } catch (probleme) {
      const parChamp = erreursDeChamps(probleme)
      setErreurs(parChamp)
      if (Object.keys(parChamp).length === 0) setErreur(messageErreur(probleme, 'Votre avis n’a pas pu être enregistré. Réessayez dans un instant.'))
    } finally {
      setEnvoi(false)
    }
  }

  return (
    <form onSubmit={(evenement) => void envoyer(evenement)} noValidate className="flex flex-col gap-4 rounded-moyen border border-bordure p-4">
      <h3 className="m-0 text-corps font-semibold text-titre">{initial ? 'Modifier votre avis' : 'Noter cette appli'}</h3>
      <fieldset aria-describedby={erreurs.note ? `${id}-erreur` : undefined} className="m-0 flex flex-col gap-1.5 border-0 p-0">
        <legend className="mb-1.5 p-0 text-petit font-medium text-titre">Votre note</legend>
        <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
          <div className="flex">
            {[1, 2, 3, 4, 5].map((valeur) => (
              <label
                key={valeur}
                className="relative grid size-11 cursor-pointer place-items-center rounded-petit has-[:focus-visible]:outline-3 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-lien"
              >
                <input type="radio" name={`${id}-note`} value={valeur} checked={note === valeur} onChange={() => {
                    setNote(valeur)
                    setErreurs((actuelles) => Object.fromEntries(Object.entries(actuelles).filter(([champ]) => champ !== 'note')))
                  }} className="pour-lecteur" />
                <Star aria-hidden="true" className={cx('size-7 transition-colors duration-150', valeur <= note ? 'fill-etoile text-etoile' : 'text-bordure-champ')} />
                <span className="pour-lecteur">
                  {valeur === 1 ? '1 étoile' : `${valeur} étoiles`}, {libellesNote[valeur].toLowerCase()}
                </span>
              </label>
            ))}
          </div>
          <span aria-hidden="true" className="text-petit font-medium text-texte">
            {note > 0 ? `${note} sur 5 · ${libellesNote[note]}` : 'De 1 à 5 étoiles'}
          </span>
        </div>
        {erreurs.note ? (
          <p id={`${id}-erreur`} className="m-0 text-petit text-danger">
            {erreurs.note}
          </p>
        ) : null}
      </fieldset>
      <Champ libelle="Votre avis" facultatif aide={`Ce que l’appli a changé pour vous, ce qui manque. ${commentaire.length} sur 1 000 caractères.`} erreur={erreurs.commentaire}>
        {(liaison) => <ZoneTexte {...liaison} value={commentaire} onChange={(evenement) => setCommentaire(evenement.target.value)} maxLength={1000} rows={3} />}
      </Champ>
      {erreur ? <Alerte ton="danger">{erreur}</Alerte> : null}
      <div className="flex flex-wrap gap-2">
        <Bouton type="submit" chargement={envoi}>
          {initial ? 'Enregistrer' : 'Publier mon avis'}
        </Bouton>
        {onAnnuler ? (
          <Bouton variante="fantome" onClick={onAnnuler}>
            Annuler
          </Bouton>
        ) : null}
      </div>
    </form>
  )
}
