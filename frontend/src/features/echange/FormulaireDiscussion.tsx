import { useState, type FormEvent } from 'react'
import { useMutation } from '@tanstack/react-query'
import { Alerte, Bouton, Champ, Liste, PuceFiltre, Saisie, ZoneTexte } from '../../ui'
import {
  creerDiscussion,
  lireErreurHttp,
  type Canal,
  type Difficulte,
  type Discussion,
  type DiscussionCreation,
  type Etiquette,
  type Techno,
} from './api'
import { libelleDifficulte } from './presentation'
import { nomPays } from './texte'

const etiquettesPossibles: Etiquette[] = ['bug', 'question', 'tuto', 'projet', 'entraide']
const difficultes: Difficulte[] = ['facile', 'moyen', 'difficile']
const technos: Techno[] = [
  'laravel',
  'symfony',
  'php',
  'node',
  'express',
  'nestjs',
  'nextjs',
  'react',
  'vue',
  'angular',
  'svelte',
  'django',
  'flask',
  'fastapi',
  'spring',
  'dotnet',
  'go',
  'postgresql',
  'mysql',
  'mongodb',
  'redis',
  'autre',
]

type FormulaireDiscussionProps = {
  canaux: Canal[]
  canalInitial?: string
  onPubliee: (discussion: Discussion) => void
  onAnnuler: () => void
}

type ChampsLocaux = {
  titre?: string
  texte?: string
  canal?: string
  erreur?: string
  attendu?: string
}

