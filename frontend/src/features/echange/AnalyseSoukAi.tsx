import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { LoaderCircle } from 'lucide-react'
import { Alerte, Bouton, CaseACocher, Carte, Modale } from '../../ui'
import { messageErreur } from '../paiements/api'
import { demanderAnalyse, type AnalyseIa, type Discussion } from './api'
import { TexteMarkdown } from './TexteMarkdown'

/**
 * « Analyse IA » à côté des correctifs : l'auteur d'un bug demande l'avis de Souk AI (DeepSeek)
 * après avoir accepté que sa fiche parte chez le fournisseur ; le résultat arrive en direct,
 * visible de tous. Une piste, jamais une résolution.
 */
export function AnalyseSoukAi({ discussionId, analyse, auteur }: { discussionId: number; analyse: AnalyseIa | null; auteur: boolean }) {
  const cache = useQueryClient()
  const [ouverte, setOuverte] = useState(false)
  const [accord, setAccord] = useState(false)
  const [erreur, setErreur] = useState<string | null>(null)

  const demande = useMutation({
    mutationFn: () => demanderAnalyse(discussionId),
    onSuccess: (recue) => {
      cache.setQueryData<Discussion>(['discussion', discussionId], (actuelle) => (actuelle ? { ...actuelle, analyse_ia: recue } : actuelle))
      setOuverte(false)
      setAccord(false)
      setErreur(null)
    },
    onError: (probleme) => setErreur(messageErreur(probleme, 'L’analyse n’a pas pu être demandée. Réessayez dans un instant.')),
  })

  function fermer() {
    setOuverte(false)
    setAccord(false)
  }

  return (
    <Carte as="section" aria-labelledby="titre-ia" className="mt-2 flex flex-col gap-3">
      <div className="flex flex-col gap-0.5">
        <h2 id="titre-ia" className="m-0 flex items-center gap-2 text-corps-l font-semibold text-titre">
          <span aria-hidden="true">🤖</span> Analyse IA
        </h2>
        <p className="m-0 text-petit text-texte-doux">Souk AI propose une piste, pas un correctif : la communauté et l’auteur décident.</p>
      </div>

      {!analyse && auteur ? (
        <>
          <p className="m-0 text-corps text-texte">Souk AI lit la fiche du bug et propose une cause probable, une correction et une façon de vérifier.</p>
          <Bouton className="self-start" onClick={() => setOuverte(true)}>
            Demander l’avis de Souk AI
          </Bouton>
        </>
      ) : null}
      {!analyse && !auteur ? <p className="m-0 text-petit text-texte-doux">Pas encore d’analyse : seul l’auteur du bug peut la demander.</p> : null}

      {analyse?.statut === 'en_cours' ? (
        <p role="status" className="m-0 flex items-center gap-2 rounded-moyen bg-fond-ia p-3 text-petit text-ia">
          <LoaderCircle aria-hidden="true" className="size-4 shrink-0 animate-tourne" />
          Analyse en cours. La réponse arrive ici, sans recharger la page.
        </p>
      ) : null}
      {analyse?.statut === 'prete' && analyse.texte ? <TexteMarkdown texte={analyse.texte} /> : null}
      {analyse?.statut === 'echec' ? <Alerte ton="alerte">Analyse indisponible. Les réponses de la communauté restent là.</Alerte> : null}

      {auteur && analyse && analyse.statut !== 'en_cours' ? (
        <Bouton variante="contour" taille="sm" className="self-start" onClick={() => setOuverte(true)}>
          {analyse.statut === 'echec' ? 'Réessayer l’analyse' : 'Relancer l’analyse'}
        </Bouton>
      ) : null}
      {auteur ? <p className="m-0 text-micro text-texte-doux">3 analyses par 24 heures ; une analyse qui échoue ne compte pas.</p> : null}
      {erreur && !ouverte ? (
        <Alerte ton="danger" urgente>
          {erreur}
        </Alerte>
      ) : null}

      <Modale
        ouverte={ouverte}
        onFermer={fermer}
        titre="Demander l’avis de Souk AI"
        pied={
          <>
            <Bouton variante="fantome" onClick={fermer}>
              Annuler
            </Bouton>
            <Bouton disabled={!accord} chargement={demande.isPending} onClick={() => demande.mutate()}>
              Lancer l’analyse
            </Bouton>
          </>
        }
      >
        <div className="flex flex-col gap-3">
          <p className="m-0 text-corps text-texte">
            La fiche du bug (titre, description, erreur, comportement attendu et code) part chez DeepSeek, un fournisseur extérieur, pour être analysée. Les clés
            repérées dans le code sont masquées avant l’envoi.
          </p>
          <p className="m-0 text-corps text-texte">L’analyse sera visible de tous, à côté des correctifs. Elle ne résout pas la discussion.</p>
          <CaseACocher libelle="J’accepte que ma fiche et mon code partent chez DeepSeek" checked={accord} onChange={(evenement) => setAccord(evenement.target.checked)} />
          {erreur ? (
            <Alerte ton="danger" urgente>
              {erreur}
            </Alerte>
          ) : null}
        </div>
      </Modale>
    </Carte>
  )
}
