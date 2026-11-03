import { useState, type FormEvent } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTitrePage } from '../../app/titre'
import type { components } from '../../shared/types/api'
import { lireErreurHttp, lireMoi, messageAction } from '../echange/api'
import { nomPays } from '../echange/texte'
import { modifierProfil, type Compte } from './api'
import {
  AccesRefuse,
  Bouton,
  Carte,
  Champ,
  EnTetePage,
  EtatErreur,
  Interrupteur,
  LienBouton,
  Liste,
  PuceFiltre,
  SqueletteLignes,
  ZoneTexte,
  useToast,
} from '../../ui'

type Techno = components['schemas']['Techno']
type Specialite = components['schemas']['Specialite']

const technos: Techno[] = ['laravel', 'symfony', 'php', 'node', 'express', 'nestjs', 'nextjs', 'react', 'vue', 'angular', 'svelte', 'django', 'flask', 'fastapi', 'spring', 'dotnet', 'go', 'postgresql', 'mysql', 'mongodb', 'redis', 'autre']
const specialites: Array<{ valeur: Specialite; libelle: string }> = [
  { valeur: 'frontend', libelle: 'Frontend' },
  { valeur: 'backend', libelle: 'Backend' },
  { valeur: 'mobile', libelle: 'Mobile' },
  { valeur: 'devops', libelle: 'DevOps' },
  { valeur: 'securite', libelle: 'Sécurité' },
  { valeur: 'data', libelle: 'Data' },
]
/** Pays de la communauté d'abord ; l'API accepte tous les codes ISO. */
const pays = ['BJ', 'SN', 'CI', 'TG', 'CM', 'BF', 'ML', 'NE', 'GN', 'GA', 'CG', 'CD', 'NG', 'GH', 'MA', 'TN', 'FR']
const BIO_MAX = 500
const COMPETENCES_MAX = 10

/**
 * Mon profil : pays et bio pour tous ; compétences, spécialités et disponibilité pour un dev
 * (ce que voient les entreprises qui cherchent un dev). Le formulaire envoie ses valeurs en entier.
 */
