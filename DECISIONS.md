# Décisions — Souk.dev

Décisions prises par l'équipe, validées par Oktav. Mis à jour au fil du projet.

## Principe : aucune interaction payante imposée

**La plateforme aide tout le monde, pas seulement ceux qui déploient chez Systalink.** Poster un problème, lire un dépôt, proposer un correctif (URL d'une branche) : tout cela est gratuit et ne déclenche aucun déploiement.

- **Publier** = mettre la fiche de l'appli dans le store (dépôt, démo, prix). Rien ne tourne, gratuit.
- **Déployer** = faire tourner une copie sur un serveur Datacloud. Ça coûte de l'hébergement : le serveur est payé avant le lancement.
- La **copie de test** reste possible, mais c'est **un choix volontaire du dev**, jamais une condition pour être aidé ni pour proposer un correctif. Joindre un dépôt ou une appli à une discussion ne lance rien automatiquement.

## Fil de la démo

**L'entraide rend l'appli vendable.** Les devs d'abord : le jury voit un dev qui gagne grâce à sa communauté, pas un client qui fait ses courses.

1. Un dev poste un bug (ex. « mon paiement MoMo plante ») avec son dépôt, et éventuellement un lien de démo ou une copie de test.
2. Un autre dev lit le dépôt (et la démo si elle existe), corrige, et propose l'URL de sa branche. S'il le souhaite, il déploie sa version corrigée pour la montrer tourner. L'auteur accepte le correctif (entraide gratuite).
3. L'appli marche : le dev la publie dans le store (ex. 15 000 F/mois).
4. Un client lui demande une version adaptée : mission sur mesure, sans enchères.

Le dev gagne de l'argent uniquement via le prix de ses applis et les missions sur mesure. Corriger un bug dans l'échange n'est pas rémunéré.

## Ordre de construction

1. **Moteur de déploiement** : la partie la plus risquée, et le Store comme l'Échange en dépendent.
2. **Espace d'échange** : cœur du thème, avec la copie de test optionnelle lancée par le moteur.
3. **Publier + Store**.

## Stack

| Couche | Choix | Licence |
|---|---|---|
| Backend | Laravel | MIT |
| Temps réel | Laravel Reverb + Laravel Echo | MIT |
| Frontend | React + Vite, SPA séparée | MIT |
| Auth SPA ↔ API | Laravel Sanctum | MIT |

**Version de PHP : 8.3, partout.** Sur les PC de l'équipe, en CI et sur le serveur (Ubuntu 24.04 l'installe par défaut). `composer.json` fixe la plateforme à 8.3.6 pour que le `composer.lock` reste compatible. Le futur Dockerfile du backend part d'une image `php:8.3`.

Le frontend est une SPA séparée qui appelle l'API Laravel : le front (JS) et le back (PHP) avancent chacun de leur côté, en se mettant d'accord sur le format des réponses de l'API.

Le **contrat d'API** (routes et format des réponses) est écrit par Oktav, au format **OpenAPI** (`openapi.yaml`). Ce fichier sert à :
- le front, pour coder contre une fausse API en attendant le back ;
- le back, pour savoir exactement quoi renvoyer ;
- les tests DAST de Jean-Baptiste, qui peuvent être lancés à partir de ce fichier.

Les **événements temps réel** (Reverb) sont décrits à part dans `REVERB.md`, car OpenAPI ne couvre pas les WebSockets. Leurs payloads reprennent les schémas de `openapi.yaml`.

