import { expect, test } from '@playwright/test'
import { auditAxe, connexionDemo, resumer } from './outils'

test.describe('socle', () => {
  test('un visiteur voit les applis du Store et peut revenir d’une page introuvable', async ({ page }) => {
    await page.goto('/')
    await expect(page.getByRole('heading', { level: 1, name: 'Le marché des devs africains.' })).toBeVisible()
    expect(resumer(await auditAxe(page))).toBe('')

    await page.goto('/store')
    await expect(page.getByText('PharmaStock', { exact: true }).first()).toBeVisible()
    expect(resumer(await auditAxe(page))).toBe('')

    // Trouver un dev, et la proposition de mission.
    await page.goto('/devs')
    await expect(page.getByRole('heading', { level: 2, name: 'Awa Dossou' })).toBeVisible()
    await page.getByRole('button', { name: 'Paiement MoMo' }).click()
    await expect(page.getByText(/classés par correspondance/)).toBeVisible()
    expect(resumer(await auditAxe(page))).toBe('')
    await page.getByRole('button', { name: 'Proposer une mission' }).first().click()
    await expect(page.getByRole('dialog', { name: /Proposer une mission à/ })).toBeVisible()
    expect(resumer(await auditAxe(page, '[role="dialog"]'))).toBe('')
    await page.keyboard.press('Escape')

    // Les événements ; créer un événement demande d'être connecté.
    await page.goto('/evenements')
    await expect(page.getByRole('heading', { level: 2, name: 'À venir' })).toBeVisible()
    expect(resumer(await auditAxe(page))).toBe('')
    await page.getByRole('button', { name: 'Créer un événement' }).first().click()
    await expect(page.getByRole('dialog', { name: 'Connectez-vous pour créer un événement' })).toBeVisible()
    expect(resumer(await auditAxe(page, '[role="dialog"]'))).toBe('')
    await page.keyboard.press('Escape')

    // La connexion : GitHub pour les devs, lien par e-mail pour les autres.
    await page.goto('/connexion')
    await expect(page.getByRole('link', { name: 'Continuer avec GitHub' })).toBeVisible()
    await page.getByRole('button', { name: 'Entreprise ou particulier' }).click()
    await page.getByRole('button', { name: 'Recevoir mon lien' }).click()
    await expect(page.getByText('Cette adresse ne semble pas valide')).toBeVisible()
    expect(resumer(await auditAxe(page))).toBe('')

    await page.goto('/une-page-qui-n-existe-pas')
    await expect(page.getByRole('heading', { level: 1, name: 'Cette page n\'existe pas' })).toBeVisible()
    expect(resumer(await auditAxe(page))).toBe('')
    await page.getByRole('link', { name: 'Voir le Store' }).click()
    await expect(page).toHaveURL(/\/store$/)
  })

  test('un dev de démo se connecte et retrouve son espace', async ({ page }) => {
    await connexionDemo(page, 'soukdemo-awa')
    await page.goto('/copies')

    const espace = page.getByRole('navigation', { name: 'Mon espace' })
    await expect(espace.getByRole('link', { name: 'Mes copies' })).toHaveAttribute('aria-current', 'page')

    await page.getByRole('button', { name: 'Menu du compte' }).click()
    await expect(page.getByRole('menu', { name: 'Menu du compte' })).toContainText('Awa Dossou')
    await page.keyboard.press('Escape')
    await expect(page.getByRole('menu')).toHaveCount(0)

    // L'Échange et son formulaire de discussion, fiche du bug dépliée.
    await page.goto('/echange')
    await expect(page.getByRole('heading', { level: 1, name: 'Échange' })).toBeVisible()
    expect(resumer(await auditAxe(page))).toBe('')
    await page.getByRole('button', { name: 'Nouvelle discussion' }).first().click()
    const formulaire = page.getByRole('dialog', { name: 'Nouvelle discussion' })
    await formulaire.getByRole('button', { name: 'bug', exact: true }).click()
    await expect(formulaire.getByText('Fiche du bug')).toBeVisible()
    expect(resumer(await auditAxe(page, '[role="dialog"]'))).toBe('')
    await page.keyboard.press('Escape')
  })

  test('le thème sombre se choisit au clavier et reste après rechargement', async ({ page }) => {
    await page.goto('/page-inconnue')
    const bouton = page.getByRole('button', { name: /Changer de thème/ })
    // Système → clair → sombre.
    await bouton.focus()
    await page.keyboard.press('Enter')
    await page.keyboard.press('Enter')
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')
    expect(resumer(await auditAxe(page))).toBe('')
    await page.reload()
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')

    // Le profil public suit le thème choisi, et l'audit axe passe aussi en sombre.
    await page.goto('/devs/soukdemo-awa')
    await expect(page.getByRole('heading', { level: 1, name: 'Awa Dossou' })).toBeVisible()
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark')
    expect(resumer(await auditAxe(page))).toBe('')
  })
})
