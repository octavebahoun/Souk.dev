import { useState, type FormEvent } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { Alerte, Badge, Bouton, Carte, Champ, Saisie, SqueletteLignes, ZoneTexte, formaterDate } from '../../ui'
import { erreursDeChamps, messageErreur } from '../paiements/api'
import { listerVersions, publierVersion, type VersionAppli } from './api'

const statuts: Record<VersionAppli['statut'], { libelle: string; ton: 'info' | 'danger' } | null> = {
  publiee: null,
  verification: { libelle: 'En vérification', ton: 'info' },
  refusee: { libelle: 'Refusée', ton: 'danger' },
}

/**
 * Le journal des versions sur la fiche, public. L'auteur y publie une nouvelle version : elle
 * est vérifiée comme une publication, puis proposée à ses clients, qui l'appliquent quand ils veulent.
 */
export function VersionsAppli({ appId, estAuteur }: { appId: number; estAuteur: boolean }) {
  const cache = useQueryClient()
  const versions = useQuery({
    queryKey: ['versions', appId],
    queryFn: () => listerVersions(appId),
    // Une version en vérification se relit jusqu'à son résultat.
    refetchInterval: (requete) => (requete.state.data?.data.some((version) => version.statut === 'verification') ? 5000 : false),
  })
  const [ref, setRef] = useState('')
  const [notes, setNotes] = useState('')
  const [envoi, setEnvoi] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)
  const [erreurs, setErreurs] = useState<Record<string, string>>({})

  async function publier(evenement: FormEvent<HTMLFormElement>) {
    evenement.preventDefault()
    setEnvoi(true)
    setErreur(null)
    setErreurs({})
    try {
      await publierVersion(appId, { notes: notes.trim(), ...(ref.trim() ? { ref: ref.trim() } : {}) })
      setRef('')
      setNotes('')
      await Promise.all([cache.invalidateQueries({ queryKey: ['versions', appId] }), cache.invalidateQueries({ queryKey: ['appli', appId] })])
    } catch (probleme) {
      const parChamp = erreursDeChamps(probleme)
      setErreurs(parChamp)
      if (Object.keys(parChamp).length === 0) setErreur(messageErreur(probleme, 'La version n’a pas pu être envoyée. Réessayez dans un instant.'))
    } finally {
      setEnvoi(false)
    }
  }

  const liste = versions.data?.data ?? []

  return (
    <Carte as="section" aria-labelledby="titre-versions" className="flex flex-col gap-4">
      <div className="flex flex-col gap-0.5">
        <h2 id="titre-versions" className="m-0 text-titre-3 text-titre">
          Versions
        </h2>
        <p className="m-0 text-petit text-texte-doux">Chaque client choisit quand appliquer une nouvelle version ; ses données sont sauvegardées avant.</p>
      </div>

      {versions.isPending ? <SqueletteLignes lignes={2} /> : null}
      {liste.length > 0 ? (
        <ol className="m-0 flex list-none flex-col gap-3 p-0">
          {liste.map((version) => {
            const statut = statuts[version.statut]
            return (
              <li key={version.id} className="flex flex-col gap-1 border-t border-bordure pt-3 first:border-t-0 first:pt-0">
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                  <span className="font-semibold text-titre">Version {version.numero}</span>
                  <time dateTime={version.cree_le} className="text-petit text-texte-doux">
                    {formaterDate(version.cree_le)}
                  </time>
                  {statut ? <Badge ton={statut.ton}>{statut.libelle}</Badge> : null}
                </div>
                <p className="m-0 whitespace-pre-line text-texte">{version.notes ?? (version.numero === 1 ? 'Publication de l’appli.' : '')}</p>
                {version.statut === 'refusee' && version.erreur ? <p className="m-0 text-petit text-danger">{version.erreur}</p> : null}
              </li>
            )
          })}
        </ol>
      ) : null}

      {estAuteur ? (
        <form onSubmit={(evenement) => void publier(evenement)} noValidate className="flex flex-col gap-3 rounded-moyen border border-bordure p-4">
          <h3 className="m-0 text-corps font-semibold text-titre">Publier une nouvelle version</h3>
          <Champ libelle="Ce qui change pour vos clients" erreur={erreurs.notes}>
            {(liaison) => <ZoneTexte {...liaison} value={notes} onChange={(evenement) => setNotes(evenement.target.value)} maxLength={2000} rows={3} required />}
          </Champ>
          <Champ libelle="Branche, tag ou commit" facultatif aide="Par défaut, la branche publiée. Le code est vérifié comme à la publication (soukdev.json, docker-compose.yml, secrets)." erreur={erreurs.ref}>
            {(liaison) => <Saisie {...liaison} value={ref} onChange={(evenement) => setRef(evenement.target.value)} maxLength={200} spellCheck={false} autoComplete="off" />}
          </Champ>
          {erreur ? (
            <Alerte ton="danger" urgente>
              {erreur}
            </Alerte>
          ) : null}
          <Bouton type="submit" className="self-start" chargement={envoi}>
            Publier la version
          </Bouton>
        </form>
      ) : null}
    </Carte>
  )
}
