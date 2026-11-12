import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig } from 'vitest/config'
import { appendFileSync, existsSync } from 'node:fs'
import { resolve } from 'node:path'
import type { Plugin } from 'vite'
import { prechargerRoutes } from './vite.precharger.ts'

/**
 * Adresse publique du site, pour les liens absolus du HTML (aperçu des partages, adresse
 * canonique) et la ligne « Sitemap » de robots.txt (le plan du site est servi par l'API, qui
 * connaît les applis, les profils et les discussions).
 */
function adresseSite(): Plugin {
  const site = (process.env.VITE_SITE_URL || 'https://soukdev.shop').replace(/\/+$/, '')
  let sortie = 'dist'

  return {
    name: 'souk-adresse-site',
    configResolved(config) {
      sortie = resolve(config.root, config.build.outDir)
    },
    transformIndexHtml(html) {
      return html.replaceAll('%SITE%', site)
    },
    closeBundle() {
      const robots = resolve(sortie, 'robots.txt')
      const api = (process.env.VITE_API_BASE || '').replace(/\/+$/, '')
      if (api && existsSync(robots)) appendFileSync(robots, `\nSitemap: ${api}/sitemap.xml\n`)
    },
  }
}

// Laravel sur la machine (php artisan serve) ou le conteneur « api » (docker-compose.yml).
// Toujours localhost, jamais 127.0.0.1 : mélanger les deux casse la session.
const cible = process.env.VITE_PROXY_CIBLE || 'http://localhost:8000'

// L'en-tête Host reste localhost:5173 : Laravel construit ses URL (captures, retours) sur le front.
const versLaravel = { target: cible, changeOrigin: false }

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss(), prechargerRoutes(), adresseSite()],
  server: {
    proxy: {
      '/api': versLaravel,
      '/sanctum': versLaravel,
      '/auth': versLaravel,
      '/logout': versLaravel,
      '/broadcasting': versLaravel,
      '/storage': versLaravel,
    },
  },
  test: {
    environment: 'jsdom',
    setupFiles: './src/test/setup.ts',
    // Les parcours de bout en bout (e2e/) tournent avec Playwright, pas avec Vitest.
    include: ['src/**/*.test.{ts,tsx}'],
  },
})
