# Usage de l'IA — Souk.dev

Déclaration de l'usage de l'IA, comme demandé par le règlement CADEV 2026.
Mis à jour au fil du projet.

| Date | Outil | Partie du projet concernée |
|---|---|---|
| 2026-10-03 | Claude Code | Lecture du brief, création de ce fichier |
| 2026-10-03 | Claude Code | Rédaction de DECISIONS.md à partir des échanges avec l'équipe |
| 2026-10-03 | Claude Code | Création de LICENSES.md |
| 2026-10-04 | Claude Code | Rédaction de REVERB.md (canaux et événements temps réel) |
| 2026-10-05 | Claude Code | Audit du contrat d'API (AUDIT_CONTRAT.md) |
| 2026-10-06 | Claude Code | Report des décisions de l'audit : DECISIONS.md, openapi.yaml, REVERB.md, soukdev.schema.json |
| 2026-10-06 | Claude Code | Rédaction du plan de la semaine 1 (SEMAINE_1.md) et mise à jour des rôles dans DECISIONS.md |
| 2026-10-07 | jean-baptiste | Mise en place de la CI GitHub Actions (back et front), ajout de Vitest et des contrôles de licences |
| 2026-10-07 | mourchid | Validation de soukdev.json et génération du .env d'une copie (backend/app/Soukdev) |
| 2026-10-07 | Cursor | Lecture d'un dépôt GitHub public : clone superficiel, docker-compose.yml et soukdev.json (backend/app/Soukdev) |
| 2026-10-07 | mourchid | Lancement d'une copie isolée : .env conservé et docker compose -p (backend/app/Soukdev) |
| 2026-10-07 | Cursor (Grok) | Moteur de déploiement : validateur soukdev.json, générateur .env, clone GitHub, commande artisan moteur:lancer |
| 2026-10-09 | Cursor (Grok) | Moteur : refus d'un docker-compose.yml dangereux (privileged, volume de l'hôte, network_mode host, ports publiés) |
| 2026-10-09 | Cursor (Grok) | Moteur : soukdev.schema.json rangé dans backend/ |
| 2026-10-09 | Cursor (Grok) | Moteur : la copie n'est en ligne que si service_web répond |
| 2026-10-09 | Cursor (Grok) | Moteur : file d'attente, états du déploiement et événement Reverb deploiement.etat |
| 2026-10-09 | Cursor (Grok) | Moteur : arrêt d'une copie (arrete) et plafonds mémoire/CPU selon la taille |
| 2026-10-10 | Cursor (Grok) | Moteur : mesure du pic mémoire après publication et formule recommandée (backend/app/Moteur, job MesurerMemoire) |
| 2026-10-10 | Cursor (Grok) | Moteur : backend intégré, application des migrations .sql et injection de SOUKDEV_URL / SOUKDEV_CLE |
| 2026-10-10 | Cursor (Grok) | Publication : POST /apps valide le dépôt puis lance demanderMesure() |
