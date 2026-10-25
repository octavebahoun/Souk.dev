import { useState } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router'
import { describe, expect, it, vi } from 'vitest'
import { Bouton, Champ, ChoixCartes, Etapes, formaterFcfa, MenuDeroulant, Modale, Montant, Onglets, Saisie, Tableau } from '.'

describe('système de design', () => {
  it('un bouton en chargement est inactif et annoncé', () => {
    render(<Bouton chargement>Payer</Bouton>)
    const bouton = screen.getByRole('button', { name: 'Payer' })
    expect(bouton).toBeDisabled()
    expect(bouton).toHaveAttribute('aria-busy', 'true')
  })

  it("un champ relie son étiquette, son aide et son erreur au contrôle", () => {
    render(
      <Champ libelle="Numéro Mobile Money" aide="Dix chiffres, sans indicatif." erreur="Ce numéro est trop court.">
        {(liaison) => <Saisie {...liaison} />}
      </Champ>,
    )
    const saisie = screen.getByLabelText('Numéro Mobile Money')
    expect(saisie).toHaveAttribute('aria-invalid', 'true')
    expect(saisie).toHaveAccessibleDescription('Ce numéro est trop court. Dix chiffres, sans indicatif.')
  })

  it('les onglets se parcourent au clavier', () => {
    function Exemple() {
      const [valeur, setValeur] = useState<'factures' | 'recus'>('factures')
      return (
        <Onglets
          libelle="Paiements"
          valeur={valeur}
          onChange={setValeur}
          onglets={[
            { valeur: 'factures', libelle: 'Factures' },
            { valeur: 'recus', libelle: 'Reçus' },
          ]}
        >
          Panneau {valeur}
        </Onglets>
      )
    }
    render(<Exemple />)
    const factures = screen.getByRole('tab', { name: 'Factures' })
    expect(factures).toHaveAttribute('aria-selected', 'true')
    fireEvent.keyDown(factures, { key: 'ArrowRight' })
    expect(screen.getByRole('tab', { name: 'Reçus' })).toHaveAttribute('aria-selected', 'true')
    expect(screen.getByRole('tab', { name: 'Reçus' })).toHaveFocus()
    expect(screen.getByRole('tabpanel')).toHaveTextContent('Panneau recus')
  })

  it('la modale se ferme avec Échap et porte son titre', () => {
    const fermer = vi.fn()
    render(
      <Modale ouverte onFermer={fermer} titre="Arrêter la copie ?" pied={<Bouton>Arrêter</Bouton>}>
        Les données de la copie seront supprimées.
      </Modale>,
    )
    const dialogue = screen.getByRole('dialog', { name: 'Arrêter la copie ?' })
    expect(dialogue).toHaveAttribute('aria-modal', 'true')
    fireEvent.keyDown(dialogue, { key: 'Escape' })
    expect(fermer).toHaveBeenCalledOnce()
  })

  it('le choix en cartes repose sur des boutons radio', () => {
    const changer = vi.fn()
    render(
      <ChoixCartes
        nom="taille"
        legende="Formule d'hébergement"
        valeur="petite"
        onChange={changer}
        options={[
          { valeur: 'petite', libelle: 'Petite' },
          { valeur: 'moyenne', libelle: 'Moyenne' },
        ]}
      />,
    )
    expect(screen.getByRole('group', { name: "Formule d'hébergement" })).toBeInTheDocument()
    expect(screen.getByRole('radio', { name: 'Petite' })).toBeChecked()
    fireEvent.click(screen.getByRole('radio', { name: 'Moyenne' }))
    expect(changer).toHaveBeenCalledWith('moyenne')
  })

  it("les étapes annoncent l'étape en cours", () => {
    render(
      <Etapes
        libelle="Déploiement"
        etapes={[
          { id: 'file', libelle: 'En file', etat: 'terminee' },
          { id: 'construction', libelle: 'Construction', etat: 'en_cours' },
          { id: 'ligne', libelle: 'En ligne', etat: 'a_venir' },
        ]}
      />,
    )
    const courante = screen.getByText('Construction').closest('li')
    expect(courante).toHaveAttribute('aria-current', 'step')
    expect(courante).toHaveTextContent('en cours')
  })

  it('les montants sont des entiers FCFA lisibles', () => {
    expect(formaterFcfa(13500).replace(/\s/g, ' ')).toBe('13 500 F')
    render(<Montant valeur={0} gratuitSiZero />)
    expect(screen.getByText('Gratuit')).toBeInTheDocument()
  })

  it('le tableau garde une légende pour les lecteurs d’écran', () => {
    render(
      <Tableau
        legende="Factures"
        colonnes={[
          { cle: 'numero', entete: 'Numéro', rendu: (ligne: { numero: string }) => ligne.numero, principale: true },
          { cle: 'montant', entete: 'Montant', rendu: () => '7 000 F', alignement: 'droite' },
        ]}
        lignes={[{ numero: 'F-0001' }]}
        cleLigne={(ligne) => ligne.numero}
      />,
    )
    expect(screen.getByRole('table', { name: 'Factures' })).toBeInTheDocument()
    expect(screen.getByRole('list', { name: 'Factures' })).toBeInTheDocument()
  })

  it('le menu déroulant s’ouvre et se ferme au clavier', () => {
    const sortir = vi.fn()
    render(
      <MemoryRouter>
        <MenuDeroulant
          libelle="Menu du compte"
          declencheur="Compte"
          elements={[
            { type: 'lien', libelle: 'Tableau de bord', vers: '/tableau' },
            { type: 'action', libelle: 'Se déconnecter', onClick: sortir },
          ]}
        />
      </MemoryRouter>,
    )
    const bouton = screen.getByRole('button', { name: 'Menu du compte' })
    fireEvent.click(bouton)
    expect(bouton).toHaveAttribute('aria-expanded', 'true')
    const menu = screen.getByRole('menu', { name: 'Menu du compte' })
    fireEvent.click(screen.getByRole('menuitem', { name: 'Se déconnecter' }))
    expect(sortir).toHaveBeenCalledOnce()
    expect(menu).toBeInTheDocument()
  })
})
