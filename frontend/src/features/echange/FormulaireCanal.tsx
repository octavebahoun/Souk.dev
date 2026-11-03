import { useState, type FormEvent } from 'react'
import { useMutation } from '@tanstack/react-query'
import { creerCanal, lireErreurHttp, messageAction, type Canal } from './api'
import { nomPays } from './texte'
import { Alerte, Bouton, Champ, Liste, Saisie, ZoneTexte } from '../../ui'

const pays = ['BJ', 'SN', 'CI', 'TG', 'CM', 'BF', 'ML', 'NE', 'GN', 'GA', 'CG', 'CD', 'NG', 'GH', 'MA', 'TN']

/** Ouvrir un canal : un nom, une description facultative, un pays pour un canal local. */
export function FormulaireCanal({ onAnnuler, onCree }: { onAnnuler: () => void; onCree: (canal: Canal) => void }) {
  const [nom, setNom] = useState('')
  const [description, setDescription] = useState('')
  const [paysChoisi, setPays] = useState('')
  const [champs, setChamps] = useState<Record<string, string>>({})

  const creation = useMutation({
    mutationFn: creerCanal,
    onSuccess: onCree,
    onError: (erreur) => setChamps(lireErreurHttp(erreur).champs),
  })

  function envoyer(evenement: FormEvent) {
    evenement.preventDefault()
    if (nom.trim() === '') {
      setChamps({ nom: 'Donnez un nom au canal.' })
      return
    }
    creation.mutate({ nom: nom.trim(), ...(description.trim() ? { description: description.trim() } : {}), ...(paysChoisi ? { pays: paysChoisi } : {}) })
  }

  const erreurGenerale = creation.isError && Object.keys(champs).length === 0 ? messageAction(creation.error, 'Le canal n’a pas pu être créé. Réessayez dans un instant.') : null

  return (
    <form onSubmit={envoyer} noValidate className="flex flex-col gap-4">
      {erreurGenerale ? <Alerte ton="danger">{erreurGenerale}</Alerte> : null}
      <Champ libelle="Nom" aide="Le sujet du canal, par exemple « Flutter » ou « Paiements Mobile Money » (50 caractères au plus)." erreur={champs.nom}>
        {(liaison) => <Saisie {...liaison} value={nom} maxLength={50} onChange={(evenement) => setNom(evenement.target.value)} />}
      </Champ>
      <Champ libelle="Description" facultatif erreur={champs.description}>
        {(liaison) => <ZoneTexte {...liaison} rows={3} maxLength={300} value={description} onChange={(evenement) => setDescription(evenement.target.value)} />}
      </Champ>
      <Champ libelle="Pays" facultatif aide="Pour un canal local (les devs d’un même pays) ; sinon, le canal est général." erreur={champs.pays}>
        {(liaison) => (
          <Liste {...liaison} value={paysChoisi} onChange={(evenement) => setPays(evenement.target.value)}>
            <option value="">Canal général</option>
            {pays.map((code) => (
              <option key={code} value={code}>
                {nomPays(code)}
              </option>
            ))}
          </Liste>
        )}
      </Champ>
      <div className="flex flex-col-reverse gap-2 tablette:flex-row tablette:justify-end">
        <Bouton variante="contour" onClick={onAnnuler}>
          Annuler
        </Bouton>
        <Bouton type="submit" chargement={creation.isPending}>
          Créer le canal
        </Bouton>
      </div>
    </form>
  )
}
