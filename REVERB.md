# Événements temps réel (Reverb) — Souk.dev

Complément de `openapi.yaml`, qui ne décrit pas les WebSockets.
Le back diffuse avec Laravel Reverb, le front écoute avec Laravel Echo.

## Règles

- **Payload = même JSON que l'API.** Chaque événement envoie un objet au format d'un schéma de `openapi.yaml` (ex. `Deploiement`). Le front réutilise donc les types générés par `openapi-typescript`.
- **Nom fixé avec `broadcastAs()`** côté Laravel. Côté Echo, on écoute avec un point devant : `.listen('.deploiement.etat', …)`.
- **Canal privé** dès que la donnée n'est pas publique dans l'API. L'autorisation se fait dans `routes/channels.php`, avec la session Sanctum.
- **Jamais de donnée réservée dans un canal public.** Exemple : le lien d'un événement en ligne n'est jamais diffusé, il ne passe que par `GET /events/{id}` pour les inscrits.

## Canaux

| Canal | Type | Qui peut écouter |
|---|---|---|
| `deploiement.{id}` | privé | Le dev qui a lancé le déploiement |
| `discussions` | public | Tout le monde (le fil) |
| `discussion.{id}` | public | Tout le monde (les discussions sont publiques) |
| `evenement.{id}` | public | Tout le monde |

## Événements

| Canal | Événement | Payload (schéma OpenAPI) | Quand |
|---|---|---|---|
| `deploiement.{id}` | `deploiement.etat` | `Deploiement` | À chaque changement d'état : `en_file` → `construction` → `demarrage` → `en_ligne` ou `echec` |
| `discussions` | `discussion.creee` | `Discussion` (sans `messages`) | Nouvelle discussion ; le front filtre lui-même par canal ou étiquette |
| `discussion.{id}` | `message.cree` | `Message` | Nouvelle réponse |
| `discussion.{id}` | `copie_test.etat` | `Deploiement` | Changement d'état de la copie de test de l'appli jointe |
| `discussion.{id}` | `correctif.propose` | `Correctif` | Nouveau correctif proposé |
| `discussion.{id}` | `correctif.etat` | `Correctif` | Changement d'état du déploiement d'un correctif |
| `discussion.{id}` | `correctif.accepte` | `Correctif` | L'auteur accepte un correctif |
| `evenement.{id}` | `inscriptions.maj` | `{ "nb_inscrits": integer }` | Inscription ou désinscription |

## Exemple côté front

```js
Echo.private(`deploiement.${id}`)
  .listen('.deploiement.etat', (deploiement) => {
    // deploiement a le type Deploiement généré depuis openapi.yaml
  });
```
