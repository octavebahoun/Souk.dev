# Architecture — Souk.dev

Vue d'ensemble pour qui reprend le code : les services, le chemin d'une requête, le moteur qui
fait tourner les copies, l'argent, le temps réel. Le détail de chaque domaine est dans sa page :
[paiement et grand livre](paiement.md), [exploitation](exploitation.md),
[modération](moderation.md), [plusieurs serveurs](machines.md), [mises à jour](mises-a-jour.md),
[backend intégré](backend-integre.md), [Souk AI](souk-ai.md), [design](design.md).

## Le produit en une phrase

Des devs publient des applis (un dépôt GitHub public avec `docker-compose.yml` et
`soukdev.json`) ; un client en commande une copie, paie par Mobile Money, et la copie tourne sur
son propre sous-domaine ; Souk.dev facture l'hébergement chaque mois et reverse le prix de
l'appli au dev, sans commission. Autour : l'Échange (discussions, correctifs, copies de test),
les missions, les événements.

## Les services

Tout tourne dans Docker. `docker-compose.yml` (développement) et `docker-compose.prod.yml`
(production, via `scripts/prod.sh`) ont les mêmes services :

| Service | Rôle | Docker ? |
|---|---|---|
| `secrets` | Génère au premier démarrage les secrets internes (PostgreSQL, Reverb) dans un volume | non |
| `db` | PostgreSQL 16 : la plateforme (comptes, applis, factures, grand livre…) | non |
| `init` | Migrations, puis écrit les routes Traefik de la plateforme | non |
| `api` | Laravel 13 (FrankenPHP en production) : l'API JSON, la session, les webhooks | non |
| `worker` | File `paiements,default` : encaissement, webhooks, factures, e-mails | non |
| `moteur` | File `moteur` : cloner, construire, lancer, arrêter, mettre à jour, sauvegarder les copies | **oui** |
| `sondes` | File `sondes` : santé des serveurs, processeur des copies (politique d'abus) | **oui** |
| `analyse` | File `analyse` : Trivy (dépendances) dans un conteneur jetable | **oui** |
| `analyse-secrets` | File `secrets` : gitleaks dans un conteneur jetable, sans réseau | **oui** |
| `ia` | File `ia` : Souk AI (DeepSeek), une analyse de bug à la demande | non |
| `reverb` | WebSocket (Laravel Reverb) : temps réel des écrans | non |
| `scheduler` | Tâches planifiées (facturation quotidienne, rapprochement, nettoyages, sondes) | non |
| `traefik` | Bord HTTP(S) : front, API, Reverb et un routeur par copie (fichiers écrits par le moteur) | non |
| `front` | React : Vite en développement, nginx statique en production | non |

Seuls les services marqués « oui » montent le socket Docker. L'API ne parle jamais à Docker :
quand elle a besoin du moteur (vérifier un dépôt, lancer une copie), elle pose un travail dans
une file et lit le résultat. Chaque file a son service, pour qu'un travail long (une
construction de 5 minutes) ne bloque pas un travail court (une sonde, une analyse de secrets).

## Le chemin d'une requête

```
navigateur ──HTTPS──▶ Traefik ──▶ front (nginx)          https://DOMAINE
                         │
                         ├─────▶ api (Laravel)            https://api.DOMAINE
                         ├─────▶ reverb (WebSocket)       wss://reverb.DOMAINE
                         └─────▶ copie n° x (conteneurs)  https://x.DOMAINE_COPIES
```

- **Session** : Sanctum en mode SPA (cookie de session + jeton CSRF), cookie valable pour
  `.DOMAINE`. Les copies vivent sur un **autre domaine** (`DOMAINE_COPIES`) : elles font tourner
  le code de leurs auteurs et ne doivent jamais recevoir ce cookie ; l'API refuse de servir si
  la configuration les mélange (`App\Securite\IsolementCopies`).
- **Proxy de confiance** : Traefik joint l'API par la boucle locale ; Laravel lit l'adresse et
  le protocole du visiteur dans `X-Forwarded-*` (limites de débit par visiteur, liens signés).
- **En-têtes** : HSTS, nosniff, refus des cadres (Traefik) ; CSP du front écrite au build, ses
  deux scripts en ligne autorisés par empreinte (`frontend/scripts/csp-nginx.mjs`).
- **Contrat** : `openapi.yaml` décrit chaque route ; le front en tire ses types
  (`npm run types`) et `ContratRoutesTest` vérifie que les routes Laravel et le contrat
  concordent.

## Le backend (`backend/app`)

Rangé par domaine, pas par couche :

| Dossier | Contenu |
|---|---|
| `Comptes` | GitHub (devs), lien magique (clients), URL de retour du front |
| `Echange` | Discussions, messages, correctifs, copies de test |
| `Moteur` | Clonage, validation de `soukdev.json`, vérification de `docker-compose.yml`, durcissement, limites par formule, lancement, sonde du service web, routes Traefik, machines |
| `Paiement` | Passerelles SasPay (réelle et simulée), facturation, cycle mensuel, encaissement, grand livre, reversements, rapprochement |
| `Securite` | Analyse des dépôts (gitleaks, Trivy), isolement des copies, suspension pour abus |
| `Reputation` | XP, niveaux, badges, Souk Score |
| `Ia` | Souk AI (DeepSeek, et un simulateur pour le développement) |
| `BackendIntegre` | PostgreSQL et PostgREST fournis à une copie, connexion de ses utilisateurs par SMS ou e-mail |
| `Exploitation` | Battements des tâches planifiées, surveillance |
| `Jobs` | Les travaux des files (un par action : `LancerDeploiement`, `SuspendreCopie`…) |
| `Http` | Contrôleurs fins, requêtes de validation, middlewares (admin, compte actif, idempotence) |
| `Policies` | Qui peut faire quoi sur chaque objet (anti-IDOR, testé par `AntiIdorTest`) |

