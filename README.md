# Souk.dev

**Le marché des applis des devs africains.** Un dev publie son appli ; une pharmacie, une
association ou une boutique en déploie sa propre copie en un clic, paie par Mobile Money en
FCFA, et la voit tourner sur son adresse. Souk.dev facture l'hébergement chaque mois et reverse
le prix de l'appli au dev, **sans commission**. Autour, l'Échange : les devs s'entraident sur
leurs bugs, se proposent des correctifs et les essaient sur des copies de test.

![Accueil de Souk.dev : « Le marché des applis des devs africains »](docs/captures/accueil.webp)

| Le Store | La fiche d'une appli |
|---|---|
| ![Store : recherche, catégories, applis avec prix et badge de sécurité](docs/captures/store.webp) | ![Fiche : captures, prix détaillé, 0 F de commission, bouton « Déployer ma copie »](docs/captures/fiche.webp) |
| **Mes copies** | **Une discussion de l'Échange** |
| ![Mes copies : état de chaque copie, son adresse, sa prochaine facture](docs/captures/copies.webp) | ![Discussion : fiche du bug, messages, correctifs proposés, copie de test](docs/captures/discussion.webp) |

<p align="center"><img src="docs/captures/mobile-sombre.webp" width="300" alt="Le Store sur mobile, en thème sombre"></p>

## Ce que fait Souk.dev

**Pour les devs**
- Publier une appli depuis un dépôt GitHub public : vérification du `docker-compose.yml` et
  du `soukdev.json`, recherche de secrets (gitleaks) et de failles (Trivy) avant la mise en
  ligne, mémoire mesurée et formule d'hébergement recommandée.
- Être payé sur son Mobile Money, sans commission ; suivre vues, copies et revenus.
- L'Échange : canaux, bugs structurés, messages, correctifs (proposer, accepter, lancer la
  version corrigée), copie de test et démo dans un cadre isolé, Souk AI, temps réel.
- Réputation (XP, niveaux, badges, Souk Score), missions, événements.

**Pour les clients**
- Trouver l'appli qui fait tourner son activité, la commander, payer (Mobile Money ou carte,
  SasPay), et l'ouvrir sur son sous-domaine quelques minutes plus tard.
- Factures mensuelles, rappel avant échéance, suspension puis réactivation selon le paiement,
  mises à jour avec retour arrière, sauvegardes et export de ses données.
- Noter les applis, demander une version sur mesure, recruter un dev.

**Pour l'équipe**
- Vue financière tirée d'un grand livre inaltérable, reversements, avoirs.
- Modération (signalements, suspensions, politique d'abus automatique), plusieurs serveurs,
  sauvegardes, alertes.

## Installer en moins de 10 minutes

Il faut Docker (avec Compose v2) et Git ; les ports 80, 5173, 8000 et 8080 libres. Rien
d'autre : PHP, Node et PostgreSQL tournent dans les conteneurs.

```sh
git clone https://github.com/octavebahoun/Souk.dev.git
cd Souk.dev
docker compose up -d            # premier démarrage : 3 à 5 minutes (images, dépendances, migrations)

# Le jeu de démo : comptes, applis, discussions, factures (identifié comme tel)
docker compose exec -T -u 1000 api php artisan db:seed --class=DemoSeeder
# Les dépôts des applis de démo, servis en local : une commande construit une vraie copie
docker compose exec -T -u 1000 api php artisan demo:miroirs
```

Puis ouvrez **http://localhost:5173**. Sur la page de connexion, en développement, le bloc
« Comptes de démo » ouvre une session sans e-mail ni GitHub :

| Compte | Pour essayer |
|---|---|
| Awa Dossou (dev) | publier, l'Échange, ses revenus et son reversement |
| Pharmacie du Port (client) | commander PharmaStock, payer sur le simulateur, ouvrir sa copie |
| Équipe Souk.dev (admin) | finances, serveurs, modération |

Le paiement passe par le **simulateur SasPay** (aucun argent réel) ; les copies s'ouvrent sur
`http://{copie}.localhost`. Les clés réelles (SasPay, DeepSeek, Resend, GitHub) se mettent dans
`backend/.env`, jamais commité ; sans elles, chaque service utilise son adaptateur simulé.

Mettre en production (HTTPS, domaine des copies à part, sauvegardes) : [docs/exploitation.md](docs/exploitation.md).

## Vérifier

```sh
docker compose exec -T api php artisan test          # PHPUnit : API, moteur, argent, contrat
cd frontend
npm ci && npx playwright install chromium            # une fois, sur la machine
npm test                                             # Vitest
npm run lint && npx tsc -b                           # ESLint, TypeScript
npm run e2e                                          # Playwright contre la plateforme lancée
npm run e2e:captures                                 # captures 3 tailles × 2 thèmes
```

## Documentation

| | |
|---|---|
| [Architecture](docs/architecture.md) | services, chemin d'une requête, moteur, argent, temps réel |
| [Paiement et grand livre](docs/paiement.md) | facturation, webhook, rapprochement, reversements, avoirs |
| [Exploitation](docs/exploitation.md) | production, sauvegarde et restauration, journaux, alertes, secrets, serveurs |
| [Modération](docs/moderation.md) | signalements, sanctions, politique d'abus |
| [Plusieurs serveurs](docs/machines.md) · [multi-VPS](docs/multi-vps.md) | machines, sondes, placement |
| [Mises à jour](docs/mises-a-jour.md) · [Backend intégré](docs/backend-integre.md) · [Souk AI](docs/souk-ai.md) | |
| [Design](docs/design.md) | tokens, composants, règles d'écran |
| [`openapi.yaml`](openapi.yaml) · [`REVERB.md`](REVERB.md) | le contrat de l'API, les événements temps réel |
| [`RELEASE_NOTES.md`](RELEASE_NOTES.md) | ce qui est livré, vérifié, et les limites connues |
| [`DECISIONS.md`](DECISIONS.md) · [`PROGRESS.md`](PROGRESS.md) | les choix et leur raison, l'avancement et les vérifications |
| [`LICENSES.md`](LICENSES.md) · [`AI_USAGE.md`](AI_USAGE.md) | licences des dépendances, usage de l'IA |

## Pile

Laravel 13 (PHP 8.4), PostgreSQL 16, Laravel Reverb, React 19, Vite, TypeScript, Tailwind CSS 4,
Traefik, Docker. Dépendances sous licences permissives uniquement (MIT, Apache 2.0, BSD, ISC,
PostgreSQL) : voir [LICENSES.md](LICENSES.md).

Né pour le concours CADEV 2026 : le brief d'origine est dans [docs/brief-concours.md](docs/brief-concours.md).
