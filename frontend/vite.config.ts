import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import { defineConfig } from 'vitest/config'

// Laravel sur la machine (php artisan serve) ou le conteneur « api » (docker-compose.yml).
// Toujours localhost, jamais 127.0.0.1 : mélanger les deux casse la session.
const cible = process.env.VITE_PROXY_CIBLE || 'http://localhost:8000'

// L'en-tête Host reste localhost:5173 : Laravel construit ses URL (captures, retours) sur le front.
const versLaravel = { target: cible, changeOrigin: false }

// https://vite.dev/config/
export default defineConfig({
  plugins: [react(), tailwindcss()],
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