Règles transverses : chaque service externe passe par un adaptateur avec une version simulée
(SasPay, DeepSeek, SMS, Resend) ; les reversements passent toujours par l'adaptateur inactif
tant que `REVERSEMENTS_REELS` n'est pas vrai ; toute route qui déplace de l'argent exige un
`Idempotency-Key` ; les journaux ne contiennent ni clé, ni jeton, ni numéro de téléphone complet.

## Le moteur : d'un dépôt à une copie en ligne

1. **Publication** : le dépôt est cloné (sans shell, sans liens symboliques, configuration Git
   neutre), `soukdev.json` validé, `docker-compose.yml` vérifié par liste blanche
   (`VerificateurCompose` : pas de `privileged`, de volume de l'hôte, de port publié, de
   réseau partagé, au plus six services, `deploy` limité aux limites). gitleaks cherche les
   secrets avant toute publication ; Trivy note les dépendances ensuite (badge de sécurité).
   La mémoire consommée est mesurée en tâche de fond et donne la formule recommandée.
2. **Commande et paiement** : la copie naît en `attente_paiement` ; elle ne démarre qu'au
   `SUCCESS` de SasPay, vérifié par son API (jamais sur la seule foi d'un webhook).
3. **Lancement** (`LancerDeploiement`, file `moteur`) : clone du commit vérifié, `.env` de la
   copie (variables du client, secrets générés), fichier de la plateforme
   `docker-compose.soukdev.yml` (formule : mémoire, processeur, processus ; `cap_drop: ALL`,
   `no-new-privileges`, journaux plafonnés, réseau propre), revérification de la configuration
   finale (`docker compose config`), placement sur la machine la plus libre, `up`, sonde du
   service web, puis route Traefik vers son adresse.
4. **Vie** : facture chaque mois, suspension après 7 jours d'impayé (conteneurs arrêtés,
   volumes gardés, page neutre), réactivation au paiement, arrêt définitif à 30 jours ; mises
   à jour avec sauvegarde et retour arrière ; sauvegarde quotidienne ; politique d'abus
   (minage, hameçonnage).

États d'une copie : `attente_paiement → en_file → construction → demarrage → en_ligne`, et
`suspendu`, `arret_en_cours`, `arrete`, `echec`.

## L'argent

Le montant payé vient toujours du serveur (`config/facturation.php`). Chaque mouvement est une
écriture du **grand livre** (`ecritures`), qui ne se modifie ni ne se supprime : encaissement
client, part du dev, part hébergement, frais, reversement, avoir. Les soldes des devs, la marge
et les écarts de l'admin sont des sommes d'écritures. Détail : [paiement.md](paiement.md).

## Le temps réel

Reverb pousse les changements ; les écrans relisent l'API au moindre doute (un événement manqué
ne casse rien). Canaux :

- publics : `discussions` (nouvelles discussions du fil), `discussion.{id}` (messages,
  correctifs, copie de test, analyse Souk AI) ;
- privés, autorisés par la session : `utilisateur.{id}` (notifications, factures),
  `deploiement.{id}` (étapes d'une copie), `mission.{id}` (fil privé d'une mission). Un compte
  suspendu n'en ouvre aucun.

Côté front, `laravel-echo` et `pusher-js` ne sont chargés qu'au premier canal ouvert. Détail
des événements : [REVERB.md](../REVERB.md).

## Le front (`frontend/src`)

- `app/` : routeur, mise en page, en-tête, navigation, connexion Reverb, préparation CSRF.
- `features/<domaine>/` : un dossier par domaine (catalogue, échange, paiements, déploiements,
  revenus, comptes, admin…), avec ses écrans et son `api.ts` (appels typés par le contrat).
- `ui/` : le système de design (tokens dans `index.css`, composants accessibles) ; un écran
  n'utilise que lui ([design.md](design.md)).
- Chargement : chaque route est un morceau à part, préchargé dès le HTML pour la page demandée
  (`vite.precharger.ts`) ; une coquille statique s'affiche avant le JavaScript.

## Les tests

- `backend/tests` : PHPUnit (650 tests), dont le contrat, l'anti-IDOR, le moteur avec un
  Docker simulé, l'argent de bout en bout avec la passerelle simulée.
- `frontend/src/**/*.test.tsx` : Vitest et Testing Library.
- `frontend/e2e` : Playwright contre la plateforme Docker réelle (copies vraiment construites à
  partir des dépôts de démo servis en miroir local), audit axe sur chaque écran ; captures
  3 tailles × 2 thèmes (`npm run e2e:captures`).
- CI GitHub : Pint, PHPUnit, types à jour avec le contrat, ESLint, Vitest, build (`tsc` et Vite), licences.
