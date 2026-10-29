# syntax=docker/dockerfile:1
#
# Front statique de production : build Vite, puis nginx (fallback SPA).
# Les adresses sont figées au build (voir docker-compose.prod.yml) : l'API sur
# api.$DOMAINE, Reverb en WSS. La clé publique de Reverb part aux navigateurs
# de toute façon ; elle est lue dans le volume « secrets » au déploiement.

FROM node:24-alpine AS build

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY . .

ARG VITE_API_BASE
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ARG VITE_REVERB_KEY

RUN npm run build

FROM nginx:1.28-alpine

COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
