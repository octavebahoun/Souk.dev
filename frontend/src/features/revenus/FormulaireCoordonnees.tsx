import { useState, type FormEvent } from 'react'
import { Alerte, Bouton, Champ, ChoixCartes, Liste, Saisie } from '../../ui'
import { enregistrerCoordonnees, erreursDeChamps, messageErreur, type ReseauMobileMoney } from '../paiements/api'
import { LogoReseau } from '../paiements/LogoReseau'
import { nomsPays } from '../paiements/reseaux'

type FormulaireCoordonneesProps = {
  reseaux: ReseauMobileMoney[]
  paysParDefaut: string
  email: string | null
  onEnregistre: () => void
  onAnnuler?: () => void
}

/**
 * Numéro Mobile Money de reversement : pays, réseau (logos), numéro saisi deux fois. Le numéro
 * ne sert qu'après le clic sur le lien de confirmation envoyé par e-mail.
 */
export function FormulaireCoordonnees({ reseaux, paysParDefaut, email, onEnregistre, onAnnuler }: FormulaireCoordonneesProps) {
  const paysDisponibles = [...new Set(reseaux.map((reseau) => reseau.pays))]
  const [pays, setPays] = useState(paysDisponibles.includes(paysParDefaut) ? paysParDefaut : (paysDisponibles[0] ?? 'BJ'))
  const [reseau, setReseau] = useState<string | null>(null)
  const [numero, setNumero] = useState('')
  const [numeroConfirmation, setNumeroConfirmation] = useState('')
  const [erreurs, setErreurs] = useState<Record<string, string>>({})
  const [erreur, setErreur] = useState<string | null>(null)
  const [envoye, setEnvoye] = useState(false)
  const [envoi, setEnvoi] = useState(false)

  const reseauxDuPays = reseaux.filter((element) => element.pays === pays)
  const differents = numeroConfirmation !== '' && numeroConfirmation.replace(/\D/g, '') !== numero.replace(/\D/g, '')
  const complet = reseau !== null && numero.trim() !== '' && numeroConfirmation.trim() !== '' && !differents

  async function enregistrer(evenement: FormEvent) {
    evenement.preventDefault()
    if (!complet || reseau === null) return
    setEnvoi(true)
    setErreur(null)
    setErreurs({})
    try {
      await enregistrerCoordonnees({ pays, reseau, numero, numero_confirmation: numeroConfirmation })
      setEnvoye(true)
      onEnregistre()
    } catch (probleme) {
      setErreurs(erreursDeChamps(probleme))
      setErreur(messageErreur(probleme, 'Le numéro n’a pas pu être enregistré. Réessayez dans un instant.'))
    } finally {
      setEnvoi(false)
    }
  }

  if (envoye) {
    return (
      <Alerte ton="succes" titre="Plus qu’une étape : confirmez ce numéro">
        Un e-mail vient de partir{email ? ` à ${email}` : ''}. Ouvrez le lien et cliquez sur « Confirmer ce numéro » : vos revenus pourront alors partir dessus.
      </Alerte>
    )
  }

  return (
    <form onSubmit={(evenement) => void enregistrer(evenement)} noValidate className="flex flex-col gap-5">
      <Champ libelle="Pays" erreur={erreurs.pays}>
        {(liaison) => (
          <Liste
            {...liaison}
            value={pays}
            onChange={(evenement) => {
              setPays(evenement.target.value)
              setReseau(null)
            }}
            className="tablette:max-w-xs"
          >
            {paysDisponibles.map((code) => (
              <option key={code} value={code}>
                {nomsPays[code] ?? code}
              </option>
            ))}
          </Liste>
        )}
      </Champ>

      <ChoixCartes
        nom="reseau"
        legende="Réseau"
        valeur={reseau}
        onChange={setReseau}
        colonnes={3}
        erreur={erreurs.reseau}
        options={reseauxDuPays.map((element) => ({
          valeur: element.code,
          libelle: element.nom,
          complement: <LogoReseau code={element.code} nom={element.nom} taille="petit" />,
        }))}
      />

      <div className="grid gap-4 tablette:grid-cols-2">
        <Champ libelle="Numéro Mobile Money" aide="Sans l’indicatif du pays, comme sur votre téléphone." erreur={erreurs.numero}>
          {(liaison) => <Saisie {...liaison} type="tel" inputMode="tel" autoComplete="tel-national" value={numero} onChange={(evenement) => setNumero(evenement.target.value)} />}
        </Champ>
        <Champ libelle="Numéro, une seconde fois" erreur={differents ? 'Les deux numéros ne correspondent pas.' : erreurs.numero_confirmation}>
          {(liaison) => <Saisie {...liaison} type="tel" inputMode="tel" autoComplete="off" value={numeroConfirmation} onChange={(evenement) => setNumeroConfirmation(evenement.target.value)} />}
        </Champ>
      </div>

      {erreur && Object.keys(erreurs).length === 0 ? (
        <Alerte ton="danger" urgente>
          {erreur}
        </Alerte>
      ) : null}

      <div className="flex flex-col gap-3 tablette:flex-row tablette:items-center">
        <Bouton type="submit" chargement={envoi} disabled={!complet}>
          Enregistrer et recevoir le lien
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