export function FormulaireDiscussion({
  canaux,
  canalInitial,
  onPubliee,
  onAnnuler,
}: FormulaireDiscussionProps) {
  const [titre, setTitre] = useState('')
  const [texte, setTexte] = useState('')
  // Un canal est toujours présélectionné : personne ne reste bloqué devant un bouton grisé.
  const canalParDefaut =
    canalInitial ?? canaux.find((item) => item.slug === 'entraide-generale')?.slug ?? canaux.find((item) => item.pays === null)?.slug ?? canaux[0]?.slug ?? ''
  const [canalChoisi, setCanal] = useState<string | null>(null)
  const canal = canalChoisi ?? canalParDefaut
  const [choisies, setChoisies] = useState<Etiquette[]>([])
  const [erreurBug, setErreurBug] = useState('')
  const [attendu, setAttendu] = useState('')
  const [techno, setTechno] = useState<Techno | ''>('')
  const [code, setCode] = useState('')
  const [difficulte, setDifficulte] = useState<Difficulte | ''>('')
  const [depot, setDepot] = useState('')
  const [demo, setDemo] = useState('')
  const [locaux, setLocaux] = useState<ChampsLocaux>({})

  const mutation = useMutation({
    mutationFn: creerDiscussion,
    onSuccess: onPubliee,
  })

  const estBug = choisies.includes('bug')
  const serveur = mutation.error ? lireErreurHttp(mutation.error) : null
  const generaux = canaux.filter((item) => item.pays === null)
  const parPays = canaux.filter((item) => item.pays !== null)

  function basculer(etiquette: Etiquette) {
    setChoisies((liste) =>
      liste.includes(etiquette) ? liste.filter((item) => item !== etiquette) : [...liste, etiquette],
    )
  }

  function publier(evenement: FormEvent<HTMLFormElement>) {
    evenement.preventDefault()
    const suivants: ChampsLocaux = {}
    if (!titre.trim()) suivants.titre = 'Donnez un titre à votre discussion.'
    if (!texte.trim()) suivants.texte = 'Décrivez votre problème ou votre question.'
    if (!canal) suivants.canal = 'Choisissez un canal.'
    if (estBug && !erreurBug.trim()) suivants.erreur = 'Indiquez l’erreur obtenue.'
    if (estBug && !attendu.trim()) suivants.attendu = 'Décrivez le comportement attendu.'
    setLocaux(suivants)
    if (Object.keys(suivants).length > 0) return

    const corps: DiscussionCreation = {
      titre: titre.trim(),
      texte: texte.trim(),
      canal,
    }
    if (choisies.length > 0) corps.etiquettes = choisies
    if (depot.trim()) corps.depot_url = depot.trim()
    if (demo.trim()) corps.demo_url = demo.trim()
    if (estBug) {
      corps.bug = {
        erreur_obtenue: erreurBug.trim(),
        comportement_attendu: attendu.trim(),
      }
      if (techno) corps.bug.techno = techno
      if (code.trim()) corps.bug.code = code.trim()
      if (difficulte) corps.bug.difficulte = difficulte
    }
    mutation.mutate(corps)
  }

  function erreurDe(cle: string, locale?: string) {
    return locale || serveur?.champs[cle]
  }

  const alerte =
    serveur?.statut === 429
      ? 'Limite atteinte : 10 discussions par heure. Réessayez plus tard.'
      : serveur?.statut === 401
        ? 'Il faut être connecté pour publier.'
        : serveur?.statut === 419
          ? 'La protection du formulaire a expiré. Rechargez la page.'
          : serveur?.statut === 422
            ? 'Le serveur a refusé certains champs.'
            : serveur?.message

  return (
    <form onSubmit={publier} className="flex flex-col gap-4" noValidate>
      {alerte ? (
        <Alerte ton="danger" urgente>
          {alerte}
          {serveur?.message && serveur.statut === 429 ? ` ${serveur.message}` : null}
        </Alerte>
      ) : null}
      {Object.keys(locaux).length > 0 ? (
        <Alerte ton="danger" urgente>
          Il manque des champs obligatoires. Complétez-les pour publier.
        </Alerte>
      ) : null}

      {canaux.length === 0 ? (
        <Alerte ton="alerte">
          Aucun canal n’est encore ouvert. Fermez cette fenêtre, utilisez « Créer un canal », puis revenez publier.
        </Alerte>
      ) : null}

      <Champ libelle="Titre" erreur={erreurDe('titre', locaux.titre)}>
        {(liaison) => (
          <Saisie {...liaison} value={titre} onChange={(evenement) => setTitre(evenement.target.value)} placeholder="Ex. : Le webhook Wave ne déclenche rien en production" data-autofocus />
        )}
      </Champ>

      <Champ libelle="Texte" erreur={erreurDe('texte', locaux.texte)} aide="Le contexte, ce que vous avez déjà essayé. Le Markdown est accepté.">
        {(liaison) => <ZoneTexte {...liaison} value={texte} onChange={(evenement) => setTexte(evenement.target.value)} rows={5} />}
      </Champ>

      <Champ libelle="Canal" erreur={erreurDe('canal', locaux.canal)}>
        {(liaison) => (
          <Liste {...liaison} value={canal} onChange={(evenement) => setCanal(evenement.target.value)}>
            <option value="">Choisir un canal</option>
            {generaux.length > 0 ? (
              <optgroup label="Généraux">
                {generaux.map((item) => (
                  <option key={item.slug} value={item.slug}>
                    {item.nom}
                  </option>
                ))}
              </optgroup>
            ) : null}
            {parPays.length > 0 ? (
              <optgroup label="Par pays">
                {parPays.map((item) => (
                  <option key={item.slug} value={item.slug}>
                    {item.nom}
                    {item.pays ? ` (${nomPays(item.pays)})` : ''}
                  </option>
                ))}
              </optgroup>
            ) : null}
          </Liste>
        )}
      </Champ>

      <fieldset className="m-0 flex flex-col gap-2 border-0 p-0">
        <legend className="mb-2 text-petit font-medium text-titre">Étiquettes</legend>
        <div className="flex flex-wrap gap-2">
          {etiquettesPossibles.map((etiquette) => (
            <PuceFiltre key={etiquette} presse={choisies.includes(etiquette)} onClick={() => basculer(etiquette)}>
              {etiquette}
            </PuceFiltre>
          ))}
        </div>
      </fieldset>

      {estBug ? (
        <fieldset className="m-0 flex flex-col gap-4 rounded-moyen border border-bordure-danger bg-fond-danger/40 p-4">
          <legend className="px-1.5 text-corps font-semibold text-titre">Fiche du bug</legend>
          <Champ libelle="Erreur obtenue" erreur={erreurDe('bug.erreur_obtenue', locaux.erreur)}>
            {(liaison) => <ZoneTexte {...liaison} value={erreurBug} onChange={(evenement) => setErreurBug(evenement.target.value)} rows={3} className="font-mono text-petit" />}
          </Champ>
          <Champ libelle="Comportement attendu" erreur={erreurDe('bug.comportement_attendu', locaux.attendu)}>
            {(liaison) => <ZoneTexte {...liaison} value={attendu} onChange={(evenement) => setAttendu(evenement.target.value)} rows={3} />}
          </Champ>
          <div className="grid gap-4 tablette:grid-cols-2">
            <Champ libelle="Techno" facultatif erreur={serveur?.champs['bug.techno']}>
              {(liaison) => (
                <Liste {...liaison} value={techno} onChange={(evenement) => setTechno(evenement.target.value as Techno | '')}>
                  <option value="">Aucune</option>
                  {technos.map((item) => (
                    <option key={item} value={item}>
                      {item}
                    </option>
                  ))}
                </Liste>
              )}
            </Champ>
            <fieldset className="m-0 flex flex-col gap-1.5 border-0 p-0">
              <legend className="mb-1.5 text-petit font-medium text-titre">
                Difficulté <span className="font-normal text-texte-doux">(facultatif)</span>
              </legend>
              <div className="flex flex-wrap gap-2">
                {difficultes.map((item) => (
                  <PuceFiltre key={item} presse={difficulte === item} onClick={() => setDifficulte(difficulte === item ? '' : item)}>
                    {libelleDifficulte[item]}
                  </PuceFiltre>
                ))}
              </div>
            </fieldset>
          </div>
          <Champ libelle="Code concerné" facultatif erreur={serveur?.champs['bug.code']}>
            {(liaison) => <ZoneTexte {...liaison} value={code} onChange={(evenement) => setCode(evenement.target.value)} rows={4} className="font-mono text-petit" />}
          </Champ>
        </fieldset>
      ) : null}

      <div className="grid gap-4 tablette:grid-cols-2">
        <Champ
          libelle="Dépôt GitHub"
          facultatif
          erreur={serveur?.champs.depot_url}
          aide={estBug ? 'Avec le dépôt, les autres devs lancent une copie de test et essaient leur correctif.' : undefined}
        >
          {(liaison) => <Saisie {...liaison} value={depot} onChange={(evenement) => setDepot(evenement.target.value)} className="font-mono text-petit" placeholder="https://github.com/vous/projet" />}
        </Champ>
        <Champ libelle="Lien de démo" facultatif erreur={serveur?.champs.demo_url}>
          {(liaison) => <Saisie {...liaison} value={demo} onChange={(evenement) => setDemo(evenement.target.value)} className="font-mono text-petit" placeholder="https://demo.exemple.bj" />}
        </Champ>
      </div>

      <div className="flex flex-col-reverse gap-2 border-t border-bordure pt-4 tablette:flex-row tablette:justify-end">
        <Bouton variante="contour" onClick={onAnnuler}>
          Annuler
        </Bouton>
        <Bouton type="submit" chargement={mutation.isPending} disabled={canaux.length === 0}>
          Publier la discussion
        </Bouton>
      </div>
    </form>
  )
}
