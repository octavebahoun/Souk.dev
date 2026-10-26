# Souk AI

L'analyse IA d'un bug, à côté des correctifs de la communauté (DECISIONS.md, ajout 6). Code dans
`backend/app/Ia/`, `AnalyseIaController`, `AnalyserBug` ; contrat : `POST /discussions/{id}/analyse-ia`
et le champ `analyse_ia` de la discussion ; temps réel : `analyse_ia.terminee` sur `discussion.{id}`.

## Le parcours

1. Sur une discussion avec l'étiquette `bug`, **seul son auteur** voit « Demander l'avis de Souk AI ».
2. Une fenêtre dit ce qui part chez DeepSeek (titre, description, erreur, comportement attendu, code)
   et que l'analyse sera publique. Rien ne part sans la case cochée : l'API exige `consentement: true`
   (422 sinon) et garde la date du consentement.
3. `202` : l'analyse part en file (`en_cours`). Le travail appelle DeepSeek, enregistre le texte et
   diffuse `analyse_ia.terminee` ; la page se met à jour sans recharger (relecture toutes les 4 s
   sans Reverb).
4. La réponse (Markdown : cause probable, correction proposée, pour vérifier) est rendue sans HTML
   brut, visible de tous. Elle ne résout jamais la discussion ; l'auteur peut la relancer.

## Limites et refus

| Cas | Réponse |
|---|---|
| Pas connecté | 401 |
| Pas l'auteur | 403 |
| Pas d'étiquette `bug`, pas de consentement | 422 |
| Une analyse de ce bug déjà en cours | 409 |
| Plus de 3 demandes en 24 heures (les échecs ne comptent pas) | 429, avec `Retry-After` et l'heure de la prochaine |

La limite est glissante (24 heures depuis la plus ancienne des trois) et se vérifie sous le verrou du
compte : deux demandes simultanées ne passent pas toutes les deux.

## Le fournisseur

- **API officielle DeepSeek** (`https://api.deepseek.com/chat/completions`, format OpenAI), modèle
  `deepseek-flash` (DeepSeek-V4.1-Flash, lu dans `GET /models` le 26 octobre 2026), effort de
  raisonnement `high`, 16 000 jetons au plus (raisonnement compris). Réglage : `DEEPSEEK_MODELE`.
- Derrière l'interface `FournisseurIa` : un autre fournisseur se branche sans toucher au reste.
  `IaSimulee` sert aux tests et au travail hors ligne (`IA_FOURNISSEUR=simulee`, ou clé absente en
  développement) ; il est refusé en production.
- **Panne** (réseau, délai, 429, 5xx, ressources insuffisantes) : deux nouveaux essais (15 s puis
  60 s), puis « analyse indisponible ». **Refus** (clé refusée, solde épuisé, réponse vide, filtrée ou
  coupée) : « analyse indisponible » tout de suite ; une clé refusée ou un solde épuisé prévient
  l'équipe (`ADMIN_EMAIL`).

## Ce qui part, ce qui reste

- La consigne fixe traite la fiche comme des **données, jamais des instructions** (balises `<bug>`).
- Les secrets repérés dans la fiche et le code (clés Stripe ou SasPay, `sk-…`, AWS, GitHub, Slack,
  Google, JWT, clés privées, `password = …`) sont **masqués avant l'envoi**.
- Les journaux gardent le statut HTTP, le modèle et la consommation ; jamais la clé, la consigne ni
  le code. Chaque demande garde ses jetons (suivi du coût).

## Coût

Une analyse lit environ 400 jetons et en écrit environ 1 000 (raisonnement compris) : quelques
millièmes de dollar au tarif de DeepSeek. Trois par jour et par dev bornent la dépense.
