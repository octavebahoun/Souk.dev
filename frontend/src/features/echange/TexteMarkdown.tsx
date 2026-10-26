import { Fragment, type ReactNode } from 'react'
import { cx } from '../../ui'

/**
 * Markdown simple (analyses Souk AI, discussions, messages), rendu sans HTML brut : titres,
 * paragraphes (un retour à la ligne est gardé), listes, gras, italique, code en ligne et blocs de
 * code. Tout le reste reste du texte.
 */
export function TexteMarkdown({ texte, className }: { texte: string; className?: string }) {
  const blocs: ReactNode[] = []
  const lignes = texte.replace(/\r\n/g, '\n').split('\n')
  let paragraphe: string[] = []
  let liste: { ordonnee: boolean; elements: string[] } | null = null

  const viderParagraphe = () => {
    if (paragraphe.length > 0) {
      const lignesDuParagraphe = paragraphe
      blocs.push(
        <p key={blocs.length} className="m-0 wrap-break-word">
          {lignesDuParagraphe.map((ligne, index) => (
            <Fragment key={index}>
              {index > 0 ? <br /> : null}
              {enLigne(ligne)}
            </Fragment>
          ))}
        </p>,
      )
      paragraphe = []
    }
  }
  const viderListe = () => {
    if (liste) {
      const elements = liste.elements.map((element, index) => <li key={index}>{enLigne(element)}</li>)
      blocs.push(
        liste.ordonnee ? (
          <ol key={blocs.length} className="m-0 flex list-decimal flex-col gap-1 pl-5">
            {elements}
          </ol>
        ) : (
          <ul key={blocs.length} className="m-0 flex list-disc flex-col gap-1 pl-5">
            {elements}
          </ul>
        ),
      )
      liste = null
    }
  }

  for (let index = 0; index < lignes.length; index++) {
    const ligne = lignes[index]
    if (ligne.trimStart().startsWith('```')) {
      viderParagraphe()
      viderListe()
      const code: string[] = []
      index++
      while (index < lignes.length && !lignes[index].trimStart().startsWith('```')) {
        code.push(lignes[index])
        index++
      }
      blocs.push(
        // Défilable au clavier : un bloc de code plus large que la colonne se lit aussi sans souris.
        <pre key={blocs.length} tabIndex={0} className="m-0 overflow-x-auto rounded-moyen bg-nuit-profonde p-3 font-mono text-petit leading-relaxed text-sur-couleur">
          <code>{code.join('\n')}</code>
        </pre>,
      )
      continue
    }
    const titre = /^#{1,6}\s+(.*)$/.exec(ligne)
    const puce = /^\s*[-*]\s+(.*)$/.exec(ligne)
    const numero = /^\s*\d+[.)]\s+(.*)$/.exec(ligne)
    if (titre) {
      viderParagraphe()
      viderListe()
      blocs.push(
        <p key={blocs.length} className="m-0 font-semibold text-titre">
          {enLigne(titre[1])}
        </p>,
      )
    } else if (puce || numero) {
      viderParagraphe()
      const ordonnee = Boolean(numero)
      if (liste && liste.ordonnee !== ordonnee) viderListe()
      liste ??= { ordonnee, elements: [] }
      liste.elements.push((puce ?? numero)![1])
    } else if (ligne.trim() === '') {
      viderParagraphe()
      viderListe()
    } else {
      viderListe()
      paragraphe.push(ligne.trimEnd())
    }
  }
  viderParagraphe()
  viderListe()

  return <div className={cx('flex flex-col gap-3 leading-relaxed', className ?? 'text-corps text-texte')}>{blocs}</div>
}

/** **gras**, *italique* et `code` ; le reste est du texte. */
function enLigne(texte: string): ReactNode[] {
  return texte.split(/(\*\*[^*]+\*\*|\*[^*\s][^*]*\*|`[^`]+`)/g).map((morceau, index) => {
    if (morceau.startsWith('**') && morceau.endsWith('**') && morceau.length > 4) {
      return (
        <strong key={index} className="font-semibold text-titre">
          {morceau.slice(2, -2)}
        </strong>
      )
    }
    if (morceau.startsWith('*') && morceau.endsWith('*') && morceau.length > 2) {
      return <em key={index}>{morceau.slice(1, -1)}</em>
    }
    if (morceau.startsWith('`') && morceau.endsWith('`') && morceau.length > 2) {
      return (
        <code key={index} className="rounded-petit bg-surface-douce px-1 py-0.5 font-mono text-petit">
          {morceau.slice(1, -1)}
        </code>
      )
    }
    return morceau
  })
}
