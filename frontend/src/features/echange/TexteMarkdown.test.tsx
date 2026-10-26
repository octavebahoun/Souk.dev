import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { TexteMarkdown } from './TexteMarkdown'

describe('TexteMarkdown', () => {
  it('rend titres, listes, gras et code sans jamais interpréter de HTML', () => {
    const { container } = render(
      <TexteMarkdown texte={'## Cause probable\nLa **commande** est `null`.\n\n- vérifier\n- rejouer\n\n```php\n$commande?->update();\n```\n<img src=x onerror=alert(1)>'} />,
    )

    expect(screen.getByText('Cause probable')).toBeInTheDocument()
    expect(screen.getByText('commande').tagName).toBe('STRONG')
    expect(screen.getByText('null').tagName).toBe('CODE')
    expect(screen.getAllByRole('listitem')).toHaveLength(2)
    expect(container.querySelector('pre code')?.textContent).toBe('$commande?->update();')
    expect(container.querySelector('img')).toBeNull()
    expect(screen.getByText('<img src=x onerror=alert(1)>')).toBeInTheDocument()
  })

  it('garde les retours à la ligne d’un message et rend l’italique', () => {
    const { container } = render(<TexteMarkdown texte={'Première ligne\nseconde *ligne*'} />)

    expect(container.querySelectorAll('p br')).toHaveLength(1)
    expect(screen.getByText('ligne').tagName).toBe('EM')
  })

  it('garde le calcul intact, numérote une liste depuis son premier numéro et titre en h3', () => {
    const { container } = render(<TexteMarkdown texte={'# Étapes\n3. vérifier\n4. rejouer\n\nTotal : 5*3*2 lignes'} />)

    expect(container.querySelector('h3')?.textContent).toBe('Étapes')
    expect(container.querySelector('ol')?.getAttribute('start')).toBe('3')
    expect(container.querySelector('em')).toBeNull()
    expect(screen.getByText(/5\*3\*2/)).toBeInTheDocument()
  })
})
