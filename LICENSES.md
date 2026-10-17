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
| framer-motion (tiré par motion) | 14.3.0 | MIT |
| motion-dom (tiré par motion) | 14.3.0 | MIT |
| motion-utils (tiré par motion) | 14.1.1 | MIT |
| laravel-echo | 2.6.1 | MIT |
| pusher-js | 8.6.0 | MIT |
| tweetnacl (tiré par pusher-js) | 1.0.3 | Unlicense |
| @fontsource/plus-jakarta-sans | 5.3.0 | OFL-1.1 |
| @fontsource/ibm-plex-mono | 5.3.0 | OFL-1.1 |
| openapi-typescript | 7.13.0 | MIT |
| @stoplight/prism-cli | 5.15.11 | Apache 2.0 |
| PHPUnit | 12.5.38 | BSD-3-Clause |
| Laravel Pint | 1.32.1 | MIT |
| opis/json-schema | 2.6.0 | Apache-2.0 |
| opis/string | 2.1.0 | Apache-2.0 |
| opis/uri | 1.1.0 | Apache-2.0 |
| Vitest | 4.1.11 | MIT |
| jsdom | 29.1.1 | MIT |
| @testing-library/react | 16.3.3 | MIT |
| @testing-library/dom | 10.4.2 | MIT |
| @testing-library/jest-dom | 6.10.0 | MIT |
| license-checker | 25.0.1 | BSD-3-Clause |
| osteel/openapi-httpfoundation-testing | pas encore installé | MIT |
| PostgreSQL (image postgres:16-alpine) | 16 | licence PostgreSQL |
| PostgREST (image postgrest/postgrest) | v12.2.3 | MIT |

`nette/utils` et `nette/schema` (tirés par Laravel) sont en triple licence BSD-3-Clause, GPL-2.0 ou GPL-3.0. On retient BSD-3-Clause. Le front ne contient aucune licence GPL, AGPL ou LGPL.

Les deux polices sont en SIL Open Font License 1.1. Ce n'est ni GPL, ni AGPL, ni LGPL : on peut les embarquer dans le site, on ne peut pas les vendre seules. Elles sont demandées par les maquettes (`design/ecrans/LISEZ-MOI.md`). `npm run check-licenses` ne bloque que GPL, AGPL et LGPL.

`tweetnacl` est en Unlicense (domaine public), tiré par `pusher-js`, lui-même exigé par Laravel Echo pour parler à Reverb. Ce n'est pas une GPL. On ne peut pas l'éviter sans retirer le temps réel.
