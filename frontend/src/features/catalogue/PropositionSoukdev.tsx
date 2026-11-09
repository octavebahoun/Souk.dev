import { useState } from 'react'
import { ExternalLink, FileCode2 } from 'lucide-react'
import { Alerte, Bouton, classesBouton } from '../../ui'
import type { RedactionSoukdev } from './api'

/**
 * Ce que Souk.dev a écrit pour un dépôt sans soukdev.json : ce qui a été lu, ce qui reste à
 * vérifier, puis chaque fichier (les fichiers Docker qui manquaient, puis le soukdev.json), avec
 * le bouton qui ouvre GitHub sur le nouveau fichier déjà rempli.
 */
export function PropositionSoukdev({ redaction }: { redaction: RedactionSoukdev }) {
  // Un soukdev.json déjà dans le dépôt et juste n'est pas reproposé ; à remplacer, GitHub ouvre l'existant.
  const fichiers = [
    ...redaction.fichiers.map((fichier) => ({ ...fichier, remplacer: false })),
    ...(redaction.soukdev_etat === 'garde'
      ? []
      : [{ chemin: 'soukdev.json', contenu: redaction.soukdev_json, lien_github: redaction.lien_github, remplacer: redaction.soukdev_etat === 'a_remplacer' }]),
  ]
  const plusieurs = fichiers.length > 1

  return (
    <section aria-labelledby="titre-proposition" className="flex flex-col gap-4 rounded-moyen border border-bordure bg-surface-douce p-4">
      <div className="flex items-start gap-3">
        <FileCode2 aria-hidden="true" className="mt-0.5 size-5 shrink-0 text-sur-primaire-doux" />
        <div className="flex flex-col gap-1">
          <h3 id="titre-proposition" className="m-0 text-titre-3 text-titre">
            {plusieurs ? `Vos ${fichiers.length} fichiers, écrits par Souk.dev` : `Votre ${fichiers[0]?.chemin ?? 'fichier'}, écrit par Souk.dev`}
          </h3>
          <p className="m-0 text-petit text-texte-doux">
            {plusieurs
              ? 'Relisez-les, ajoutez-les à votre dépôt (un bouton par fichier), puis vérifiez le dépôt.'
              : 'Relisez-le, ajoutez-le à votre dépôt, puis vérifiez le dépôt.'}
          </p>
        </div>
      </div>

      {redaction.deductions.length > 0 ? (
        <div className="flex flex-col gap-1.5">
          <p className="m-0 text-petit font-medium text-titre">Ce que Souk.dev a lu dans votre dépôt</p>
          <ul className="m-0 flex flex-col gap-1 pl-5 text-petit text-texte">
            {redaction.deductions.map((ligne) => (
              <li key={ligne}>{ligne}</li>
            ))}
          </ul>
        </div>
      ) : null}

      {redaction.a_verifier.length > 0 ? (
        <Alerte ton="alerte" titre="À vérifier avant de publier">
          <ul className="m-0 flex flex-col gap-1 pl-5">
            {redaction.a_verifier.map((ligne) => (
              <li key={ligne}>{ligne}</li>
            ))}
          </ul>
        </Alerte>
      ) : null}

      {redaction.soukdev_etat === 'garde' ? <p className="m-0 text-petit text-texte">Votre soukdev.json est gardé tel quel : il ne manquait que les fichiers Docker.</p> : null}

      {fichiers.map((fichier) => (
        <FichierPropose key={fichier.chemin} {...fichier} />
      ))}

      <p className="m-0 text-petit text-texte-doux">
        {fichiers.some((fichier) => fichier.lien_github)
          ? 'GitHub s’ouvre avec chaque fichier déjà rempli : validez le commit, puis revenez cliquer sur « Vérifier le dépôt ».'
          : 'Créez ces fichiers à la racine du dépôt avec ce contenu, puis cliquez sur « Vérifier le dépôt ».'}
      </p>
    </section>
  )
}

function FichierPropose({ chemin, contenu, lien_github, remplacer }: { chemin: string; contenu: string; lien_github: string | null; remplacer: boolean }) {
  const [copie, setCopie] = useState(false)

  return (
    <div className="flex flex-col gap-1.5">
      {remplacer ? (
        <p className="m-0 text-petit text-texte">
          Ce fichier existe déjà dans votre dépôt : copiez ce contenu, ouvrez-le sur GitHub, remplacez tout son texte, puis validez.
        </p>
      ) : null}
      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="m-0 font-mono text-petit font-medium text-titre">{chemin}</p>
        <div className="flex items-center gap-1">
          <button
            type="button"
            onClick={() => {
              void navigator.clipboard?.writeText(contenu).then(() => setCopie(true))
            }}
            className="rounded-petit px-2 py-1 text-petit font-medium text-lien hover:bg-surface"
          >
            {copie ? 'Copié' : 'Copier'}
            <span className="pour-lecteur"> {chemin}</span>
          </button>
          {lien_github ? (
            <a
              href={lien_github}
              target="_blank"
              rel="noopener noreferrer"
              aria-label={`${remplacer ? 'Remplacer' : 'Ajouter'} ${chemin} sur GitHub (nouvel onglet)`}
              className={classesBouton('primaire', 'sm')}
            >
              {remplacer ? 'Remplacer sur GitHub' : 'Ajouter sur GitHub'}
              <ExternalLink aria-hidden="true" className="size-3.5" />
            </a>
          ) : null}
        </div>
      </div>
      <pre className="m-0 max-h-72 overflow-auto rounded-moyen border border-bordure bg-surface p-3 font-mono text-petit text-titre">
        <code>{contenu}</code>
      </pre>
    </div>
  )
}

/** Le bouton qui demande la proposition, sous le champ du dépôt. */
export function DemandeSoukdev({ desactive, chargement, onDemander }: { desactive: boolean; chargement: boolean; onDemander: () => void }) {
  return (
    <p className="m-0 flex flex-wrap items-center gap-x-2 gap-y-1 text-petit text-texte-doux">
      Il manque soukdev.json ou docker-compose.yml ?
      <Bouton variante="secondaire" taille="sm" chargement={chargement} disabled={desactive} onClick={onDemander}>
        Souk.dev les écrit pour vous
      </Bouton>
    </p>
  )
}
