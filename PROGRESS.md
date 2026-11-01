# PROGRESS — Souk.dev, SaaS complet

Journal de l'agent, tenu à jour en continu (voir `BRIEF.md`, section 1).
À relire avec `BRIEF.md` et `DECISIONS.md` au début de chaque session.

## Où on en est

- **Branche** : `saas-complet`, partie de `main` (la PR #28 `fin-mvp` y est fusionnée).
- **Phase en cours** : 6 — Exploitation, revue indépendante traitée (8 constats corrigés, 2 en dette Docker, comme la phase 5).
- **Tâche suivante** : au retour de Docker, exécuter `moderation.spec.ts`, les captures `/admin/moderation` et `/legal/*`, le test de restauration et le démarrage prod, puis clore la phase 6 (et, toujours en attente, la vérification Docker de la phase 5).

## Étape 0 (25 octobre)

- Docker répond (29.1.3, 4 cœurs, 7,7 Go).
- Clés présentes dans `backend/.env` : SasPay (`sk_live_`), webhook SasPay, DeepSeek, Resend, GitHub.
- Tunnel public refusé par le mode auto de Claude Code : question posée à l'utilisateur
  (lancer `cloudflared tunnel --url http://localhost:8000` lui-même). En attendant, le retour
  de paiement et le rapprochement toutes les 15 minutes vérifient les paiements sans webhook.
- SMS : question posée ; sans réponse, adaptateur simulé.

## Plan

### Phase 1 — Socle
- [x] Dettes : git en `LC_ALL=C` (`RepositoryReaderTest`), suite PHP dans le conteneur (GD) — reste : couleurs en dur → tokens (avec la refonte)
- [x] Laravel Boost installé (demandé par `backend/CLAUDE.md`)
- [x] Faille : liens symboliques des dépôts clonés neutralisés
- [x] Image PHP de la plateforme (`backend/Dockerfile`) et `docker-compose.yml` : API, worker, moteur, Reverb, planificateur, PostgreSQL, Traefik, front
- [x] Docker réservé au service moteur (arrêt asynchrone, nettoyage et mesure sur la file `moteur`)
- [x] Migrations vérifiées sur PostgreSQL (montée et descente), contrôles de santé par service
- [x] Policies pour chaque ressource (fin des `abort_unless` dispersés)
- [x] Tests anti-IDOR : déploiement, mission, messages et note de mission, canaux privés
- [x] Temps réel des missions (canal privé `mission.{id}`)
- [x] Réputation, stats et Souk Score branchés (ils valaient zéro), recherche triée par Souk Score
- [x] Système de design : `docs/design.md`, tokens clair et sombre, Outfit, bibliothèque `frontend/src/ui`, coquille
- [x] Jeu de démo (`DemoSeeder`) et connexion de démo pour Playwright ; Playwright, captures et audit axe en place
- [x] Revue indépendante (sous-agent neuf) : 10 constats, tous corrigés (voir ci-dessous)
- [x] Sortie : `docker compose up` à froid démarre tout, suite PHP verte dans le conteneur, aucun test sauté

**Revue indépendante de la phase 1** (constats corrigés) :
1. Critique : un `docker-compose.yml` lisait les secrets de la plateforme par interpolation → environnement minimal pour git et docker.
2. Élevé : le conteneur `front` (npm, root) voyait DB_PASSWORD et joignait la base → volume public et réseau séparés.
3. Moyen : un arrêt pouvait rester bloqué si Reverb tombait → job d'abord, diffusion non bloquante, reprise par le nettoyage.
4. Moyen : XP sans validation d'un tiers (auto-acceptation, événements passés) → exclus.
5. à 8. Front : thème sombre des anciens écrans, sélecteur de thème dans un menu, contrastes des états, rendu des refus d'autorisation.
9. et 10. Tri initial du Souk Score, Traefik sur la boucle locale, interrupteur de 44 px, compte admin de démo réservé au poste de dev.

### Phase 2 — Paiement (SasPay)
- [x] Adaptateurs `PasserellePaiement` (SasPay, simulée) et reversements (simulés)
- [x] Simulateur SasPay : page de paiement locale, statuts `PENDING`/`SUCCESS`/`FAILED`, webhooks signés
- [x] Factures à date anniversaire, rappel J-3, grâce 7 jours, suspension, réactivation, arrêt définitif à 30 jours
- [x] Webhook signé et idempotent, revérification par l'API, rapprochement (15 min et quotidien)
- [x] Grand livre en partie double, en entiers FCFA, sans modification (écritures inverses, trigger PostgreSQL)
- [x] Reversements : solde, coordonnées Mobile Money confirmées, mensuel ou à la demande (≥ 5 000 F)
- [x] Avoirs saisis par l'admin
- [x] Écrans : commande et paiement, retour, « Mes paiements » et reçus, « Mes revenus », vue financière admin, « Mes copies »
- [x] Sortie : un client paie et sa copie démarre ; un impayé est suspendu puis réactivé ; un dev est reversé (simulé) ; un échec réel de SasPay est géré de bout en bout
- [x] Revue indépendante de la phase 2 et corrections (25 constats, tous traités)

**Revue indépendante de la phase 2** (sous-agent neuf, 8 constats prouvés par des tests jetables, repris en tests de non-régression) :
1. Copie réactivée après sa date anniversaire resuspendue quelques jours plus tard → le paiement ouvre un mois à partir d'aujourd'hui.
2. Succès tardif sur une tentative « annulée » jamais crédité → la transaction décide, revérifiée pendant 7 jours.
3. Course entre le passage quotidien et un paiement (suspension ou arrêt d'une copie payée) → verrou et revérification à chaque étape, ordre des verrous commun.
4. Copie payée qui ne démarre pas, toujours facturée → statut « incident », plus de facture, équipe et client prévenus.
5. En production, reversements par le simulateur (« versé » sans argent) → refusés tant que les payouts réels ne sont pas activés.
6. Carte débitée en dollars rejetée comme « montant incohérent » → créditée, l'équipe vérifie ; plus de « rien débité » faux.
7. Double paiement possible pendant une validation USSD → 409 et « Vérifier le paiement ».
8. à 25. Idempotency-Key obligatoire, diffusion en file, frais retenus passés en charge, remboursement des paiements en double, reprises et blocages des reversements, suspension dans le bon ordre, premier mois depuis le paiement, rapprochement robuste, annonces aux lecteurs d'écran, données partielles, prix de l'auteur, clés renouvelées, messages, captures complètes, garde du jeu de démo, jobs uniques, masquage des numéros.

### Phase 3 — Confiance
- [x] Sécurité avant publication : gitleaks et Trivy en conteneurs jetables, statuts, badge, relance par l'auteur, rapport sur la fiche
- [x] Avis et notes des applis : clients ayant payé une copie, 1 à 5, un par client et par appli (modifiable), moyenne sur la fiche et les cartes, tri « Les mieux notées »
- [x] Notifications : centre dans l'appli (cloche en direct, page, réglages), e-mails, préférences par type (e-mail de suspension obligatoire)
- [x] Sortie : un dépôt avec un secret est refusé ; badge vert sur un dépôt sain ; un e-mail remis pour chaque notification (12 sur 12, adresse de test de Resend)
- [x] Revue indépendante de la phase 3 (sous-agent neuf) : 15 constats, tous traités

**Revue indépendante de la phase 3** :
1. Recherche de secrets coincée derrière les analyses Trivy (un seul worker) → file et worker dédiés, demande abandonnée non analysée, 20 vérifications par heure.
2. Un dépôt pouvait couper gitleaks avec sa propre configuration → `.gitleaks.toml`, `.gitleaksignore` et `gitleaks:allow` retirés avant l'analyse (test).
3. Liens et images injectables dans les nouveaux e-mails → Markdown sécurisé (test sur le HTML rendu).
4. Statut « en cours » éternel, bouton de relance grisé → état `echec`, relance après 30 minutes, réanalyse hebdomadaire et des oubliées, fiche relue pendant l'analyse.
5. Trivy suivait les dépôts déclarés par le pom.xml (requêtes vers une adresse choisie par le dépôt) → `--offline-scan`.
6. Une nouvelle demande de mission ne prévenait personne → notification et e-mail au dev.
7. et 8. Panneau de la cloche resté ouvert hors du focus ; erreurs du formulaire d'avis muettes et focus perdu → corrigés (WCAG 2.4.11, 3.3.1, 2.4.3).
9. à 15. 503 sans son message ni Retry-After, mission d'une notification ignorée sur la page Missions, rappels d'abonnement orphelins, réglages concurrents, conteneurs et clones laissés par un travail tué, pluriels, tests manquants (503 de bout en bout, configuration hostile, mission notifiée) et parcours E2E rendus rejouables.

### Phase 4 — Intelligence
- [x] Souk AI : API DeepSeek (`deepseek-flash`), consentement exigé par l'API, 3 sur 24 heures (échecs exclus), une à la fois, résultat par Reverb (`analyse_ia.terminee`), secrets masqués avant l'envoi, réponse en Markdown sans HTML
- [x] Sortie : une analyse réelle s'affiche à côté des correctifs (E2E, DeepSeek, environ 8 s) ; la 4ᵉ demande en 24 heures reçoit 429
- [x] Revue indépendante de la phase 4 (sous-agent neuf) : 15 constats, tous traités

**Revue indépendante de la phase 4** :
1. Supprimer la discussion rendait l'analyse (environ 240 par jour au lieu de 3) → les analyses survivent à leur discussion et comptent.
2. Échecs provoqués gratuits, dépense sans plafond → refus comptés, pannes rendues dans la limite de six tentatives, 6 000 jetons et fiche tronquée.
3. Analyses devant les paiements sur le même worker → file et worker `ia`.
4. Masquage incomplet (PHP, JSON, `.env`, adresses, Bearer, MTN MoMo…) → motifs élargis, un test par format.
5. Bloc de la fiche refermable, analyse gardée après modification → bornes aléatoires, analyses périmées à chaque modification.
6. à 15. Analyse « en cours » éternelle, message du quota masqué à l'écran, alertes en rafale, annonces et focus, bonne analyse cachée par une relance ratée, « 1 h 60 », messages Reverb trop gros, 401 au contrat, rendu Markdown (retraits, italique, numérotation, titres), tests manquants.

### Phase 5 — Plateforme
- [x] Mises à jour poussées : versions publiées par l'auteur (vérifiées comme une publication, commit épinglé), clients prévenus, mise à jour choisie par le client avec sauvegarde des volumes et retour arrière automatique ; vérifié en réel (version 2 appliquée, version cassée revenue d'elle-même)
- [x] Backend intégré complet : connexion SMS et e-mail des utilisateurs finaux, sauvegarde quotidienne, export
- [x] Revue indépendante du backend intégré (sous-agent neuf) : 8 constats, 7 corrigés, 1 en attente de Docker (voir ci-dessous)
- [x] Plusieurs serveurs : liste des machines, placement sur la plus libre, maintenance (drain), état et santé sondés, page admin ; test local avec deux machines écrit (`machines.spec.ts`), à exécuter au retour de Docker (voir ci-dessous)
- [x] Revue indépendante des serveurs (sous-agent neuf) : 6 constats, 5 corrigés, 1 en dette Docker (voir ci-dessous)

**Revue indépendante du backend intégré** (8 constats, tous fondés ; aucun réfuté) :
1. Élevé : Playwright (dont `backend-integre.spec.ts`), captures /copies, axe et Lighthouse jamais exécutés (Docker arrêté) → **non corrigé ici** : Docker toujours arrêté, à refaire dès son retour avant de clore la phase 5.
2. Moyen : `/session` sans limite propre (5 faux essais bloquaient le code d'autrui) → 20 essais par heure et par adresse IP.
3. Moyen : verdicts distincts faux/expiré (oracle d'activité sur un numéro) → verdict unique « invalide ou expiré ».
4. Moyen : panne SMS inattendue en 500, contre 503 au contrat → toute panne SMS rend 503 + `Retry-After`, comme l'e-mail.
5. Moyen : nom d'appli avec retour à la ligne → 503 pour tous les utilisateurs de la copie (CVE-2026-45070) → nom sans CR/LF à la saisie, nettoyé à l'envoi.
6. Moyen : codes expirés jamais purgés → purge quotidienne planifiée.
7. Moyen : « Mes copies » ne montrait que la dernière sauvegarde → liste de chaque sauvegarde gardée, chacune exportable.
8. Moyen : demande de sauvegarde sans fin en cas d'échec du travail → erreur et bouton de retour après 15 minutes sans nouvelle sauvegarde.
Contrat inchangé : les codes et routes utilisés (422, 429, 503, `GET /deployments/{id}/sauvegardes`) y figuraient déjà.

**Revue indépendante des serveurs** (6 constats, tous fondés ; aucun réfuté) :
1. Moyen : démon déjà enregistré → 500 de base au lieu du 422 du contrat → `unique` sur `hote_docker` (ignoré en modification) avec message français, test.
2. Élevé : `tcp://` distant sans TLS accepté partout, aucun champ TLS → refusé en 422 en production (seuls le socket local, `localhost`, `127.0.0.0/8`, `host.docker.internal`), aide dans le formulaire, TLS par machine prévu avec le bord par machine (phase 6).
3. Moyen : le verrou de placement ne couvrait pas l'écriture de `machine_id` (course possible à plusieurs workers) → élection et réservation dans la même transaction, copies occupantes verrouillées ; test de deux placements successifs.
4. Moyen : l'UI disait « Sans limite déclarée » alors que le placement limitait par la mémoire sondée → contrat corrigé, « environ X libres (mémoire sondée) » et jauge sur la sondée, tests.
5. Moyen : captures `/admin/serveurs` jamais faites → **non corrigé ici** : Docker toujours arrêté, à refaire dès son retour avant de clore la phase 5 (avec les nouveaux états : vide, libre sondé).
6. Moyen : état vide en simple paragraphe, contre `EtatVide` illustré exigé → `EtatVide` avec illustration `vide-serveurs` (clair et sombre, 0,27 $) et action vers le formulaire.
Contrat : seule la description de `memoire_libre_mo` change (capacité utile, sinon sondée) ; types régénérés.

### Phase 6 — Exploitation
- [x] Administration et modération : signalements (motifs graves en e-mail aussitôt), suspensions de copies pour abus (moteur, volumes gardés) et de comptes (plus de connexion ni d'API), gestion des canaux, finances et serveurs déjà en place, politique d'abus (`docs/moderation.md`, `/legal/abus`)
- [x] `docker-compose.prod.yml` (HTTPS Traefik + Let's Encrypt, worker et planificateur séparés, volumes persistants, front nginx statique), sauvegardes avec test de restauration (scripts écrits, exécution en dette Docker), journaux JSON (canal `json`, Traefik JSON), alertes e-mail (file, disque, machines), nettoyage hebdomadaire des images
- [x] Pages légales : CGU, CGV, confidentialité (APDP Bénin), politique d'abus — brouillons marqués « à faire valider par un juriste »
- [x] Routage multi-VPS conçu dans `docs/multi-vps.md` (TLS par machine, proxy en bordure par machine, VPN WireGuard, push SSH des routes), sans second VPS réel ; `docs/machines.md` mis à jour
- [x] Revue indépendante de la phase 6 (sous-agent neuf) : 10 constats, 8 corrigés, 2 en dette Docker (voir ci-dessous)
- [ ] Vérification Docker en dette (démon arrêté, voir Problèmes ouverts)

**Revue indépendante de la phase 6** (10 constats, tous fondés ; aucun réfuté) :
1. Élevé : `pg_dump | gzip` en `sh` (code de gzip seul) déclarait réussie une sauvegarde vide → dump dans un temporaire, refusé s'il est vide, puis compression (prouvé : faux `docker` en échec → code 1, rien d'écrit).
2. Moyen : un compte suspendu gardait ses canaux privés Reverb (`/broadcasting/auth` hors middleware `api/`) → chaque canal refuse un suspendu, test (200 avant, 403 après).
3. Moyen : `lockForUpdate()` hors transaction (décisions de signalement, suspensions de copies) → lecture + contrôle + écriture dans `DB::transaction`, travail et e-mail après validation.
4. Moyen : « un ouvert par cible » applicatif seul (double-clic = doublon) → index unique partiel `(user_id, cible, cible_id) WHERE statut='ouvert'` (PostgreSQL et SQLite), violation mappée en 409, test (refus du 2ᵉ, de nouveau signalable après décision).
5. Moyen : l'UI interdisait de suspendre tout admin, contre l'API (seul son propre compte) → bouton désactivé pour son seul compte, texte aligné, test composant.
6. Moyen : « Signaler » absent des messages, copies, comptes et événements → boutons sur chaque message et chaque événement (hors auteur), signalement d'une copie ou d'un compte par l'API documenté dans `docs/moderation.md`.
7. Moyen : aucun lien « Voir » vers un message, une copie ou un compte signalé → le signalement expose `cible_chemin` (discussion parente, adresse de la copie, profil `/devs`), contrat et types à jour, test.
8. Moyen : le nettoyage des images ignorait les démons distants → purge du local puis de chaque distant joignable (les injoignables laissés à la sonde), test.
9. Moyen : captures `/admin/moderation` et `/legal/*` jamais faites → **non corrigé ici** : Docker toujours arrêté, à refaire dès son retour avant de clore la phase 6 (voir Problèmes ouverts).
10. Moyen : restauration et `moderation.spec.ts` jamais exécutés (critères de sortie de la phase) → **non corrigé ici** : Docker toujours arrêté, à refaire dès son retour avant de clore la phase 6 (voir Problèmes ouverts).
Contrat : seul le schéma `Signalement` gagne `cible_chemin` ; types régénérés.

### Phase 7 — Refonte du design
- [ ] Chaque page redessinée, captures critiquées (3 tailles, 2 thèmes), zéro violation axe sérieuse

### Phase 8 — Release
- [ ] Audit sécurité, performance, accessibilité ; documentation ; `RELEASE_NOTES.md`

## Pages (but, action principale, ce qui doit sauter aux yeux)

| Page | But | Action principale | Ce qui doit sauter aux yeux |
|---|---|---|---|
| Commande (`/store/{id}/deployer`) | Commander une copie en confiance | Payer | Le total d'aujourd'hui, puis ce qui revient chaque mois ; « 0 F de commission » |
| Retour de paiement | Savoir si l'argent est passé et ce qui suit | Ouvrir ma copie (ou réessayer) | Le résultat (confirmé, refusé, en attente), puis les étapes de la copie |
| Mes paiements | Payer ce qui est dû, retrouver ses reçus | Payer la facture due | Ce qui est à payer ; sinon « vous êtes à jour » et la prochaine facture |
| Facture / reçu | Garder une preuve de paiement | Imprimer le reçu (ou payer) | Le total, payé quand et comment |
| Mes revenus | Être payé | Recevoir son solde | Le solde, le numéro où l'argent arrive, sa confirmation |
| Finances (admin) | Suivre l'argent de Souk.dev | Saisir un avoir après un remboursement | La marge, ce qui est dû aux devs, les impayés et les écarts |
| Mes copies | Suivre ses copies | Payer, ouvrir ou relancer selon l'état | L'état de chaque copie, son adresse, ce qu'elle coûte
| Serveurs (`/admin/serveurs`) | Voir la flotte et sa santé, l'entretenir | Ajouter un serveur (ou sonder, basculer, retirer) | Les pastilles d'état (en service, en maintenance, injoignable) et les jauges de mémoire libre |
| Modération (`/admin/moderation`) | Examiner et décider vite | Traiter un signalement (ou suspendre) | Les motifs graves en rouge, puis les copies et comptes suspendus |
| Pages légales (`/legal/*`) | Lire les règles et ses droits | Comprendre (aucune action) | Le bandeau « brouillon à faire valider par un juriste », puis le plan en 4-5 articles |

## Vérifications

| Date | Phase | Contrôle | Résultat |
|---|---|---|---|
| 2026-10-25 | 0 | `php artisan test` (état de départ) | 356/361 : 1 échec `RepositoryReaderTest` (locale), 4 erreurs GD (`PublicationAppliTest`) |
| 2026-10-25 | 1 | `docker compose up` à froid (volumes vides) | 8 services sains, migrations PostgreSQL passées |
| 2026-10-25 | 1 | Copie locale déployée par le moteur conteneurisé | sonde OK, `http://essai-locale.localhost` → 200 via Traefik |
| 2026-10-25 | 1 | `php artisan test` dans le conteneur PHP | 380/380, aucun test sauté |
| 2026-10-25 | 1 | Playwright (parcours socle, axe sur la page introuvable en clair et en sombre) | 3/3, zéro violation sérieuse |
| 2026-10-25 | 1 | `composer audit`, `npm audit` | aucune faille (Prism retiré : 8 failles npm de développement) |
| 2026-10-25 | 1 | Revue indépendante | 10 constats, tous corrigés ; fuite des secrets vérifiée avant et après dans le conteneur moteur |
| 2026-10-26 | 2 | `php artisan test` dans le conteneur, chaque réponse JSON validée contre `openapi.yaml` | 464/464, aucun test sauté |
| 2026-10-26 | 2 | Pint, ESLint, `tsc`, Vitest | zéro erreur, 25/25 |
| 2026-10-26 | 2 | Démarrage à froid (projet Docker séparé, volumes vierges) | 8 services sains en 66 s, 26 migrations |
| 2026-10-26 | 2 | Playwright contre la plateforme démarrée à froid (moteur réel, paiement simulé) | 8/8 : payer → copie en ligne → impayé suspendu (page neutre via Traefik) → réactivé ; refus expliqué et retenté ; reversement ; vue admin ; audit axe sans violation sérieuse |
| 2026-10-26 | 2 | Échec réel SasPay (passerelle `saspay`, numéro togolais impossible) | « Paiement échoué » chez SasPay ; vérifié par l'API : paiement `echoue`, facture toujours à payer, page « Paiement refusé » |
| 2026-10-26 | 2 | Trigger du grand livre sur PostgreSQL ; migrations annulées puis rejouées | modification et suppression refusées ; aller-retour sans erreur |
| 2026-10-26 | 2 | Captures (3 tailles × 2 thèmes) des écrans de paiement, critiquées | 6 défauts corrigés (bouton coupé, visuel de l'appli, état « à jour », compteurs à zéro, montants et ponctuation coupés en fin de ligne) |
| 2026-10-26 | 2 | `composer audit`, `npm audit`, licences | aucune faille, aucune licence interdite |
| 2026-10-26 | 2 | Après corrections de la revue : suite PHP (réponses validées contre le contrat), Playwright 8/8, captures 42/42 | 494/494 ; 8/8 ; relues |
| 2026-10-26 | 2 | Lighthouse (build de production) sur la commande et « Mes copies » | bureau : 98 / 100 / 100 ; mobile simulé : accessibilité et bonnes pratiques 100, performance 72 à 79 (premier affichage 2,3 s, LCP 3,3 s) : sous l'objectif de 90, voir Problèmes ouverts |
| 2026-10-26 | 3 | Suite PHP dans le conteneur (réponses validées contre le contrat) ; Pint, ESLint, `tsc`, Vitest | 517/517 ; zéro erreur, 28/28 |
| 2026-10-26 | 3 | Démarrage à froid d'une copie propre du dépôt (worktree, projet Docker séparé, volumes vierges) | 10 services sains en 61 s, 34 migrations ; a révélé les webhooks simulés refusés sans secret (corrigé : secret de développement tiré de `APP_KEY`) |
| 2026-10-26 | 3 | Playwright contre la plateforme démarrée à froid | 11/11 au troisième passage (deux échecs isolés non reproduits : script axe non chargé depuis le CDN, un parcours de paiement refusé) ; secret refusé, badge vert, avis, cloche en direct, réglages, paiement, suspension, reversement, admin |
| 2026-10-26 | 3 | Un e-mail par notification (12 sortes) envoyé par Resend à `delivered@resend.dev` | 12/12 remis (statut lu dans l'API de Resend) |
| 2026-10-26 | 3 | Captures (3 tailles × 2 thèmes) : Store trié par note, fiche avec avis, notifications, réglages, cloche | 30 captures relues ; corrigés : interrupteur grisé illisible, bouton mal placé sur mobile, erreur de note restée affichée |
| 2026-10-26 | 3 | Lighthouse (build de production), bureau | accueil 99 / 100 / 100, Store 95 / 100 / 96, fiche 99 / 100 / 96 (décalage de mise en page corrigé : 0,67 → 0,03), « Mes copies » et notifications 100 / 100 / 100 ; mobile de la fiche 66 (voir Problèmes ouverts) |
| 2026-10-26 | 3 | `composer audit`, `npm audit`, licences | aucune faille, aucune licence interdite (gitleaks MIT et Trivy Apache 2.0 inscrits) |
| 2026-10-26 | 4 | Analyse réelle par DeepSeek (`deepseek-flash`) sur le bug de démo | prête en 7,7 s, 387 jetons lus, 994 écrits, réponse en français en trois parties |
| 2026-10-26 | 4 | Suite PHP dans le conteneur (contrat vérifié) ; Pint, ESLint, `tsc`, Vitest | 547/547 ; zéro erreur, 31/31 |
| 2026-10-26 | 4 | Démarrage à froid d'une copie propre (worktree, projet séparé, `.env` réel) puis Playwright | 11 services sains en 51 s, 36 migrations ; 13/13 du premier coup (dont Souk AI réel : analyse affichée en direct, focus et annonce, 429 à la 4ᵉ) |
| 2026-10-26 | 4 | Captures de la discussion avec l'analyse (3 tailles × 2 thèmes, auteur et visiteur) | 12 relues ; corrigés : italique non rendu, messages en Markdown brut alors que « Markdown accepté » |
| 2026-10-28 | 5 | Suite PHP sur l'hôte (réponses validées contre le contrat), dont `BackendIntegreCompletTest` (9 tests) et `ContratRoutesTest` | 563 passés, 4 échecs connus : `PublicationAppliTest` demande GD, absent de l'hôte (vert dans le conteneur, voir phase 1) ; Docker arrêté, suite en conteneur non relancée |
| 2026-10-28 | 5 | Pint, ESLint (tout le front), `tsc`, Vitest, types régénérés depuis `openapi.yaml` | zéro erreur, 31/31 ; `api.ts` régénéré (routes d'authentification des copies, sauvegardes, `derniere_sauvegarde`, 503) |
| 2026-10-28 | 5 | `composer audit`, `npm audit`, licences | 1 faille moyenne trouvée (symfony/mime CVE-2026-45070, injection d'en-têtes e-mail, publiée le jour même) : corrigée (8.1.7 → 8.1.10, `composer.lock` seul modifié) ; après : aucune faille, aucune licence interdite |
| 2026-10-28 | 5 | Démarrage à froid, Playwright (dont `backend-integre.spec.ts`), captures, axe, Lighthouse | **non faits** : Docker arrêté sur le poste (`/var/run/docker.sock` absent, service inactif, `sudo` interdit) ; à refaire dès son retour, avant la revue de phase |
| 2026-10-28 | 5 | Après corrections de la revue : suite PHP sur l'hôte (dont 5 tests de non-régression du backend intégré) | 568 passés, 4 échecs connus : `PublicationAppliTest` demande GD, absent de l'hôte (vert dans le conteneur) |
| 2026-10-28 | 5 | Après corrections de la revue : Pint (431 fichiers), ESLint, `tsc`, Vitest (dont `MesCopies.test.tsx`) | zéro erreur, 33/33 |
| 2026-10-28 | 5 | Après corrections de la revue : `composer audit`, `npm audit`, licences | aucune faille, aucune licence interdite |
| 2026-10-28 | 5 | Suite PHP sur l'hôte, dont `MachinesTest` (21 tests : contrat, placement, maintenance, anti-IDOR admin, sonde, démons) et `ContratRoutesTest` | 589 passés, 4 échecs connus : `PublicationAppliTest` demande GD, absent de l'hôte (vert dans le conteneur) ; Docker arrêté, suite en conteneur non relancée |
| 2026-10-28 | 5 | Pint (442 fichiers), ESLint (tout le front), `tsc`, Vitest (dont `etatServeurs.test.ts`), types régénérés depuis `openapi.yaml` | zéro erreur, 41/41 ; `api.ts` régénéré (schémas `Machine`, `machine_nom` sur `Deploiement`) |
| 2026-10-28 | 5 | `composer audit`, `npm audit`, `composer check-licenses`, `npm run check-licenses` | aucune faille, aucune licence interdite (docker:dind Apache 2.0 inscrit) |
| 2026-10-28 | 5 | `docker compose config` (dont profil `machines`) et chargement du parcours `machines.spec.ts` | compose valide, 3 tests listés ; exécution impossible : Docker arrêté |
| 2026-10-28 | 5 | Playwright `machines.spec.ts`, captures `/admin/serveurs` (3 tailles × 2 thèmes), axe, Lighthouse | **non faits** : Docker arrêté sur le poste (`/var/run/docker.sock` absent, `sudo` interdit) ; parcours écrit et relu, à exécuter à son retour (voir Problèmes ouverts) |
| 2026-10-29 | 5 | Après corrections de la revue des serveurs : suite PHP sur l'hôte (dont 5 tests de non-régression serveurs) | 594 passés, 4 échecs connus : `PublicationAppliTest` demande GD, absent de l'hôte (vert dans le conteneur) |
| 2026-10-29 | 5 | Après corrections de la revue des serveurs : Pint (442 fichiers), ESLint, `tsc`, Vitest, types régénérés depuis `openapi.yaml` | zéro erreur, 43/43 ; `api.ts` régénéré (description `memoire_libre_mo`) |
| 2026-10-29 | 5 | Après corrections de la revue des serveurs : `composer audit`, `npm audit`, licences | aucune faille, aucune licence interdite |
| 2026-10-29 | 5 | Playwright `machines.spec.ts`, captures `/admin/serveurs` (3 tailles × 2 thèmes, nouveaux états), axe, Lighthouse | **toujours non faits** : Docker encore arrêté (`/var/run/docker.sock` absent) ; dette maintenue (voir Problèmes ouverts) |
| 2026-10-29 | 6 | Suite PHP sur l'hôte, dont `ModerationTest` (18 tests : signalements, suspensions copies/comptes, canaux, anti-IDOR admin) et `ExploitationTest` (7 tests : alertes, nettoyage, journaux JSON), `ContratRoutesTest` | 620 passés, 4 échecs connus : `PublicationAppliTest` demande GD, absent de l'hôte (vert dans le conteneur) |
| 2026-10-29 | 6 | Pint (460 fichiers), ESLint (tout le front), `tsc` (appli + E2E), Vitest (dont `presentationModeration.test.ts`), types régénérés identiques | zéro erreur, 46/46 ; `api.ts` régénéré (schémas `Signalement`, `CompteAdmin`, suspensions, `CanalModification`) |
| 2026-10-29 | 6 | `composer audit`, `npm audit`, `composer check-licenses`, `npm run check-licenses` | aucune faille, aucune licence interdite (nginx BSD-2-Clause, alpine MIT inscrits) |
| 2026-10-29 | 6 | `docker compose -f docker-compose.prod.yml config` (avec `DOMAINE`, `ACME_EMAIL`, `VITE_REVERB_KEY` de test), YAML Traefik, `sh -n` des scripts | compose valide ; `traefik.prod.yml` et `plateforme.yml.dist` parsés ; `init-prod.sh` et scripts de sauvegarde valides |
| 2026-10-29 | 6 | `npx playwright test e2e/moderation.spec.ts --list`, `tsc` + ESLint du parcours | 4 tests listés (suspension d'une copie signalée, compte suspendu puis rétabli, canaux, pages légales) ; exécution impossible : Docker arrêté (tenté : `ECONNREFUSED 127.0.0.1:5173`) |
| 2026-10-29 | 6 | Test de restauration (`scripts/restaurer-plateforme.sh`), démarrage prod, Playwright `moderation.spec.ts`, captures `/admin/moderation` + `/legal/*` (3 tailles × 2 thèmes), axe, Lighthouse | **non faits** : Docker arrêté sur le poste (`/var/run/docker.sock` absent, `docker info` sans serveur, `sudo` interdit) ; parcours et scripts écrits et relus, à exécuter à son retour (voir Problèmes ouverts) |
| 2026-10-29 | 6 | Relecture sécurité (sections 6-7 : Policies/middleware avant binding, anti-IDOR, argent hors navigateur, secrets jamais en clair, licences) | rien à corriger : cibles du signalement sur table fixe, suspensions avec motif et e-mail obligatoire, travaux moteur gardés, `DOMAINE` validé avant `sed`, secrets en volume ou `.env.prod` |
| 2026-10-29 | 6 | Après corrections de la revue : suite PHP sur l'hôte (dont 4 tests de non-régression : canaux refusés au suspendu, index d'unicité, `cible_chemin`, nettoyage distant) | 624 passés, 4 échecs connus : `PublicationAppliTest` demande GD, absent de l'hôte (vert dans le conteneur) |
| 2026-10-29 | 6 | Après corrections de la revue : Pint (461 fichiers), ESLint (tout le front), `tsc`, Vitest (dont `Moderation.test.tsx`), types régénérés depuis `openapi.yaml` | zéro erreur, 47/47 ; `api.ts` régénéré (`cible_chemin` sur `Signalement`) |
| 2026-10-29 | 6 | Après corrections de la revue : `composer audit`, `npm audit`, `composer check-licenses`, `npm run check-licenses` | aucune faille, aucune licence interdite |
| 2026-10-29 | 6 | Après corrections de la revue : `sh -n` du script de sauvegarde, faux `docker` en échec | syntaxe valide ; pg_dump en échec → code 1, rien d'écrit (avant : sauvegarde vide déclarée réussie) |
| 2026-10-29 | 6 | Captures `/admin/moderation` + `/legal/*`, Playwright `moderation.spec.ts`, restauration, démarrage prod | **toujours non faits** : Docker encore arrêté (`/var/run/docker.sock` absent) ; dette maintenue (voir Problèmes ouverts) |

## Problèmes ouverts

- 25 octobre : l'agent a vidé par erreur la base SQLite locale de l'hôte (`backend/database/database.sqlite`, sans sauvegarde) avec `migrate:fresh`. La plateforme Docker (PostgreSQL) n'est pas touchée. Règle ajoutée : ne plus jamais lancer de commande destructrice sur cette base.

- Tunnel public pour le webhook SasPay : refusé par le mode auto de Claude Code, en attente de l'utilisateur.
- Déploiement réel d'un dépôt GitHub (`octavebahoun/marchilearnia`) : refusé par le mode auto (« code externe »). À autoriser par l'utilisateur ; en attendant, le moteur est vérifié avec une appli locale écrite par l'agent.

- Performance Lighthouse sur mobile simulé (Moto G, 4G lente) entre 66 (fiche) et 79 pour une appli 100 % JavaScript, alors que le bureau est à 98. Gains déjà faits : animations chargées à part, coquille statique. Pistes pour l'audit de la phase 8 : pré-rendu des pages publiques, CSS critique en ligne, données de la page demandées plus tôt, poids de react-router et d'axios.
- Le compte SasPay fourni affiche le nom marchand « Gentube » sur la page de paiement : un compte au nom de Souk.dev sera nécessaire avant le lancement.
- 26 octobre : deux plateformes lancées sur le même dossier de copies (vérification à froid) se nettoient l'une l'autre ; une copie de test de la plateforme habituelle a été arrêtée. Sans conséquence (jeu de démo) ; à ne pas reproduire.
- 28 octobre : Docker arrêté sur le poste pendant le backend intégré (démon inactif, `sudo` interdit pour le relancer). La vérification Docker (suite en conteneur, démarrage à froid, Playwright dont `backend-integre.spec.ts`, captures 3 tailles × 2 thèmes, axe, Lighthouse) reste à faire avant de clore la phase 5 ; le parcours est écrit et relu, pas exécuté. Les 7 autres constats de la revue sont corrigés et testés ; le constat Docker (élevé) est revérifié toujours bloqué le 28 octobre au soir.
- 28 octobre : plusieurs serveurs livré sans Docker (même cause). Dette précise avant de clore la phase 5 : démarrer Docker, `docker compose up -d`, `docker compose --profile machines up -d dind`, vérifier la sonde du second démon dans `/admin/serveurs`, exécuter `npx playwright test e2e/machines.spec.ts` (3 tests : enregistrement + sonde, copie sur la plus libre et joignable, maintenance + retrait refusé), captures `/admin/serveurs` (`PAGES=/admin/serveurs npm run e2e:captures`, 3 tailles × 2 thèmes, à regarder et critiquer — avec les nouveaux états : vide illustré, libre sondé), axe (inclus dans le parcours) et Lighthouse. Limite assumée : routage direct v1 (voir `docs/machines.md`), bord par machine et TLS par machine conçus en phase 6 (`docs/multi-vps.md`) avant tout second VPS réel. Revérifié bloqué le 29 octobre (constat 5 de la revue des serveurs).
- 29 octobre : phase 6 livrée sans Docker (même cause : `/var/run/docker.sock` absent, `docker info` sans serveur, `sudo` interdit). Dette précise avant de clore la phase 6 : démarrer Docker, `docker compose up -d`, exécuter `npx playwright test e2e/moderation.spec.ts` (4 tests : copie signalée suspendue depuis l'admin, compte suspendu puis rétabli, canaux, pages légales), captures (`PAGES=/admin/moderation,/legal/cgu,/legal/cgv,/legal/confidentialite,/legal/abus npm run e2e:captures`, 3 tailles × 2 thèmes, à regarder et critiquer), axe (inclus dans le parcours) et Lighthouse ; puis côté production : `./scripts/sauvegarde-plateforme.sh` sur un jeu d'essai, `./scripts/restaurer-plateforme.sh` (doit finir par « RESTAURATION RÉUSSIE »), et `docker compose -f docker-compose.prod.yml up -d --build` avec un `DOMAINE` de test (front, API, Reverb, une copie HTTPS). Parcours et scripts écrits et relus, non exécutés. Revérifié bloqué après la revue de la phase 6 (constats 9 et 10 : 8 autres corrigés et testés).

## Dépenses et crédits

- Vertex AI (Nano Banana Pro, `gemini-3-pro-image`) : 5,76 $ / 10 $ (43 images, détail dans `scripts/visuels/depenses.json`).
- Logo.dev : 8 logos de réseaux Mobile Money récupérés une fois (16 crédits ; 486 restants sur 500 ce mois-ci).
