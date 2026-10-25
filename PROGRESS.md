# PROGRESS — Souk.dev, SaaS complet

Journal de l'agent, tenu à jour en continu (voir `BRIEF.md`, section 1).
À relire avec `BRIEF.md` et `DECISIONS.md` au début de chaque session.

## Où on en est

- **Branche** : `saas-complet`, partie de `main` (la PR #28 `fin-mvp` y est fusionnée).
- **Phase en cours** : 1 — Socle.
- **Tâche suivante** : système de design (tokens Systalink, Outfit, composants), puis captures « avant ».

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
- [ ] Système de design : `docs/design.md`, tokens dans `frontend/src/index.css`, police Outfit, bibliothèque de composants
- [ ] Sortie : `docker compose up` à froid démarre tout, CI verte, aucun test sauté

### Phase 2 — Paiement (SasPay)
- [ ] Adaptateurs `PasserellePaiement` (SasPay, simulée) et reversements (simulés)
- [ ] Simulateur SasPay : page de paiement locale, statuts `PENDING`/`SUCCESS`/`FAILED`, webhooks signés
- [ ] Factures à date anniversaire, rappel J-3, grâce 7 jours, suspension, réactivation, arrêt définitif à 30 jours
- [ ] Webhook signé et idempotent, revérification par l'API, rapprochement (15 min et quotidien)
- [ ] Grand livre en partie double, en entiers FCFA, sans modification (écritures inverses)
- [ ] Reversements : solde, coordonnées Mobile Money confirmées, mensuel ou à la demande (≥ 5 000 F)
- [ ] Avoirs saisis par l'admin
- [ ] Écrans : paiement et retour, « Mes paiements », « Mes revenus », vue financière admin

### Phase 3 — Confiance
- [ ] Sécurité avant publication : gitleaks et Trivy en conteneurs jetables, statuts, badge, relance
- [ ] Avis et notes des applis (clients ayant eu une copie)
- [ ] Notifications : centre dans l'appli, e-mails, préférences par type

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

À remplir écran par écran avant de le redessiner.

## Vérifications

| Date | Phase | Contrôle | Résultat |
|---|---|---|---|
| 2026-10-25 | 0 | `php artisan test` (état de départ) | 356/361 : 1 échec `RepositoryReaderTest` (locale), 4 erreurs GD (`PublicationAppliTest`) |
| 2026-10-25 | 1 | `docker compose up` à froid (volumes vides) | 8 services sains, migrations PostgreSQL passées |
| 2026-10-25 | 1 | Copie locale déployée par le moteur conteneurisé | sonde OK, `http://essai-locale.localhost` → 200 via Traefik |
| 2026-10-25 | 1 | `php artisan test` dans le conteneur PHP | 380/380, aucun test sauté |

## Problèmes ouverts

- Tunnel public pour le webhook SasPay : refusé par le mode auto de Claude Code, en attente de l'utilisateur.
- Déploiement réel d'un dépôt GitHub (`octavebahoun/marchilearnia`) : refusé par le mode auto (« code externe »). À autoriser par l'utilisateur ; en attendant, le moteur est vérifié avec une appli locale écrite par l'agent.

## Dépenses et crédits

- Vertex AI (Nano Banana Pro) : 0 $ / 10 $.
- Logo.dev : aucun logo récupéré.