export function ModifierProfil() {
  useTitrePage('Mon profil')
  const moi = useQuery({ queryKey: ['moi'], queryFn: lireMoi })

  const entete = (
    <EnTetePage
      titre="Mon profil"
      description={moi.data?.profil === 'client' ? 'Votre pays et quelques mots sur votre activité, pour les devs avec qui vous échangez.' : 'Ce que voient les entreprises qui cherchent un dev, et les membres de l’Échange.'}
      actions={moi.data?.username ? <LienBouton to={`/devs/${moi.data.username}`} variante="contour">Voir mon profil public</LienBouton> : undefined}
    />
  )

  if (moi.isPending) {
    return (
      <div className="flex flex-col gap-6">
        {entete}
        <SqueletteLignes lignes={6} />
      </div>
    )
  }

  if (moi.isError) {
    return (
      <div className="flex flex-col gap-6">
        {entete}
        <EtatErreur description={messageAction(moi.error, 'Impossible de lire votre profil.')} onReessayer={() => void moi.refetch()} />
      </div>
    )
  }

  if (!moi.data) {
    return (
      <div className="flex flex-col gap-6">
        {entete}
        <AccesRefuse description="Connectez-vous pour modifier votre profil." action={<LienBouton to="/connexion">Se connecter</LienBouton>} />
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-6">
      {entete}
      {/* Un formulaire par compte : il part des valeurs enregistrées, puis garde la saisie en cours. */}
      <Formulaire key={moi.data.id} compte={moi.data} />
    </div>
  )
}

function Formulaire({ compte }: { compte: Compte }) {
  const cache = useQueryClient()
  const toast = useToast()
  const [paysChoisi, setPays] = useState(compte.pays ?? '')
  const [bio, setBio] = useState(compte.bio ?? '')
  const [competences, setCompetences] = useState<Techno[]>(compte.competences)
  const [specialitesChoisies, setSpecialites] = useState<Specialite[]>(compte.specialites)
  const [disponible, setDisponible] = useState(compte.disponible)
  const [champs, setChamps] = useState<Record<string, string>>({})

  const enregistrement = useMutation({
    mutationFn: modifierProfil,
    onSuccess: (misAJour) => {
      setChamps({})
      cache.setQueryData(['moi'], misAJour)
      void cache.invalidateQueries({ queryKey: ['profil'] })
      void cache.invalidateQueries({ queryKey: ['devs'] })
      toast.succes('Profil enregistré.')
    },
    onError: (erreur) => {
      setChamps(lireErreurHttp(erreur).champs)
      toast.erreur(messageAction(erreur, 'Le profil n’a pas pu être enregistré. Réessayez dans un instant.'))
    },
  })

  // Compétences, spécialités et disponibilité ne servent qu'aux devs (« Trouver un dev »).
  const estDev = compte.profil === 'dev'
  const listePays = paysChoisi && !pays.includes(paysChoisi) ? [paysChoisi, ...pays] : pays

  function basculer<T>(liste: T[], valeur: T): T[] {
    return liste.includes(valeur) ? liste.filter((item) => item !== valeur) : [...liste, valeur]
  }

  function enregistrer(evenement: FormEvent) {
    evenement.preventDefault()
    enregistrement.mutate({
      pays: paysChoisi || null,
      bio: bio.trim() || null,
      ...(estDev ? { competences, specialites: specialitesChoisies, disponible } : {}),
    })
  }

  return (
    <form onSubmit={enregistrer} noValidate className="flex flex-col gap-6">
      <Carte className="flex flex-col gap-5">
        <Champ libelle="Pays" facultatif erreur={champs.pays}>
          {(liaison) => (
            <Liste {...liaison} value={paysChoisi} onChange={(evenement) => setPays(evenement.target.value)}>
              <option value="">Non précisé</option>
              {listePays.map((code) => (
                <option key={code} value={code}>
                  {nomPays(code)}
                </option>
              ))}
            </Liste>
          )}
        </Champ>
        <Champ
          libelle="Bio"
          facultatif
          aide={`Ce que vous faites et pour qui, en quelques lignes (${bio.length}/${BIO_MAX} caractères).`}
          erreur={champs.bio}
        >
          {(liaison) => <ZoneTexte {...liaison} rows={4} maxLength={BIO_MAX} value={bio} onChange={(evenement) => setBio(evenement.target.value)} />}
        </Champ>
      </Carte>

      {estDev ? (
      <Carte className="flex flex-col gap-5">
        <fieldset className="m-0 flex flex-col gap-3 border-0 p-0">
          <legend className="mb-1 p-0 text-petit font-medium text-titre">Compétences</legend>
          <p className="m-0 text-petit text-texte-doux">
            {COMPETENCES_MAX} au plus ({competences.length} choisie{competences.length > 1 ? 's' : ''}). Elles servent à vous trouver dans « Trouver un dev ».
          </p>
          <div className="flex flex-wrap gap-2">
            {technos.map((techno) => {
              const choisie = competences.includes(techno)
              return (
                <PuceFiltre
                  key={techno}
                  presse={choisie}
                  onClick={() => {
                    if (!choisie && competences.length >= COMPETENCES_MAX) {
                      toast.info(`${COMPETENCES_MAX} compétences au plus : retirez-en une pour en ajouter une autre.`)
                      return
                    }
                    setCompetences((liste) => basculer(liste, techno))
                  }}
                >
                  {techno}
                </PuceFiltre>
              )
            })}
          </div>
          {champs.competences ? <p role="alert" className="m-0 text-petit text-danger">{champs.competences}</p> : null}
        </fieldset>
        <fieldset className="m-0 flex flex-col gap-3 border-0 p-0">
          <legend className="mb-1 p-0 text-petit font-medium text-titre">Spécialités</legend>
          <div className="flex flex-wrap gap-2">
            {specialites.map((specialite) => (
              <PuceFiltre key={specialite.valeur} presse={specialitesChoisies.includes(specialite.valeur)} onClick={() => setSpecialites((liste) => basculer(liste, specialite.valeur))}>
                {specialite.libelle}
              </PuceFiltre>
            ))}
          </div>
        </fieldset>
      </Carte>

      ) : null}

      {estDev ? (
        <Carte>
          <Interrupteur
            libelle="Disponible pour une mission"
            aide="Les entreprises peuvent filtrer les devs disponibles et vous proposer une mission."
            actif={disponible}
            onChange={setDisponible}
          />
        </Carte>
      ) : null}

      <div className="flex justify-end">
        <Bouton type="submit" chargement={enregistrement.isPending} className="w-full tablette:w-auto">
          Enregistrer mon profil
        </Bouton>
      </div>
    </form>
  )
}
