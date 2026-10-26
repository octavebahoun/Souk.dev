# Décisions — Souk.dev

Décisions prises par l'équipe, validées par Oktav. Mis à jour au fil du projet.

## Principe : aucune interaction payante imposée

**La plateforme aide tout le monde, pas seulement ceux qui déploient chez Systalink.** Poster un problème, lire un dépôt, proposer un correctif (URL d'une branche) : tout cela est gratuit et ne déclenche aucun déploiement.

- **Publier** = mettre la fiche de l'appli dans le store (dépôt, démo, prix). Rien ne tourne, gratuit.
- **Déployer** = faire tourner une copie sur le serveur Datacloud de Souk.dev, partagé entre toutes les copies (voir [Hébergement](#hébergement)). Ça consomme de la mémoire et du processeur sur ce serveur : d'où les limites de débit.
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

**Version de PHP : 8.4, partout.** Sur les PC de l'équipe, en CI et sur le serveur (le backend y tourne dans Docker, image `php:8.4`). La plateforme fixée dans `composer.json` et la version de la CI suivent la même version.

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

- **Un seul serveur, partagé** : un **Serveur cloud** (VPS) Datacloud fait tourner la plateforme et toutes les copies (copies clients, copies de test, versions corrigées), chacune isolée dans son projet Docker. C'est notre produit Datacloud payant, exigé par le règlement.
- **Pour le concours : un VPS de 8 Go**, Ubuntu 24.04 LTS, commandé la semaine du 13 octobre avec la réduction de 40 % (valable sur la première période seulement). La plateforme prend 2 à 3 Go, ce qui laisse 5 à 8 copies pour la démo.
- **Ensuite : un VPS de 16 Go** (6 cœurs, 88,80 € par mois au prix normal, soit environ 58 250 F), pris par Systalink si nous sommes retenus, ou par nous sinon.
- **Plusieurs serveurs** : quand un VPS est plein, on en ajoute un autre à la liste des serveurs du moteur. Chaque nouvelle copie part sur le serveur qui a le plus de mémoire libre.
- **Pourquoi pas un VPS par client** : Datacloud fournit bien une API pour créer des VPS, mais chaque VPS serait un serveur de plus à maintenir (mises à jour, failles) pendant des années, un travail que le client ne paie pas.
- **Cible après le concours : le serverless Datacloud**. Datacloud gère le système, et chaque copie démarre et grandit selon son trafic. Son API n'est pas encore ouverte : Datacloud l'annonce d'ici un à deux mois. Le moteur ne change pas pour autant : il déploie sur « une machine » sans savoir laquelle (voir [Moteur de déploiement](#moteur-de-déploiement)).
- **Échange avec Systalink (9 octobre)**, avec Abdoulaye Sadio Barry, directeur des opérations : API VPS disponible, API serverless à venir, paiements gérés par Paxity, entité du groupe ([paxity.io](https://paxity.io)). Ils ont apprécié le projet et veulent en reparler après le concours, que nous soyons retenus ou non. Les contraintes rencontrées et l'architecture cible iront dans le brief de présentation.
- **Base de données** : dans un conteneur Docker **sur le VPS Datacloud**, sur le réseau Docker interne, **sans port ouvert** vers l'extérieur. Dimensionné pour un hackathon, pas pour des milliers d'utilisateurs.
- **Stockage** : sur le serveur distant d'Oktav.
- Disque limité : nettoyer régulièrement les anciennes images Docker (`docker system prune`).

## Business model

- **Le client paie deux montants par mois** : le prix de l'appli, fixé par le dev, et l'hébergement de sa copie, fixé par Souk.dev.
- **Le prix de l'appli va entièrement au dev**. Aucune commission sur ses ventes.
- **Souk.dev gagne sur l'hébergement** : il paie les serveurs Datacloud et leur maintenance, et facture chaque copie selon sa taille.
- **Prix de l'hébergement**, calculés sur le prix normal du VPS de 16 Go (environ 4 480 F par Go et par mois, 13 Go libres pour les copies), avec une marge de 50 % :

| Formule | Mémoire | Processeur | Coût pour Souk.dev | Prix au client |
| --- | --- | --- | --- | --- |
| Petite | 512 Mo | 0,5 cœur | 2 240 F/mois | 3 500 F/mois |
| Moyenne | 1 Go | 1 cœur | 4 480 F/mois | 7 000 F/mois |
| Grande | 2 Go | 2 cœurs | 8 960 F/mois | 13 500 F/mois |

- **La formule est choisie par le dev** à la publication (champ `taille` de l'appli). Le formulaire affiche clairement le prix d'hébergement que paiera le client. Le moteur applique les limites de mémoire et de processeur à chaque copie.
- **Mesure de la mémoire** : après la publication, la plateforme lance l'appli quelques minutes en tâche de fond et mesure son pic de mémoire (champ `mesure`). Elle recommande la formule au-dessus du pic : une appli qui monte à 450 Mo est recommandée en Moyenne, pas en Petite.
- **Un changement de taille ne touche que les nouveaux clients** : chaque copie garde la formule et le prix de son déploiement (`taille` du déploiement). Si une copie manque de mémoire, c'est au dev d'optimiser son appli.
- **Systalink gagne aussi** : plus il y a de copies, plus Souk.dev loue de serveurs Datacloud, puis de serverless.
- Les montants seront recalculés si le prix du VPS change.
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
- **Prix** : `prix` en FCFA, avec `type_prix` : `mensuel` (payé chaque mois) ou `unique` (payé une seule fois). L'hébergement reste mensuel dans les deux cas.
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

Demande de version sur mesure d'un client à l'auteur d'une appli (§5.5 du brief), ou recrutement direct d'un dev depuis l'espace entreprises. Pas d'enchères, pas de mise en concurrence.

- **Ressource à part, privée** : visible uniquement par le client et l'auteur, car elle contient des informations privées (besoins, budget, délais). Les discussions, elles, sont publiques.
- Routes : `POST /apps/{id}/missions` (envoyer la demande), `POST /devs/{username}/missions` (recruter un dev sans appli), `GET /missions` (mes missions), `GET /missions/{id}`, `GET /missions/{id}/messages`, `POST /missions/{id}/messages`, `POST /missions/{id}/note` (le client termine la mission et note l'auteur).

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
  - **10 par heure** pour les discussions, correctifs, missions, vérifications de dépôt et relances de l'analyse de sécurité ;
  - **3 analyses Souk AI par jour** et par dev ;
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

## Ajouts proposés par l'équipe (7 octobre)

Validés par Oktav. L'équipe s'engage à les livrer d'ici le 25 octobre, **après** le cœur du MVP : le moteur et l'échange passent d'abord. Chaque ajout passera par le contrat (`openapi.yaml`) avant d'être codé.

1. **Profil enrichi** : compétences, pays, disponibilité freelance, contributions, bugs résolus, note de la communauté, badges.
    - **Déclaré par le dev** avec `PATCH /me` : `competences` (technos de la liste du store, 10 maximum), `specialites` (`frontend`, `backend`, `mobile`, `devops`, `securite`, `data`), `disponible` (freelance ou collaboration), en plus du pays et de la bio.
    - **Calculé par la plateforme** (`stats`) : bugs résolus (correctifs acceptés), applis publiées, missions terminées, note moyenne et nombre de notes.
    - **Note de la communauté** : donnée par **le client à la fin d'une mission**, de 1 à 5, avec un commentaire facultatif (`POST /missions/{id}/note`, une seule fois, qui passe la mission à `terminee`).
    - Les badges et l'XP viendront avec l'ajout « réputation ».
2. **Bug structuré** : technologie, code concerné, erreur obtenue, comportement attendu, niveau de difficulté.
    - Un bloc `bug` sur une discussion, **seulement avec l'étiquette `bug`**. Obligatoires : `erreur_obtenue` et `comportement_attendu`. Facultatifs : `techno` (liste du store), `code`, `difficulte` (`facile`, `moyen`, `difficile`), **choisie par l'auteur**. Filtre `GET /discussions?difficulte=facile`.
3. **Réputation** : des points (XP) pour un bug résolu, une réponse utile, un projet publié, une aide, un événement. Pas de votes.
    - **Règle de fond** : l'XP ne récompense que ce qui a été validé par quelqu'un d'autre. Calculée par la plateforme, jamais modifiable à la main.
    - **Barème** : correctif accepté **+50** ; appli publiée **+100**, et **+50** avec le badge « Security Checked » ; mission terminée **+100**, plus **10 × la note** du client ; événement organisé, une fois passé et non annulé, **+50**.
    - **Niveaux** : Débutant (0), Contributeur (200), Confirmé (1 000), Expert (3 000).
    - **Badges** : Premier correctif (1 correctif accepté), Chasseur de bugs (10 bugs résolus), Publié (1 appli), Fiable (note ≥ 4,5 sur au moins 3 missions), Sécurisé (1 appli « Security Checked »), Organisateur (1 événement passé).
    - Contrat : champ `reputation` (`xp`, `niveau`, `badges`) dans le profil.
4. **Souk Score** : un score global sur 1000, calculé à partir de la réputation, des projets, de la sécurité et de l'activité.
    - **Rôle** : l'XP cumule tout depuis l'arrivée ; le Souk Score mesure la **qualité et l'activité récente**. Un nouveau qui travaille bien peut dépasser un ancien devenu inactif.
    - **Calcul**, en 5 parts plafonnées : **entraide** 25 par bug résolu (250 max) ; **projets** 50 par appli publiée (200 max) ; **clients** note moyenne / 5 × 150, plus 25 par mission terminée (100 max), soit 250 max ; **sécurité** part des applis « Security Checked » × 150 ; **activité** 15 par action validée sur 30 jours (150 max).
    - Les compétences déclarées ne comptent pas, puisque personne ne les vérifie.
    - Contrat : champ `souk_score` dans le profil, avec le total et le détail des cinq parts.
5. **Tableau de bord du dev** : vues, déploiements, revenus, bugs résolus, note.
    - Route `GET /me/tableau-de-bord` : totaux et détail par appli (vues, copies actives, déploiements, revenu). Le profil (stats, réputation, Souk Score) vient de `GET /me`.
    - **Vues** : un visiteur compté une fois par jour sur la fiche d'une appli.
    - **Revenus estimés**, affichés comme tels, tant que le paiement via Datacloud n'est pas en place : appli mensuelle = copies actives × prix (par mois) ; appli à paiement unique = déploiements × prix (au total).
6. **Souk AI** : une analyse IA du bug, affichée à côté des solutions de la communauté, sans les remplacer.
    - **À la demande** : l'auteur clique sur « Demander l'avis de Souk AI », seulement sur une discussion avec l'étiquette `bug`. Il est prévenu que son bug et son code partent chez un fournisseur extérieur.
    - **Modèle** : Claude Opus 5.5 (`claude-opus-5-5`), appelé par le backend avec le SDK PHP officiel d'Anthropic. La clé API reste dans le `.env` du serveur, jamais dans le front. Environ 0,08 $ par analyse.
    - **Limite** : 3 analyses par jour et par dev, pour garder la facture sous contrôle.
    - **Affichage** : visible de tous, sous l'étiquette « 🤖 Analyse IA », à côté des correctifs. Elle ne résout jamais une discussion. Si le modèle refuse ou échoue : « analyse indisponible ».
    - Contrat : `POST /discussions/{id}/analyse-ia`, champ `analyse_ia` sur la discussion (`statut` `en_cours`, `prete` ou `echec`, `texte`, `cree_le`).
7. **Recherche avancée** : filtres par pays, prix, type de projet, niveau, open source ou commercial.
    - Nouveaux filtres sur `GET /apps` : `categorie`, `pays` (de l'auteur), `prix_max`, `type_prix`, et le tri `tri` (`recent`, `prix_croissant`, `prix_decroissant`).
    - **Catégorie** choisie à la publication, dans une liste fixe (`GET /categories`) : `commerce`, `gestion`, `education`, `sante`, `finance`, `association`, `restauration`, `autre`.
    - Pas de filtre « open source ou commercial » (`?gratuit=true` existe déjà), ni « déployable » (toutes les applis le sont), ni « niveau » (il ne s'applique pas à une appli).
8. **Communautés africaines** : des canaux par pays et par techno (ex. « Laravel Afrique de l'Ouest »).
    - **Pays sur le profil**, facultatif : champ `pays` (code ISO, ex. `BJ`), tous les pays acceptés (diaspora comprise). Modifiable avec `PATCH /me`, qui sert aussi pour la bio.
    - **Canaux par pays** : un canal peut avoir un `pays` (ex. `#laravel-benin` → `BJ`), filtre `GET /canaux?pays=BJ`. Un canal général ou régional n'a pas de pays.
9. **Espace entreprises** : chercher un dev disponible, le recruter, demander une personnalisation, en plus des missions. Contacter un dev reste toujours gratuit.
    - **Chercher un dev** : `GET /devs`, filtres `disponible`, `competence`, `specialite` et `pays`, trié par Souk Score décroissant.
    - **Recruter** sans passer par une appli : `POST /devs/{username}/missions`. C'est la même mission, privée, avec `app_id` vide.
    - **Personnaliser** une appli : la mission existante, `POST /apps/{id}/missions`.
10. **Sécurité avant publication**, gratuite puisqu'elle protège les clients. Trois niveaux :
    - **Bloquant** (publication refusée en `422`) : un secret dans le dépôt (gitleaks, MIT) ou un `docker-compose.yml` dangereux (`privileged`, volumes de l'hôte, `network_mode: host`, ports publiés).
    - **Avertissement** : des dépendances avec des failles connues (Trivy, Apache 2.0). L'appli est publiée avec le badge orange « Problèmes détectés » et le rapport.
    - **Rien trouvé** : badge vert « Security Checked ».
    - Contrat : champ `securite` sur une appli (`en_cours`, `verifie`, `problemes`), filtre `GET /apps?securite=true`, relance par l'auteur avec `POST /apps/{id}/securite`. Outils interdits par leur licence : Semgrep (LGPL), Hadolint (GPL).
11. **Paiement unique** : le dev choisit le prix de son appli, **mensuel** (ex. 15 000 F/mois) ou **unique** (ex. 150 000 F, payé une fois). L'hébergement reste mensuel. Contrat : champ `type_prix` (`mensuel` ou `unique`) sur une appli.
12. **Écran « mes copies »** : une entreprise ou une agence voit toutes ses copies déployées au même endroit. Gratuit.
    - Contrat : `GET /deployments?type=client`, et chaque copie porte `app_nom` pour s'afficher sans recharger l'appli. L'échéance attendra que le paiement via Datacloud soit réglé.

**Écarté** : le vote pour la meilleure solution (l'auteur accepte un correctif), l'achat du code source (le client déploie une copie), toute commission sur les ventes, le premium pour les devs. **Reporté** : l'abonnement entreprises, tant que le paiement via Datacloud n'est pas réglé.

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
- **Semaine 2** (13 → 19 octobre) : en préparation, dans [`SEMAINE_2.md`](SEMAINE_2.md).

## Moteur de déploiement

- **Objectif** : on lui donne un lien GitHub, il rend une URL où l'appli tourne.
- **Étape 1** : cloner le dépôt, lire et **valider** `soukdev.json` (fichier non fiable, écrit par un dev inconnu) avec `soukdev.schema.json`.
- **Étape 2** : générer un `.env` par copie. Le dev déclare chaque variable dans `soukdev.json` avec sa source :
  - remplie par le client (ex. `APP_NOM`) ;
  - générée par la plateforme (ex. `DB_PASSWORD`) ;
  - valeur fixe choisie par le dev (ex. `DEVISE=FCFA`).
- **Étape 3** : lancer avec `docker compose -p <copie>` (réseau, volumes et conteneurs isolés par copie), derrière un reverse proxy avec un sous-domaine par copie (ex. `client-a.soukdev.com`), qui n'expose que `service_web`.
- **Machine cible** : le moteur déploie sur « une machine » sans savoir laquelle. Aujourd'hui, le VPS partagé ; plus tard, le serverless Datacloud, quand son API sera ouverte.

## Points ouverts

- **Encaissement** : le client paie Souk.dev (hébergement et prix de l'appli), puis Souk.dev reverse le prix de l'appli au dev. Le prestataire de paiement reste à choisir ; Paxity, entité de Systalink, est envisagé.
- Service d'envoi des emails (liens magiques, annulation d'événement) : à choisir par l'équipe.
- Vérifier que l'appli est vraiment prête avant de donner son URL (étape 4 du moteur).

## Décisions de l'agent

Choix faits par l'agent en exécutant `BRIEF.md` (le brief passe avant ce fichier, voir sa section 2). Une ligne par décision, avec la raison.

### Tranché par le brief (25 octobre)

- **Encaissement** (point ouvert ci-dessus) : SasPay, en Mobile Money et carte. Frais en `ADD_ON` (le client les paie), reversements aux devs sans commission, frais de reversement pris sur la marge de Souk.dev.
- **E-mails** (point ouvert ci-dessus) : Resend, déjà branché.
- **Vérifier que l'appli est prête** (point ouvert ci-dessus) : fait, la sonde du service web décide de `en_ligne`.
- **Souk AI** : l'API officielle DeepSeek remplace Claude pour l'instant, derrière l'adaptateur `FournisseurIa`.
- **Revenus du tableau de bord** : réels dès que la facturation tourne, plus « estimés ».
- **Concours CADEV** : terminé ; les règles du concours ne s'appliquent plus, la règle des licences permissives reste.

### Socle (phase 1)

- **2026-10-25 — Branche** : `saas-complet` part de `main`, où la PR #28 (`fin-mvp`) est déjà fusionnée.
- **2026-10-25 — Laravel Boost** (MIT, dev) installé, comme le demande `backend/CLAUDE.md` : règles et skills Laravel pour les agents.
- **2026-10-25 — Dépôts clonés** : `git clone --config core.symlinks=false` et git en `LC_ALL=C`. Un lien symbolique du dépôt vers `backend/.env` ou le `.env` d'une autre copie était lu par le validateur ou envoyé par Docker Compose (faille corrigée).
- **2026-10-25 — Plateforme en conteneurs** : une seule image PHP (FrankenPHP, Alpine) pour l'API, les workers, Reverb et le planificateur. Raison : un seul environnement, du poste de dev au VPS.
- **2026-10-25 — Docker réservé au moteur** : seul le service `moteur` (file `moteur`) reçoit le socket Docker. L'API, exposée à Internet, ne lance jamais de conteneur. Conséquence : `DELETE /deployments/{id}` répond `202` (`arret_en_cours`, puis `arrete` par Reverb) au lieu de `204`/`503` ; contrat mis à jour.
- **2026-10-25 — Sonde du service web** : depuis un conteneur jetable (`curlimages/curl`) branché sur le réseau de la copie. Raison : Docker isole les réseaux entre eux, et la même sonde servira pour une autre machine.
- **2026-10-25 — Secrets internes** (mot de passe PostgreSQL, clés Reverb) : générés au premier `docker compose up` dans un volume, chargés par Laravel au démarrage. Raison : démarrage à froid sans étape manuelle, rien dans le dépôt.
- **2026-10-25 — Autorisation** : une Policy par ressource ; un refus garde sa raison, et une Policy peut répondre `404` pour ne pas révéler qu'une ressource existe (factures, revenus, notifications). Les routes déjà au contrat gardent leur `403`.
- **2026-10-25 — GitHub lié** : contrôlé aussi pour `POST /apps` et `POST /apps/verification` (le contrat l'exigeait, le code non).
- **2026-10-25 — Souk Score de la recherche de devs** : une copie du profil calculé est rangée sur le compte, rafraîchie à chaque action validée et chaque nuit, pour trier sans tout recalculer. Le profil d'un dev reste calculé en direct.

### Paiement (phase 2)

- **2026-10-26 — Passerelle par défaut en développement** : le simulateur (`PAIEMENT_PASSERELLE=simulee` dans `docker-compose.yml`). La clé fournie est une clé de production sur un compte où les paiements échouent : elle sert aux parcours d'échec, sur demande. Jamais le simulateur en production (refusé au démarrage).
- **2026-10-26 — Une vérification par l'API fait foi** : ni le navigateur ni le webhook ne paient une facture ; seul `GET /payments/{id}/verify/` à `SUCCESS` le fait, sous verrou, une seule fois.
- **2026-10-26 — Grand livre immuable** : en plus du modèle, un trigger PostgreSQL refuse toute modification ; seule la purge du jeu de démo peut supprimer (variable de session `soukdev.purge`).
- **2026-10-26 — Reversement repris sans double paiement** : bénéficiaire figé et chiffré sur le reversement, même `Idempotency-Key` à chaque essai ; un `409` de SasPay se réessaie et ne rend jamais le solde.
- **2026-10-26 — Confirmation du numéro par un bouton** : le lien de l'e-mail ouvre une page ; seul le clic confirme. Raison : les analyseurs de liens des messageries ouvrent les liens tout seuls.
- **2026-10-26 — Suspension = conteneurs arrêtés, volumes gardés**, page neutre sur le sous-domaine (rien sur la facture : le client d'une pharmacie n'a pas à savoir qu'elle n'a pas payé). Les copies déclarent `restart: unless-stopped` : une copie suspendue ne repart pas seule après un redémarrage de Docker.
- **2026-10-26 — E-mails de facturation en file** : une panne de Resend ne bloque ni une suspension ni un paiement. Aucun e-mail ne part vers les comptes de démo (adresses inexistantes, rebonds nuisibles au domaine d'envoi).
- **2026-10-26 — Liens absolus sur `APP_URL`** : simulateur, lien de paiement et confirmation visent l'API, même quand la requête passe par le proxy du front.
- **2026-10-26 — Le contrat vérifié par les tests** : chaque réponse JSON de `/api` est validée contre `openapi.yaml` (JSON Schema 2020-12). Il a trouvé 11 descriptions YAML cassées et 4 listes paginées hors format.
- **2026-10-26 — Miroir local des applis de démo** (développement seulement, `MOTEUR_MIROIRS`) : le moteur clone une appli cobaye écrite pour Souk.dev au lieu de `github.com/soukdemo/…`, ce qui permet de prouver « payer → en ligne → suspendu → réactivé » avec de vrais conteneurs.
- **2026-10-26 — Copie annoncée en ligne après Traefik** : 3 secondes d'attente après l'écriture de la route (Traefik regroupe les changements toutes les 2 s), sinon un clic rapide tombait sur un 404.
- **2026-10-26 — Logos des réseaux Mobile Money** (Logo.dev, 8 marques, une fois chacune) : seulement pour désigner le moyen de paiement choisi, hébergés dans `frontend/src/assets/reseaux/`.
- **2026-10-26 — Animations chargées à part** (`LazyMotion`) et coquille statique dans `index.html` : premier affichage plus rapide, mêmes animations.
- **2026-10-26 — Après la revue de la phase 2** :
  - le premier mois court depuis le paiement ; une réactivation après la date anniversaire ouvre un mois à partir du paiement (rien n'a été servi pendant la suspension) ;
  - statut d'abonnement « incident » (contrat) : copie payée qui ne démarre pas, plus de facture tant que l'équipe n'a pas réglé le problème ;
  - en production, reversements refusés tant que `REVERSEMENTS_REELS` n'est pas activé (jamais le simulateur) ;
  - `Idempotency-Key` obligatoire (428) sur le paiement, le reversement, l'avoir et le remboursement d'un paiement en double ; l'autorisation passe avant ce contrôle ;
  - routes d'administration : le contrôle d'admin passe avant la recherche de l'objet de l'URL (403, jamais un 404 révélateur) ;
  - une carte débitée dans une autre devise est créditée et vérifiée par l'équipe ; un montant inattendu en francs CFA n'est jamais appliqué automatiquement ;
  - frais retenus par SasPay malgré `ADD_ON` : charge de Souk.dev (compte `charge_frais_encaissement`), jamais sur la part du dev ;
  - alertes de l'équipe par e-mail (`AlerteAdmin`, `ADMIN_EMAIL`) pour tout cas d'argent qu'aucun automatisme ne doit trancher seul.

### Confiance (phase 3)

- **2026-10-26 — Sécurité avant publication dans des conteneurs jetables** : gitleaks (réseau coupé, rapport masqué) et Trivy (base des failles dans un volume) tournent sur le service `analyse`, jamais dans l'API. Un secret bloque la publication (422 avec le fichier, la ligne et la règle, jamais la valeur) ; une faille de dépendance donne le badge orange et le rapport ; l'auteur relance l'analyse (5 par heure). Le jeu de démo a un dépôt « fuite » dont la clé factice est écrite à l'exécution, pour que le dépôt de Souk.dev lui-même reste sans secret.
- **2026-10-26 — Qui note une appli** : un client dont au moins une facture de l'appli est payée (une commande jamais payée ne suffit pas), jamais son auteur. Un avis par client et par appli, modifiable ; la suppression et la modération viendront avec l'administration (phase 6).
- **2026-10-26 — Moyenne rangée sur l'appli** : nombre et somme des notes recalculés depuis la table des avis, sous le verrou de l'appli. Raison : la fiche, le Store et le tri lisent deux colonnes, sans jointure ni course entre deux avis simultanés.
- **2026-10-26 — Tri « Les mieux notées »** : moyenne bayésienne, comme si chaque appli avait déjà trois avis à 3/5. Raison : un seul 5/5 ne doit pas passer devant vingt avis à 4,7 ; une appli sans avis se range au milieu, devant une appli mal notée.
- **2026-10-26 — Six types de notification** : correctif proposé, correctif accepté, message de mission, factures et échéances (rappel, retard, paiement reçu), suspension (suspension, dernier avertissement, arrêt, copie qui ne démarre pas), reversement. Chacun se règle dans Souk.dev et par e-mail ; tout est reçu par défaut.
- **2026-10-26 — E-mail de suspension obligatoire** : il prévient avant la suppression des données d'un client ; l'écran des réglages le dit au lieu d'afficher un interrupteur grisé.
- **2026-10-26 — Un e-mail par fil de mission toutes les 30 minutes au plus** : chaque message arrive dans le centre, mais une conversation n'inonde pas la boîte.
- **2026-10-26 — Notifications en direct** : `notification.creee` sur `utilisateur.{id}`, même forme que l'API ; sans Reverb, la cloche relit l'API toutes les deux minutes. Une notification d'un autre compte répond 404.
- **2026-10-26 — Preuve des e-mails** : un e-mail par notification envoyé par Resend à son adresse de test officielle `delivered@resend.dev`, tous remis (statut lu dans l'API de Resend). Raison : aucune boîte de réception n'est configurée (`ADMIN_EMAIL` absent) et l'adresse personnelle de l'utilisateur ne sert pas sans son accord.
- **2026-10-26 — Après la revue de la phase 3** :
  - la recherche de secrets a sa file et son worker (`analyse-secrets`) : une vérification n'attend jamais derrière une analyse Trivy ; une demande abandonnée par la requête n'est pas analysée ; vérifier et publier : 20 par heure et par compte ;
  - le dépôt analysé ne choisit pas les règles de gitleaks : `.gitleaks.toml`, `.gitleaksignore` et les commentaires `gitleaks:allow` sont retirés de la copie analysée ;
  - Trivy en `--offline-scan` : il ne contacte pas les dépôts Maven ou npm déclarés par le dépôt (adresse interne, serveur lent) ;
  - état `echec` (contrat) : une analyse impossible se voit et se relance ; une analyse « en cours » depuis plus de 30 minutes se relance ; chaque semaine toutes les applis sont réanalysées, chaque nuit celles jamais analysées ; les restes d'analyses interrompues sont nettoyés chaque heure ;
  - un service indisponible (503) garde son message et son `Retry-After` ; la requête qui attend la recherche de secrets a le temps de le faire ;
  - les textes des membres restent du texte dans les e-mails (Markdown sécurisé : ni lien ni image injectés) ;
  - une nouvelle demande de mission prévient le dev (type « Missions ») ;
  - les réglages de notification s'enregistrent sous verrou, l'un après l'autre.

### Intelligence (phase 4)

- **2026-10-26 — Souk AI sur l'API officielle DeepSeek**, modèle `deepseek-flash` lu dans `GET /models` (DeepSeek-V4.1-Flash), derrière `FournisseurIa` ; `DEEPSEEK_MODELE` le change. Le fournisseur simulé sert aux tests, au travail hors ligne et au dépôt cloné sans clé ; il est refusé en production.
- **2026-10-26 — Consentement exigé par l'API**, pas seulement par l'écran : `consentement: true` obligatoire, date gardée avec la demande.
- **2026-10-26 — « 3 par jour » = 3 sur 24 heures glissantes**, vérifiées sous le verrou du compte. Raison : pas de remise à zéro à minuit UTC (1 h du matin au Bénin), et un 429 qui dit quand réessayer. Seules les pannes du fournisseur sont rendues, avec six tentatives au plus (revue de la phase 4).
- **2026-10-26 — Secrets masqués avant l'envoi** : une fiche de bug contient souvent un extrait de configuration ; les motifs de clés courants sont remplacés avant de partir chez DeepSeek.
- **2026-10-26 — Réponse rendue en Markdown simple, sans HTML** : titres, listes, gras, code ; un texte du modèle ne peut ni injecter de script ni de lien déguisé.
- **2026-10-26 — Après la revue de la phase 4** :
  - une analyse survit à sa discussion et compte toujours : supprimer puis recréer un bug ne rend pas d'analyse ; une réponse refusée ou coupée compte aussi (sinon une fiche qui pousse le modèle à l'échec offrirait des appels sans limite) ;
  - file et worker `ia` dédiés : une analyse ne retient jamais un paiement ; 6 000 jetons et 90 secondes au plus par appel, un seul nouvel essai ;
  - bornes tirées au hasard autour de la fiche, champs tronqués, masquage élargi (tableaux PHP, JSON, `.env`, adresses, en-têtes, Telegram, SendGrid, Slack, clés privées coupées) ;
  - une fiche modifiée périme ses analyses ; une relance qui échoue laisse la bonne analyse affichée ; une analyse « en cours » depuis plus de 10 minutes ne bloque plus rien ;
  - alerte de l'équipe une fois par heure au plus, y compris pour un modèle ou un paramètre refusés ;
  - événements Reverb allégés (texte abrégé, analyse sans texte) : Reverb refuse les messages de plus de 10 Ko ;
  - une limite propre à l'action (429) montre son propre message à l'écran.
