import { useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { Flag } from 'lucide-react'
import { Bouton, Champ, Liste, Modale, ZoneTexte, useToast } from '../../ui'
import { erreursDeChamps, messageErreur } from '../paiements/api'
import { signaler, type CibleSignalement, type MotifSignalement } from './api'
import { libellesMotifs } from './presentationModeration'

const motifs: MotifSignalement[] = ['spam', 'abus', 'hameconnage', 'minage', 'contenu_illegal', 'autre']

/**
 * Bouton « Signaler » d'une discussion, d'un message, d'une appli ou d'un événement : le
 * motif et des faits vérifiables partent à l'équipe. Discret (fantôme), jamais l'action
 * principale. Les copies et les comptes se signalent par l'API (voir docs/moderation.md).
 */
export function Signaler({ cible, cibleId, etiquette }: { cible: CibleSignalement; cibleId: number; etiquette: string }) {
  const toast = useToast()
  const [ouvert, setOuvert] = useState(false)
  const [motif, setMotif] = useState<MotifSignalement>('spam')
  const [details, setDetails] = useState('')
  const [erreurs, setErreurs] = useState<Record<string, string>>({})

  const mutation = useMutation({
    mutationFn: () => signaler({ cible, cible_id: cibleId, motif, details: details || undefined }),
    onSuccess: () => {
      toast.succes('Signalement envoyé à l’équipe. Merci.')
      setDetails('')
      setErreurs({})
      setOuvert(false)
    },
    onError: (probleme) => {
      setErreurs(erreursDeChamps(probleme))
      toast.erreur(messageErreur(probleme, 'Le signalement n’a pas pu être envoyé.'))
    },
  })

  return (
    <>
      <Bouton variante="fantome" taille="sm" icone={<Flag aria-hidden="true" className="size-4" />} onClick={() => setOuvert(true)}>
        Signaler
      </Bouton>
      <Modale
        ouverte={ouvert}
        onFermer={() => {
          setDetails('')
          setErreurs({})
          setOuvert(false)
        }}
        titre={`Signaler ${etiquette}`}
        description="L’équipe examine chaque signalement. Un signalement abusif peut valoir une suspension."
        pied={
          <>
            <Bouton variante="secondaire" onClick={() => setOuvert(false)}>
              Annuler
            </Bouton>
            <Bouton onClick={() => mutation.mutate()} chargement={mutation.isPending}>
              Envoyer
            </Bouton>
          </>
        }
      >
        <div className="flex flex-col gap-4">
          <Champ libelle="Motif" erreur={erreurs.motif}>
            {(liaison) => (
              <Liste {...liaison} value={motif} onChange={(evenement) => setMotif(evenement.target.value as MotifSignalement)}>
                {motifs.map((choix) => (
                  <option key={choix} value={choix}>
                    {libellesMotifs[choix]}
                  </option>
                ))}
              </Liste>
            )}
          </Champ>
          <Champ libelle="Détails" aide="Ce qui pose problème, avec des faits vérifiables." erreur={erreurs.details} facultatif>
            {(liaison) => <ZoneTexte {...liaison} rows={3} maxLength={2000} value={details} onChange={(evenement) => setDetails(evenement.target.value)} />}
          </Champ>
        </div>
      </Modale>
    </>
  )
}
