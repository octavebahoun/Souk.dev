# syntax=docker/dockerfile:1
#
# Front statique de production : build Vite, puis nginx (fallback SPA).
# Les adresses sont figées au build (voir docker-compose.prod.yml) : l'API sur
# api.$DOMAINE, Reverb en WSS. La clé publique de Reverb part aux navigateurs
# de toute façon ; elle est lue dans le volume « secrets » au déploiement.

FROM node:24-alpine AS build

WORKDIR /app

COPY package.json package-lock.json .npmrc ./
RUN npm ci --no-audit --no-fund

COPY . .

ARG VITE_API_BASE
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ARG VITE_REVERB_KEY

RUN test -n "$VITE_REVERB_KEY" || { echo "VITE_REVERB_KEY manquant : lire la clé publique de Reverb dans le volume « secrets » (docs/exploitation.md)." >&2; exit 1; }
RUN npm run build && node scripts/csp-nginx.mjs dist/index.html /app/csp.conf

FROM nginx:1.28-alpine

COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/csp.conf /etc/nginx/snippets/csp.conf
COPY --from=build /app/dist /usr/share/nginx/html
