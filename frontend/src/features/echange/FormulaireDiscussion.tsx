import { useState, type FormEvent } from 'react'
import { useMutation } from '@tanstack/react-query'
import { Champ, classeChamp } from '../../shared/ui/Champ'
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
  const [canal, setCanal] = useState(canalInitial ?? '')
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
      ? 'Limite atteinte : 10 discussions par heure. Réessayez plus tard.'
      : serveur?.statut === 401
        ? 'Il faut être connecté pour publier.'
        : serveur?.statut === 419
          ? 'La protection du formulaire a expiré. Rechargez la page.'
          : serveur?.statut === 422
            ? 'Le serveur a refusé certains champs.'
            : serveur?.message

  return (
    <form onSubmit={publier} className="flex flex-col gap-3.5" noValidate>
      {alerte ? (
        <p role="alert" className="m-0 rounded-controle border border-[#FECACA] bg-[#FEF2F2] px-3.5 py-2.5 text-sm font-semibold text-danger">
          {alerte}
          {serveur?.message && serveur.statut === 429 ? ` ${serveur.message}` : null}
        </p>
      ) : null}
      {Object.keys(locaux).length > 0 ? (
        <p role="alert" className="m-0 rounded-controle border border-[#FECACA] bg-[#FEF2F2] px-3.5 py-2.5 text-sm font-semibold text-danger">
          Il manque des champs obligatoires. Complétez-les pour publier.
        </p>
      ) : null}

      <Champ id="nd-titre" label="Titre" erreur={erreurDe('titre', locaux.titre)}>
        <input
          id="nd-titre"
          value={titre}
          onChange={(evenement) => setTitre(evenement.target.value)}
          aria-invalid={Boolean(erreurDe('titre', locaux.titre))}
          aria-describedby={erreurDe('titre', locaux.titre) ? 'nd-titre-erreur' : undefined}
          className={classeChamp}
          placeholder="Ex. : Le webhook Wave ne déclenche rien en production"
        />
      </Champ>

      <Champ id="nd-texte" label="Texte" erreur={erreurDe('texte', locaux.texte)}>
        <textarea
          id="nd-texte"
          value={texte}
          onChange={(evenement) => setTexte(evenement.target.value)}
          aria-invalid={Boolean(erreurDe('texte', locaux.texte))}
          aria-describedby={erreurDe('texte', locaux.texte) ? 'nd-texte-erreur' : undefined}
          className={`${classeChamp} min-h-24 py-2.5`}
          placeholder="Expliquez le contexte, ce que vous avez déjà essayé…"
        />
      </Champ>

      <Champ id="nd-canal" label="Canal" erreur={erreurDe('canal', locaux.canal)}>
        <select
          id="nd-canal"
          value={canal}
          onChange={(evenement) => setCanal(evenement.target.value)}
          aria-invalid={Boolean(erreurDe('canal', locaux.canal))}
          aria-describedby={erreurDe('canal', locaux.canal) ? 'nd-canal-erreur' : undefined}
          className={classeChamp}
        >
          <option value="">Choisir un canal</option>
          <optgroup label="Généraux">
            {generaux.map((item) => (
              <option key={item.slug} value={item.slug}>
                {item.nom}
              </option>
            ))}
          </optgroup>
          <optgroup label="Par pays">
            {parPays.map((item) => (
              <option key={item.slug} value={item.slug}>
                {item.nom}
                {item.pays ? ` (${nomPays(item.pays)})` : ''}
              </option>
            ))}
          </optgroup>
        </select>
      </Champ>

      <fieldset className="m-0 border-0 p-0">
        <legend className="mb-2 text-sm font-semibold text-ink">Étiquettes</legend>
        <div className="flex flex-wrap gap-2">
          {etiquettesPossibles.map((etiquette) => {
            const cochee = choisies.includes(etiquette)
            return (
              <label key={etiquette} className="inline-flex min-h-cible cursor-pointer items-center">
                <span
                  className={`inline-flex h-9 items-center gap-2 rounded-full border px-3.5 text-sm font-semibold ${
                    cochee ? 'border-accent bg-nav text-accent' : 'border-line bg-canvas text-ink'
                  }`}
                >
                  <input
                    type="checkbox"
                    checked={cochee}
                    onChange={() => basculer(etiquette)}
                    className="size-4 accent-accent"
                  />
                  {etiquette}
                </span>
              </label>
            )
          })}
        </div>
      </fieldset>

      {estBug ? (
        <fieldset className="m-0 flex flex-col gap-3 rounded-xl border border-[#FECACA] bg-[#FFFBFB] p-4">
          <legend className="px-1.5 text-[15px] font-bold text-ink">Fiche du bug</legend>
          <Champ id="nd-erreur" label="Erreur obtenue" erreur={erreurDe('bug.erreur_obtenue', locaux.erreur)}>
            <textarea
              id="nd-erreur"
              value={erreurBug}
              onChange={(evenement) => setErreurBug(evenement.target.value)}
              aria-invalid={Boolean(erreurDe('bug.erreur_obtenue', locaux.erreur))}
              aria-describedby={erreurDe('bug.erreur_obtenue', locaux.erreur) ? 'nd-erreur-erreur' : undefined}
              className={`${classeChamp} min-h-[72px] py-2.5 font-mono text-[13.5px]`}
            />
          </Champ>
          <Champ id="nd-attendu" label="Comportement attendu" erreur={erreurDe('bug.comportement_attendu', locaux.attendu)}>
            <textarea
              id="nd-attendu"
              value={attendu}
              onChange={(evenement) => setAttendu(evenement.target.value)}
              aria-invalid={Boolean(erreurDe('bug.comportement_attendu', locaux.attendu))}
              aria-describedby={erreurDe('bug.comportement_attendu', locaux.attendu) ? 'nd-attendu-erreur' : undefined}
              className={`${classeChamp} min-h-[72px] py-2.5`}
            />
          </Champ>
          <Champ id="nd-techno" label="Techno" facultatif erreur={serveur?.champs['bug.techno']}>
            <select
              id="nd-techno"
              value={techno}
              onChange={(evenement) => setTechno(evenement.target.value as Techno | '')}
              className={classeChamp}
            >
              <option value="">Aucune</option>
              {technos.map((item) => (
                <option key={item} value={item}>
                  {item}
                </option>
              ))}
            </select>
          </Champ>
          <Champ id="nd-code" label="Code concerné" facultatif erreur={serveur?.champs['bug.code']}>
            <textarea
              id="nd-code"
              value={code}
              onChange={(evenement) => setCode(evenement.target.value)}
              className={`${classeChamp} min-h-24 py-2.5 font-mono text-[13.5px]`}
            />
          </Champ>
          <fieldset className="m-0 border-0 p-0">
            <legend className="mb-2 text-sm font-semibold text-ink">Difficulté</legend>
            <div className="flex flex-wrap gap-2">
              {difficultes.map((item) => (
                <label key={item} className="inline-flex min-h-cible cursor-pointer items-center">
                  <span
                    className={`inline-flex h-9 items-center gap-2 rounded-full border px-3.5 text-sm font-semibold ${
                      difficulte === item ? 'border-accent bg-nav text-accent' : 'border-line bg-canvas text-ink'
                    }`}
                  >
                  <input
                    type="radio"
                    name="nd-difficulte"
                    value={item}
                    checked={difficulte === item}
                    onChange={() => setDifficulte(item)}
                    className="size-4 accent-accent"
                  />
                  {item}
                  </span>
                </label>
              ))}
            </div>
          </fieldset>
        </fieldset>
      ) : null}

      <Champ id="nd-depot" label="Dépôt GitHub" facultatif erreur={serveur?.champs.depot_url}>
        <input
          id="nd-depot"
          value={depot}
          onChange={(evenement) => setDepot(evenement.target.value)}
          aria-invalid={Boolean(serveur?.champs.depot_url)}
          aria-describedby={serveur?.champs.depot_url ? 'nd-depot-erreur' : undefined}
          className={`${classeChamp} font-mono text-[13.5px]`}
          placeholder="https://github.com/vous/projet"
        />
      </Champ>
      <Champ id="nd-demo" label="Lien de démo" facultatif erreur={serveur?.champs.demo_url}>
        <input
          id="nd-demo"
          value={demo}
          onChange={(evenement) => setDemo(evenement.target.value)}
          aria-invalid={Boolean(serveur?.champs.demo_url)}
          aria-describedby={serveur?.champs.demo_url ? 'nd-demo-erreur' : undefined}
          className={`${classeChamp} font-mono text-[13.5px]`}
          placeholder="https://demo.exemple.bj"
        />
      </Champ>

      <div className="flex flex-wrap justify-end gap-2.5">
        <button
          type="button"
          onClick={onAnnuler}
          className="inline-flex min-h-cible items-center rounded-controle border border-line bg-canvas px-4 font-semibold text-ink"
        >
          Annuler
        </button>
        <button
          type="submit"
          disabled={mutation.isPending || canaux.length === 0}
          className="inline-flex min-h-cible items-center rounded-controle bg-accent px-5 font-bold text-canvas disabled:opacity-60"
        >
          {mutation.isPending ? 'Publication…' : 'Publier la discussion'}
        </button>
      </div>
    </form>
  )
}