- Canaux d'un déploiement (`deploiement.{id}`) et d'une mission (`mission.{id}`) : **privés**.
- Canaux des discussions et des événements : **publics**, comme dans l'API.
- Jamais de donnée réservée dans un canal public (ex. le lien d'un événement en ligne).

Écarté :
- microservice Node / Socket.IO (deuxième auth et deuxième déploiement, code non maîtrisé par l'équipe backend) ;
- Inertia (le front devrait coder dans le projet Laravel et dépendrait des contrôleurs PHP).

## Hébergement

- **Un seul produit Datacloud** : un **Serveur cloud** (VPS), qui fait tourner le moteur de déploiement (Docker). Il suffit pour respecter l'obligation du règlement (au moins un produit Datacloud payant). On limite la dépense tant que la victoire n'est pas acquise.
- Configuration envisagée : offre Entreprise, **2 vCPU, 8 Go de RAM, 50 Go**, Ubuntu 24.04 LTS. Commande en attente du code promo de 40 %.
- **Base de données** : dans un conteneur Docker **sur le VPS Datacloud**, sur le réseau Docker interne, **sans port ouvert** vers l'extérieur. Dimensionné pour un hackathon, pas pour des milliers d'utilisateurs.
- **Stockage** : sur le serveur distant d'Oktav.
- Disque limité : nettoyer régulièrement les anciennes images Docker (`docker system prune`).

## Organisation du code

Un seul dépôt : `frontend/` (React) + `backend/` (Laravel).

**Types partagés** : `openapi.yaml` est la seule source.
- Front : types TypeScript générés avec `openapi-typescript` (MIT). Si le contrat change, on régénère et TypeScript signale ce qui casse.
- Back : pas de génération ; les tests vérifient que chaque réponse respecte le contrat avec `osteel/openapi-httpfoundation-testing` (MIT). La CI bloque tout écart.

## Contrat d'API : ressources du MVP

On liste uniquement les routes du MVP, ressource par ressource : **Comptes**, **Applis**, **Déploiements**, **Discussions** (avec canaux et étiquettes), **Correctifs**, **Missions**, **Événements**.

### Comptes

- **Choix à l'entrée** : « Développeur / informaticien » ou « Entreprise, agence, particulier ». Ce choix oriente la connexion et la page d'arrivée :
  - Développeur → connexion **GitHub** (Laravel Socialite, MIT) → arrivée sur l'Échange ;
  - Entreprise, agence, particulier → connexion par **lien magique par email** → arrivée sur le Store.
- **Un seul type de compte** : le choix d'entrée est une porte, pas une étiquette définitive. Le compte porte un champ `profil` (`dev` ou `client`, sert à la redirection) et `github_lie` (oui/non). Un client peut lier son GitHub plus tard sur le même compte.
- **GitHub est obligatoire uniquement pour publier une appli ou proposer un correctif** (il faut lier un dépôt).
- Le profil du dev affiche ses dépôts, leurs étoiles et leurs README, récupérés via GitHub.
- **Dépôts publics uniquement** pour le MVP : on ne demande jamais la permission `repo`, qui donne lecture et écriture sur tout le code du dev. Un jeton qui fuit ne peut donc pas modifier son code.
- Un compte **admin** (équipe) peut supprimer tout contenu abusif.
- Routes : `GET /auth/github`, `GET /auth/github/callback`, `POST /auth/lien-magique`, `GET /auth/lien-magique/{token}`, `POST /logout`, `GET /me`, `GET /devs/{username}` (profil public). Les routes de connexion sont des routes web Laravel (hors `/api`).
- Écarté pour le MVP : connexion Google (pas de dépôts, d'étoiles ni de README ; une connexion de plus à coder et sécuriser).
- Les clients ne sont pas un type d'entreprise en particulier : entreprise, association, agence, particulier…

### Applis

- Routes : `GET /apps` (le store), `GET /apps/{id}` (détail : README, démo, prix, auteur), `POST /apps` (publier : dépôt, lien de démo, prix), `PATCH /apps/{id}` (modifier), `DELETE /apps/{id}` (retirer).
- `POST /apps` **valide `soukdev.json` immédiatement** et refuse la publication s'il est invalide : le dev voit l'erreur en publiant, pas un client en déployant.
- **Vérifier avant de publier** : `POST /apps/verification` contrôle le dépôt (public, `docker-compose.yml` présent, `soukdev.json` valide) sans rien publier, et renvoie ce que la plateforme a lu (service web, backend, variables client). Le formulaire de publication l'appelle dès que le dev colle l'adresse du dépôt.
- **Captures** : 1 à 5 images (PNG, JPEG ou WebP, **5 Mo maximum** chacune), envoyées avec le formulaire de publication (`POST /apps` en `multipart/form-data`). La première sert de couverture sur la carte du store. Pour les changer : `POST /apps/{id}/captures` remplace toutes les captures (POST, car PHP ne lit pas le multipart en PATCH). Stockées sur le serveur d'Oktav.
- **Stack** : 1 à 8 technos, choisies dans une **liste fixe** (`GET /technos`), avec `autre` pour celles qui n'y sont pas.
- Recherche dans le store : `GET /apps?q=paiement` (nom et description), `?gratuit=true` et `?techno=laravel`.

### Déploiements

- Routes : `POST /apps/{id}/deployments` (lancer, avec les variables du client), `GET /deployments/{id}` (état et URL), `GET /deployments` (mes déploiements), `DELETE /deployments/{id}` (arrêter).
- Un déploiement dure plusieurs minutes : le `POST` répond **tout de suite** avec l'état `en_file`, le travail tourne en tâche de fond (file d'attente Laravel).
- Reverb pousse chaque changement d'état au front : `en_file` → `construction` → `demarrage` → `en_ligne` ou `echec`. Un déploiement arrêté passe à `arrete`.

### Discussions

- Routes : `GET /discussions` (le fil), `POST /discussions`, `GET /discussions/{id}`, `PATCH` et `DELETE /discussions/{id}`, `GET /discussions/{id}/messages` (paginé), `POST /discussions/{id}/messages`, `PATCH` et `DELETE /messages/{id}`.
- Joindre son projet à une discussion, trois options cumulables :
  - `app_id` : une appli déjà publiée dans le store ;
  - `depot_url` : n'importe quel dépôt GitHub public, même non publié ;
  - `demo_url` : l'appli déjà déployée ailleurs (ex. Vercel), pour que les autres devs voient le bug tourner sans cloner ni installer. Utile notamment pour un projet frontend seul.
- La `demo_url` s'affiche dans un cadre intégré (iframe) **isolé** (`sandbox`), avec un bouton « ouvrir dans un onglet » pour les sites qui refusent d'être intégrés.
- **Copie de test** : seulement si le dev la demande explicitement (`POST /discussions/{id}/copie-test`), à partir du `depot_url` ou de l'appli jointe. Elle exige un `soukdev.json`.
- **Une seule ressource** pour l'échange central et la discussion propre à chaque appli : filtre `GET /discussions?app=42`.
- **Canaux** : une discussion est **soit dans un canal** (ex. `#laravel`, `#mobile-money`), **soit sur la page d'une appli**, jamais les deux. Sans appli, le canal est obligatoire. Filtre : `GET /discussions?canal=laravel`. Routes : `GET /canaux`, `POST /canaux`.
- **Sujets** : une **liste fixe** d'étiquettes (`bug`, `question`, `tuto`, `projet`, `entraide`), lisible avec `GET /etiquettes`. Filtre : `GET /discussions?etiquette=bug`.
- **Résolue** : champ `resolue`. Elle le devient quand l'auteur accepte un correctif, ou quand il la marque résolue lui-même (`PATCH`, même sans correctif, ex. il a trouvé tout seul). Il peut la rouvrir. Filtre : `GET /discussions?statut=ouvertes|resolues`.
- L'**auteur** peut modifier ou supprimer ses discussions et messages ; l'**admin** peut supprimer tout contenu abusif.

### Correctifs

- Routes : `POST /discussions/{id}/correctifs` (proposer), `GET /discussions/{id}/correctifs`, `POST /correctifs/{id}/accepter`, `POST /correctifs/{id}/deploiement` (optionnel).
- Un correctif est **l'URL d'une branche ou d'un fork GitHub**, avec éventuellement une `demo_url`. Le déployer pour montrer la version corrigée qui tourne est **optionnel** (choix du dev).
- **Un seul correctif accepté** par discussion. Les autres restent visibles.
- Pas de permission `repo` : « accepter » ne fusionne rien, l'auteur fusionne lui-même sur GitHub.

### Missions

Demande de version sur mesure d'un client à l'auteur d'une appli (§5.5 du brief). Pas d'enchères, pas de mise en concurrence.

- **Ressource à part, privée** : visible uniquement par le client et l'auteur, car elle contient des informations privées (besoins, budget, délais). Les discussions, elles, sont publiques.
- Routes : `POST /apps/{id}/missions` (envoyer la demande), `GET /missions` (mes missions), `GET /missions/{id}`, `GET /missions/{id}/messages`, `POST /missions/{id}/messages`.

### Événements

Rencontre avec date et inscription, en ligne (avec lien) ou en présentiel (avec lieu).

- Routes : `GET /events`, `POST /events` (titre, date, type, lieu ou lien), `GET /events/{id}`, `PATCH` et `DELETE /events/{id}`, `POST /events/{id}/inscription`, `DELETE /events/{id}/inscription`, `GET /events/{id}/participants`, `GET /events/{id}/avis`, `POST /events/{id}/avis`.
- Un avis ne peut être laissé que par un **inscrit**, et seulement **après la date** de l'événement. Un seul avis par dev.
- Le lien en ligne n'est visible **que par les inscrits**.
- La **liste des participants** n'est visible que par les inscrits et l'organisateur. Le **nombre** d'inscrits est public.
- L'**organisateur** peut modifier ou supprimer son événement ; supprimer un événement **prévient les inscrits**. L'admin peut supprimer un événement abusif.

### Formats de données

- Prix d'une appli : **entier en FCFA**, par mois ; `0` = gratuit.
- Avis d'un événement : **note de 1 à 5**, commentaire optionnel.
- **Pagination** par numéro de page (`?page=2&per_page=20`, 50 maximum), au format natif des API Resources Laravel : la liste dans `data`, plus `links` et `meta`. Concerne les applis, déploiements, discussions, messages, correctifs, missions, événements, participants et avis.
- **Doublons** (deuxième avis, deuxième inscription, deuxième correctif accepté) : réponse `409`.

### Sécurité de l'API

- **CSRF Sanctum** : la SPA appelle d'abord `GET /sanctum/csrf-cookie`, puis renvoie l'en-tête `X-XSRF-TOKEN` à chaque requête qui modifie quelque chose. Jeton absent ou expiré : `419`.
- **Limites de débit** (au-delà : `429`) :
  - **3 déploiements par heure** et par compte (copies de test et déploiements de correctifs compris) ;
  - **10 par heure** pour les discussions, correctifs, missions et vérifications de dépôt ;
  - **60 requêtes par minute** pour tout le reste (valeur par défaut de Laravel).
- Canaux privés Reverb autorisés par `POST /broadcasting/auth` (route web Laravel), avec la session Sanctum.
- Cookie de session : `soukdev_session` (variable `SESSION_COOKIE`).

## Format de `soukdev.json`

Fichier à la racine du dépôt, à côté du `docker-compose.yml`. Schéma de validation : `soukdev.schema.json`.

```json
{
  "version": 1,
  "service_web": { "nom": "front", "port": 3000 },
  "backend": "propre",
  "variables": [
    { "nom": "APP_NOM", "source": "client", "libelle": "Nom de votre structure", "requis": true },
    { "nom": "DB_PASSWORD", "source": "plateforme" },
    { "nom": "DEVISE", "source": "fixe", "valeur": "FCFA" }
  ]
}
```

- `service_web` : le conteneur du `docker-compose.yml` exposé par le reverse proxy, et son port. Tout le reste reste fermé.
- `backend` : `propre` (la base est un service du `docker-compose.yml`) ou `integre` (la plateforme fournit la base et l'API).
- `variables` : chaque variable a une `source` : `client` (remplie au déploiement, avec `libelle` et `requis`), `plateforme` (générée, ex. mot de passe unique par copie) ou `fixe` (avec `valeur`).
- `migrations` : chemin des fichiers de migration, **obligatoire si `backend` vaut `integre`**, absent sinon. Dans ce cas, pas de `DB_PASSWORD` : la plateforme injecte l'URL et la clé du backend.

## Backend intégré

Fait partie du MVP dans sa **version limitée** (§9 du brief) : une base **PostgreSQL**, une API automatique **PostgREST** et une connexion par SMS. Seule la version complète est hors MVP. Fonctionnement comme Supabase : le dev crée un backend de test, code autour, met ses tables dans des migrations ; au déploiement, la plateforme crée un backend neuf, applique les migrations et injecte la nouvelle URL et la nouvelle clé.

## Rôles

| Membre | Rôle |
|---|---|
| Oktav | Architecture, squelette du projet, revue de tout le code (front et back), merge des branches, coordination |
| Hope | Frontend React : tous les écrans |
| Mourchid | Backend Laravel : moteur de déploiement, puis Publier + Store une fois le moteur stable |
| Wasfade | Backend Laravel : API Échange, Reverb, comptes utilisateurs |
| Jean-Baptiste | CI/CD, SAST, DAST, sécurité, tests ; vérification automatique des licences en CI (`composer licenses`, `license-checker`) ; reverse proxy ; appui Docker et VPS pour le moteur |

## Revue et merge

- **La CI bloque** : pas de merge tant qu'elle n'est pas verte (tests, licences, respect du contrat).
- **Oktav valide le reste** : architecture, logique, respect des décisions. Lui seul approuve et merge.
- Protection de branche sur `main` : CI verte + approbation d'Oktav obligatoires.
- PR petites : une PR = une tâche qui se relit vite.

## Planning

- **Semaine 1** (6 → 12 octobre) : détail dans [`SEMAINE_1.md`](SEMAINE_1.md).

## Moteur de déploiement

- **Objectif** : on lui donne un lien GitHub, il rend une URL où l'appli tourne.
- **Étape 1** : cloner le dépôt, lire et **valider** `soukdev.json` (fichier non fiable, écrit par un dev inconnu) avec `soukdev.schema.json`.
- **Étape 2** : générer un `.env` par copie. Le dev déclare chaque variable dans `soukdev.json` avec sa source :
  - remplie par le client (ex. `APP_NOM`) ;
  - générée par la plateforme (ex. `DB_PASSWORD`) ;
  - valeur fixe choisie par le dev (ex. `DEVISE=FCFA`).
- **Étape 3** : lancer avec `docker compose -p <copie>` (réseau, volumes et conteneurs isolés par copie), derrière un reverse proxy avec un sous-domaine par copie (ex. `client-a.soukdev.com`), qui n'expose que `service_web`.
- **Machine cible** : le moteur déploie sur « une machine » sans savoir laquelle. VPS partagé pour la démo, un VPS par client si Datacloud fournit une API de création.

## Points ouverts

- Datacloud permet-il de créer des VPS ou des conteneurs par API ? (question posée à Systalink, en attente)
- **Paiement du serveur** avant un déploiement : la plateforme peut-elle déclencher le paiement Datacloud (API de facturation) ? Un paiement simulé affaiblirait le critère « utilisable ». À demander à Systalink.
- Service d'envoi des emails (liens magiques, annulation d'événement) : à choisir par l'équipe.
- Un dev qui a déjà pris un serveur pour une copie de test pourrait-il le réutiliser pour déployer son appli ensuite ? À étudier.
- Vérifier que l'appli est vraiment prête avant de donner son URL (étape 4 du moteur).
