import { expect, test } from '@playwright/test'
import { auditAxe, connexionDemo, resumer } from './outils'

test.describe('socle', () => {
  test('un visiteur voit les applis du Store et peut revenir d’une page introuvable', async ({ page }) => {
    await page.goto('/store')
    await expect(page.getByText('PharmaStock', { exact: true }).first()).toBeVisible()

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

    // Un écran pas encore refait reste en clair.
    await page.goto('/store')
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light')
  })
})
