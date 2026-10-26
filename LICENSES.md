# Licences — Souk.dev

Tableau des composants tiers, tenu à jour dès le départ comme l'exige le règlement CADEV 2026.

**Autorisées** : MIT, Apache 2.0, BSD, licence PostgreSQL.
**Interdites** : GPL, AGPL, LGPL (le projet devient irrecevable).

Versions exactes déjà installées. Les autres lignes attendent leur installation.

| Composant | Version | Licence |
|---|---|---|
| Laravel (laravel/framework) | 13.35.0 | MIT |
| Laravel Reverb | 1.12.0 | MIT |
| pusher/pusher-php-server | 7.3.0 | MIT |
| Laravel Echo | pas encore installé | MIT |
| Laravel Sanctum | 4.3.3 | MIT |
| Laravel Socialite | 5.31.0 | MIT |
| React et react-dom | 19.3.0 | MIT |
| Vite | 8.3.3 | MIT |
| TypeScript | 6.0.3 | Apache 2.0 |
| Tailwind CSS | 4.3.3 | MIT |
| @tailwindcss/vite | 4.3.3 | MIT |
| Axios | 1.20.0 | MIT |
| react-router | 8.4.0 | MIT |
| @remix-run/route-pattern (tiré par react-router) | 0.22.1 | MIT |
| cookie-es (tiré par react-router) | 3.1.1 | MIT |
| @tanstack/react-query | 5.104.3 | MIT |
| @tanstack/query-core (tiré par react-query) | 5.104.3 | MIT |
| motion | 14.3.0 | MIT |
| lucide-react (icônes) | 1.61.0 | ISC |
| framer-motion (tiré par motion) | 14.3.0 | MIT |
| motion-dom (tiré par motion) | 14.3.0 | MIT |
| motion-utils (tiré par motion) | 14.1.1 | MIT |
| laravel-echo | 2.6.1 | MIT |
| pusher-js | 8.6.0 | MIT |
| tweetnacl (tiré par pusher-js) | 1.0.3 | Unlicense |
| @fontsource-variable/outfit (police de l'interface) | 5.3.0 | OFL-1.1 |
| @fontsource/ibm-plex-mono | 5.3.0 | OFL-1.1 |
| openapi-typescript | 7.13.0 | MIT |
| PHPUnit | 12.5.38 | BSD-3-Clause |
| Laravel Pint | 1.32.1 | MIT |
| opis/json-schema | 2.6.0 | Apache-2.0 |
| symfony/yaml (tests : réponses de l'API validées contre openapi.yaml) | 8.1.9 | MIT |
| opis/string | 2.1.0 | Apache-2.0 |
| opis/uri | 1.1.0 | Apache-2.0 |
| Vitest | 4.1.11 | MIT |
| jsdom | 29.1.1 | MIT |
| @testing-library/react | 16.3.3 | MIT |
| @testing-library/dom | 10.4.2 | MIT |
| @testing-library/jest-dom | 6.10.0 | MIT |
| license-checker | 25.0.1 | BSD-3-Clause |
| @playwright/test (parcours de bout en bout, captures) | 1.64.1 | Apache 2.0 |
| osteel/openapi-httpfoundation-testing | pas encore installé | MIT |
| PostgreSQL (image postgres:16-alpine) | 16 | licence PostgreSQL |
| PostgREST (image postgrest/postgrest) | v12.2.3 | MIT |
| Traefik (image Docker du reverse proxy) | 3.3.4 | MIT |
| gitleaks (image zricethezav/gitleaks : secrets d'un dépôt avant publication, conteneur jetable) | v8.21.2 | MIT |
| Trivy (image aquasec/trivy : failles connues des dépendances, conteneur jetable) | 0.58.1 | Apache 2.0 |
| resend/resend-php (envoi des emails) | 1.17.0 | MIT |
| laravel/boost (outil de dev : règles et skills pour les agents) | 2.10.3 | MIT |
| laravel/mcp (tiré par laravel/boost) | 1.1.1 | MIT |
| laravel/roster (tiré par laravel/boost) | 1.0.0 | MIT |

`nette/utils` et `nette/schema` (tirés par Laravel) sont en triple licence BSD-3-Clause, GPL-2.0 ou GPL-3.0. On retient BSD-3-Clause. Le front ne contient aucune licence GPL, AGPL ou LGPL.

Les deux polices (Outfit pour l'interface, IBM Plex Mono pour le code) sont en SIL Open Font License 1.1. Ce n'est ni GPL, ni AGPL, ni LGPL : on peut les embarquer dans le site, on ne peut pas les vendre seules. Outfit remplace Plus Jakarta Sans depuis la refonte : c'est la police de secours que déclare le design system de Systalink (`BRIEF.md`, section 8). `npm run check-licenses` ne bloque que GPL, AGPL et LGPL.

`tweetnacl` est en Unlicense (domaine public), tiré par `pusher-js`, lui-même exigé par Laravel Echo pour parler à Reverb. Ce n'est pas une GPL. On ne peut pas l'éviter sans retirer le temps réel.

**Outils d'audit qui ne sont pas des dépendances du projet** (rien n'est installé ni livré avec Souk.dev) :
`axe-core` (MPL-2.0), chargé dans la page seulement pendant l'audit d'accessibilité (`frontend/e2e/outils.ts`),
et Lighthouse (Apache 2.0), lancé avec `npx`. La MPL ne porte que sur les fichiers d'axe-core eux-mêmes,
que le projet ne modifie ni ne distribue.


## Logos de marques (réseaux Mobile Money)

Récupérés une seule fois par l'API de Logo.dev (outil MCP `logo-dev`), le 26 octobre 2026, puis
réduits en WebP 96 px et hébergés dans `frontend/src/assets/reseaux/`. Ce sont des marques de leurs
propriétaires, pas des composants sous licence libre : elles ne servent qu'à désigner le réseau
choisi pour un paiement ou un reversement, jamais en décoration ni pour suggérer un partenariat.

| Fichier | Marque | Domaine interrogé |
|---|---|---|
| `mtn.webp` | MTN (MTN MoMo) | mtn.com |
| `moov.webp` | Moov Africa (Moov Money) | moov-africa.bj |
| `celtiis.webp` | Celtiis (Celtiis Cash) | celtiis.bj |
| `orange.webp` | Orange (Orange Money) | orange.com |
| `wave.webp` | Wave | wave.com |
| `free.webp` | Free Sénégal (Free Money) | free.sn |
| `togocel.webp` | Togocel (Togocel Money) | togocel.tg |
| `airtel.webp` | Airtel Africa (Airtel Money) | airtel.africa |
