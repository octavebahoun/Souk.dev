# Notes de version — Souk.dev 1.0

Souk.dev devient un SaaS complet : un client inconnu trouve une appli, la paie par Mobile Money,
la voit tourner sur son sous-domaine, reçoit sa facture le mois suivant, et sa copie est
suspendue puis réactivée selon qu'il paie ; un dev publie, est aidé, aide, et reçoit son argent.
Branche `saas-complet`, huit phases ; le détail de chaque phase et de chaque vérification est
dans [PROGRESS.md](PROGRESS.md), les choix dans [DECISIONS.md](DECISIONS.md).

## Ce qui est livré

**Comptes et communauté**
- Connexion GitHub (devs) et lien magique par e-mail (clients), avec une page de confirmation
  avant d'ouvrir la session ; profil modifiable (pays, bio, compétences, spécialités,
  disponibilité) ; réputation (XP, niveaux, badges) et Souk Score ; tableau de bord du dev.
- L'Échange : canaux (création par les membres), étiquettes, bugs structurés, messages,
  correctifs (proposer, accepter, lancer la version corrigée), copie de test et démo dans un
  cadre isolé, Souk AI (DeepSeek, avec consentement, 3 analyses par jour), temps réel Reverb.
- Missions (pour une appli ou un dev, fil privé, note) ; événements (inscription, avis,
  modification, annulation avec e-mail aux inscrits).

**Store et moteur**
- Publication depuis un dépôt GitHub public : vérification de `soukdev.json` et du
  `docker-compose.yml` (liste blanche, durcissement, six services au plus), gitleaks avant
  publication et Trivy après (badge de sécurité), mémoire mesurée et formule recommandée.
- Recherche et filtres, fiche, vues, avis et notes des clients.
- Copies : clonage du commit vérifié, `.env` par copie, conteneurs durcis (capacités retirées,
  plafonds de mémoire, processeur, processus et journaux), un sous-domaine par copie derrière
  Traefik, sonde du service web, arrêt, nettoyage, mises à jour avec sauvegarde et retour
  arrière, backend intégré (PostgreSQL, PostgREST, connexion des utilisateurs par SMS ou
  e-mail), sauvegardes quotidiennes et export, plusieurs serveurs.

**Argent (SasPay)**
- Paiement Mobile Money ou carte ; la copie ne démarre qu'au `SUCCESS` vérifié par l'API de
  SasPay ; webhook signé (HMAC, 300 s, idempotent) qui ne fait que déclencher cette
  vérification ; rapprochement toutes les 15 minutes et chaque jour.
- Facture du mois suivant 3 jours avant l'échéance, avec rappel et lien de paiement signé ;
  suspension à 7 jours, réactivation au paiement, arrêt définitif à 30 jours.
- Grand livre inaltérable ; reversements Mobile Money aux devs, mensuels ou sur demande, sur un
  numéro confirmé par e-mail ; avoirs après remboursement ; vue financière de l'équipe.

**Exploitation et confiance**
- Production : `docker-compose.prod.yml` (HTTPS par Traefik, files et planificateur séparés,
  volumes persistants), copies sur un domaine à part, en-têtes de sécurité (CSP par empreinte,
  HSTS, refus des cadres), proxy de confiance.
- Modération : signalements, suppression de contenus, suspension de comptes et de copies,
  canaux ; politique d'abus automatique (un conteneur saturé une heure, hameçonnage signalé
  par deux membres distincts).
- Sauvegarde et restauration de la plateforme, journaux JSON, alertes e-mail à l'équipe,
  nettoyage des images ; pages légales (brouillons à faire valider par un juriste).
- Interface refaite avec un système de design (clair et sombre, mobile à bureau), visée WCAG 2.2 AA.

## Ce qui est vérifié, et comment

Chaque phase a suivi la même boucle (section 10 du brief) ; les résultats datés sont dans
[PROGRESS.md](PROGRESS.md), « Vérifications ». Au 3 novembre :

| Contrôle | Résultat |
|---|---|
| Style et types : Pint, ESLint, `tsc` | 0 erreur |
| Tests de l'API (PHPUnit), dont le contrat face à `openapi.yaml` et l'anti-IDOR | 658 tests |
| Tests du front (Vitest) | 50 tests |
| Parcours de bout en bout (Playwright) contre la plateforme Docker, copies vraiment construites : connexion, discussion, correctif, copie de test, publication, paiement, déploiement, Mes copies, mises à jour, backend intégré, plusieurs serveurs, mission, événement, reversement, modération | 30 parcours sur 30 |
| Accessibilité : audit axe (WCAG 2.2 A et AA) sur chaque écran des parcours | aucune violation sérieuse ou critique ; audit automatique, pas d'audit manuel avec lecteur d'écran |
| Captures 3 tailles × 2 thèmes, regardées et critiquées | 198 captures (33 pages) |
| Lighthouse (build de production), bureau | 98 à 100 en performance, 100 en accessibilité, 96 à 100 en bonnes pratiques sur l'accueil, le Store, une fiche, la commande et Mes copies |
| Dépendances : `composer audit`, `npm audit` | aucune faille en production ; 15 moyennes dans les outils de développement |
| Licences (CI) | aucune GPL, AGPL ni LGPL |
| Sécurité | audit par un relecteur indépendant (9 constats) et revue de chaque phase, constats corrigés et testés ; CSP vérifiée sur toutes les pages |
| Production | essai local complet le 1er novembre (HTTPS, sauvegarde puis restauration réussie) |

Paiements testés avec le simulateur SasPay (aucun argent réel) ; les reversements passent
toujours par l'adaptateur simulé.

## Limites connues

- **Performance sur mobile** : Lighthouse en mobile simulé (Moto G, 4G lente) donne 72 à 86 sur
  l'accueil, le Store et une fiche selon la charge de la machine (bureau : 98 à 100). Le
  premier affichage utile attend le JavaScript de l'application ; passer 90 demande un
  pré-rendu des pages publiques.
- **Isolation des copies** : conteneurs durcis, mais root dans le conteneur et noyau partagé
  avec la plateforme ; `userns-remap` ou gVisor, puis des machines dédiées aux copies, avant
  d'accueillir des auteurs inconnus en nombre (`docs/exploitation.md`). Pas de quota disque
  par copie.
- **Paiement réel** : la page de paiement SasPay affiche le nom marchand « Gentube », celui du
  compte fourni (voulu). Les reversements réels restent coupés tant que
  `REVERSEMENTS_REELS` n'est pas activé ; les parcours se testent avec le simulateur.
- **Webhooks réels en développement** : il faut un tunnel public (non mis en place).
- **SMS** : aucun fournisseur de SMS n'est branché (pas de clé) ; la connexion des
  utilisateurs finaux par SMS passe par l'adaptateur simulé, l'e-mail fonctionne.
- **Certificats** : un certificat par copie (défi HTTP-01) ; au-delà de 50 nouvelles copies par
  semaine, passer au certificat joker (défi DNS-01).
- **Second VPS** : le routage multi-VPS est conçu (`docs/multi-vps.md`), pas déployé.
- **Accessibilité** : audits axe automatiques seulement ; un passage avec un lecteur d'écran
  reste à faire.
- **E-mails dans les parcours de bout en bout** : le lien magique complet et la confirmation
  du numéro de reversement sont couverts par les tests de l'API, pas par Playwright (pas de
  boîte de réception de test en développement).
- **Pages légales** : brouillons, à faire valider par un juriste (APDP Bénin).
