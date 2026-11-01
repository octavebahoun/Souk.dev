# Serveurs du moteur — Souk.dev

Plusieurs démons Docker hébergent les copies. Quand un serveur est plein, on en ajoute
un autre à la liste : chaque nouvelle copie part sur le serveur actif et joignable qui a
le plus de mémoire libre.

## Concepts

- **Machine** : un démon Docker joint par sa valeur `DOCKER_HOST` (`/admin/serveurs`).
  `locale` (démon de la plateforme, socket) existe dès la migration ; les autres sont des
  `tcp://hôte:port` (ou `unix:///…`).
- **Placement** : à chaque lancement, le moteur choisit la machine `active` et `joignable`
  avec le plus de mémoire libre (capacité déclarée, sinon mémoire sondée, moins les
  plafonds des copies hébergées). Sans limite connue, la machine passe devant : on ne
  refuse jamais une copie faute d'avoir déclaré une capacité. Si rien ne convient
  (pleines, en maintenance, injoignables), la copie passe en `echec` avec un message clair
  (et en `incident` facturé si elle était payée).
- **Maintenance (drain)** : la machine garde ses copies jusqu'à leur arrêt mais n'en
  reçoit plus aucune. Réversible à tout moment.
- **Santé** : le moteur sonde chaque démon toutes les 5 minutes (`docker info` :
  joignable, mémoire totale, version) et à la demande (`POST /admin/machines/{id}/sonde`,
  `202` : la sonde tourne sur le moteur, seul à parler à Docker). Une sonde ratée marque
  la machine injoignable : le placement l'évite, l'équipe la voit en rouge.
- **Retrait** : refusé (`409`) tant que des copies y tournent, comme le changement de
  démon (les arrêts et sauvegardes partiraient sur le mauvais démon).

Toutes les commandes docker/compose d'une copie (lancement, sonde, migrations,
sauvegardes, mises à jour, arrêts, nettoyage) visent le démon de sa machine. Les copies
de mesure mémoire et les analyses de sécurité tournent sur le démon local (tâches
courtes de la plateforme).

## Ajouter un serveur

1. Préparer un hôte Docker joignable depuis le conteneur `moteur` (en production :
   TLS obligatoire, un `tcp://…` distant sans TLS est refusé par l'API (422) —
   voir Limites).
2. Dans `/admin/serveurs`, « Ajouter un serveur » : nom, démon, capacité mémoire
   (mémoire réservée aux copies, plateforme déduite — ex. 13 Go sur un VPS de 16 Go).
3. Vérifier que la version du démon apparaît (sonde réussie), puis lancer une copie :
   elle part sur le serveur le plus libre (`machine_nom` dans `GET /deployments/{id}`).

## Tester avec deux machines en local

```sh
docker compose --profile machines up -d dind route-dind
```

Ce profil opt-in lance un second démon (Docker-in-Docker, développement uniquement) dans
son propre espace réseau, à l'adresse fixe `10.199.0.2` (réseau `machines`). Le conteneur
ponctuel `route-dind` pose sur l'hôte une seule route, `10.201.0.0/16 via 10.199.0.2` :
Traefik (réseau de l'hôte) joint ainsi les copies du second démon comme celles du démon
local, à la manière du VPN qui route les sous-réseaux des VPS en production. Le démon
n'ouvre aucun port tcp (sans TLS, quiconque le joindrait aurait la main sur le poste) : le
moteur passe par son socket, partagé dans le volume `dind-socket` (groupe 2376).

**Jamais `network_mode: host` pour ce démon** : deux démons dans le même espace réseau se
disputent `docker0` et les chaînes iptables. Le 1er novembre, le second a effacé les règles
du démon de l'hôte : plus aucun conteneur ne sortait sur Internet jusqu'à
`sudo systemctl restart docker`.

Enregistrer `unix:///run/dind/docker.sock` dans `/admin/serveurs`, vérifier la sonde, puis
jouer avec les capacités. Parcours complet : `npx playwright test e2e/machines.spec.ts`
(enregistre `aux`, réduit `locale` à 512 Mo, vérifie qu'une copie part sur `aux`, la plus
libre, et y répond, bascule `aux` en maintenance, tente le retrait).

## Limites connues

- **Routage direct (v1)** : Traefik central vise l'adresse du conteneur. Ça marche quand
  le sous-réseau des copies de chaque démon est routé jusqu'à Traefik (démon local, DinD
  ci-dessus avec sa route). Des VPS
  séparés exigeront un proxy en bordure par machine et la livraison de ses routes :
  conçu en phase 6, voir `docs/multi-vps.md`, avant tout second VPS réel.
- **`tcp://` sans TLS** : réservé au poste de développement (`localhost`, `127.0.0.0/8`,
  `host.docker.internal` ; le DinD ci-dessus passe par un socket, sans tcp). Un démon distant sans TLS exposerait Docker
  sans authentification : refusé par l'API en production (422). Le TLS par machine
  (`DOCKER_TLS_VERIFY` et certificats stockés par machine) est conçu avec le bord par
  machine en phase 6, voir `docs/multi-vps.md`, avant tout second VPS réel.
- **Pas de migration de copies** : la maintenance draine naturellement (les copies s'y
  terminent), elle ne les déplace pas.
