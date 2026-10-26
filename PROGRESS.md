# PROGRESS — Souk.dev, SaaS complet

Journal de l'agent, tenu à jour en continu (voir `BRIEF.md`, section 1).
À relire avec `BRIEF.md` et `DECISIONS.md` au début de chaque session.

## Où on en est

- **Branche** : `saas-complet`, partie de `main` (la PR #28 `fin-mvp` y est fusionnée).
- **Phase en cours** : 4 — Intelligence (phase 3 terminée : revue indépendante faite, 15 constats traités).
- **Tâche suivante** : Souk AI (DeepSeek) — route, file, consentement, limite de 3 par 24 heures, résultat par Reverb.

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
- [ ] Souk AI : DeepSeek, consentement, 3 par jour, résultat par Reverb

### Phase 5 — Plateforme
- [ ] Mises à jour poussées (sauvegarde des volumes, retour arrière si la sonde échoue)
- [ ] Backend intégré complet : connexion SMS et e-mail des utilisateurs finaux, sauvegarde quotidienne, export
- [ ] Plusieurs serveurs : liste, placement, maintenance, état ; test local avec deux machines

### Phase 6 — Exploitation
- [ ] Administration et modération (signalements, suspensions, canaux, finances, serveurs, politique d'abus)
- [ ] `docker-compose.prod.yml`, sauvegardes avec test de restauration, journaux JSON, alertes, nettoyage des images
- [ ] Pages légales (brouillons à faire valider par un juriste)

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

## Problèmes ouverts

- 25 octobre : l'agent a vidé par erreur la base SQLite locale de l'hôte (`backend/database/database.sqlite`, sans sauvegarde) avec `migrate:fresh`. La plateforme Docker (PostgreSQL) n'est pas touchée. Règle ajoutée : ne plus jamais lancer de commande destructrice sur cette base.

- Tunnel public pour le webhook SasPay : refusé par le mode auto de Claude Code, en attente de l'utilisateur.
- Déploiement réel d'un dépôt GitHub (`octavebahoun/marchilearnia`) : refusé par le mode auto (« code externe »). À autoriser par l'utilisateur ; en attendant, le moteur est vérifié avec une appli locale écrite par l'agent.

- Performance Lighthouse sur mobile simulé (Moto G, 4G lente) entre 66 (fiche) et 79 pour une appli 100 % JavaScript, alors que le bureau est à 98. Gains déjà faits : animations chargées à part, coquille statique. Pistes pour l'audit de la phase 8 : pré-rendu des pages publiques, CSS critique en ligne, données de la page demandées plus tôt, poids de react-router et d'axios.
- Le compte SasPay fourni affiche le nom marchand « Gentube » sur la page de paiement : un compte au nom de Souk.dev sera nécessaire avant le lancement.
- 26 octobre : deux plateformes lancées sur le même dossier de copies (vérification à froid) se nettoient l'une l'autre ; une copie de test de la plateforme habituelle a été arrêtée. Sans conséquence (jeu de démo) ; à ne pas reproduire.

## Dépenses et crédits

- Vertex AI (Nano Banana Pro, `gemini-3-pro-image`) : 5,49 $ / 10 $ (41 images, détail dans `scripts/visuels/depenses.json`).
- Logo.dev : 8 logos de réseaux Mobile Money récupérés une fois (16 crédits ; 486 restants sur 500 ce mois-ci).
