import { useState } from 'react'
import { ExternalLink, FileCode2 } from 'lucide-react'
import { Alerte, Bouton, classesBouton } from '../../ui'
import type { RedactionSoukdev } from './api'

/**
 * Le soukdev.json écrit par Souk.dev pour un dépôt qui n'en a pas : ce qui a été lu, ce qui reste
 * à vérifier, le fichier, et le bouton qui ouvre GitHub sur le nouveau fichier déjà rempli.
 */
export function PropositionSoukdev({ redaction }: { redaction: RedactionSoukdev }) {
  const [copie, setCopie] = useState(false)

  return (
    <section aria-labelledby="titre-proposition" className="flex flex-col gap-4 rounded-moyen border border-bordure bg-surface-douce p-4">
      <div className="flex items-start gap-3">
        <FileCode2 aria-hidden="true" className="mt-0.5 size-5 shrink-0 text-sur-primaire-doux" />
        <div className="flex flex-col gap-1">
          <h3 id="titre-proposition" className="m-0 text-titre-3 text-titre">
            Votre soukdev.json, écrit par Souk.dev
          </h3>
          <p className="m-0 text-petit text-texte-doux">Relisez-le, ajoutez-le à votre dépôt, puis vérifiez le dépôt.</p>
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

      <div className="flex flex-col gap-1.5">
        <div className="flex items-center justify-between gap-2">
          <p className="m-0 font-mono text-petit text-texte-doux">soukdev.json</p>
          <button
            type="button"
            onClick={() => {
              void navigator.clipboard?.writeText(redaction.soukdev_json).then(() => setCopie(true))
            }}
            className="rounded-petit px-2 py-1 text-petit font-medium text-lien hover:bg-surface"
          >
            {copie ? 'Copié' : 'Copier'}
            <span className="pour-lecteur"> le soukdev.json</span>
          </button>
        </div>
        <pre className="m-0 max-h-80 overflow-auto rounded-moyen border border-bordure bg-surface p-3 font-mono text-petit text-titre">
          <code>{redaction.soukdev_json}</code>
        </pre>
      </div>

      {redaction.lien_github ? (
        <div className="flex flex-col gap-2 tablette:flex-row tablette:items-center">
          <a href={redaction.lien_github} target="_blank" rel="noopener noreferrer" className={classesBouton('primaire', 'md')}>
            Ajouter à mon dépôt sur GitHub
            <ExternalLink aria-hidden="true" className="size-4" />
            <span className="pour-lecteur">(nouvel onglet)</span>
          </a>
          <p className="m-0 text-petit text-texte-doux">GitHub s’ouvre avec le fichier déjà rempli : validez le commit, puis revenez vérifier le dépôt.</p>
        </div>
      ) : (
        <p className="m-0 text-petit text-texte-doux">
          Créez un fichier <code className="font-mono">soukdev.json</code> à la racine du dépôt avec ce contenu, puis vérifiez le dépôt.
        </p>
      )}
    </section>
  )
}

/** Le bouton qui demande la proposition, sous le champ du dépôt. */
export function DemandeSoukdev({ desactive, chargement, onDemander }: { desactive: boolean; chargement: boolean; onDemander: () => void }) {
  return (
    <p className="m-0 flex flex-wrap items-center gap-x-2 gap-y-1 text-petit text-texte-doux">
      Pas encore de soukdev.json ?
      <Bouton variante="fantome" taille="sm" chargement={chargement} disabled={desactive} onClick={onDemander}>
        Souk.dev l’écrit pour vous
      </Bouton>
    </p>
  )
}
