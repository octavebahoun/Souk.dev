# Audit du frontend — 6 novembre 2026

Branche `saas-complet`. Vérifs automatiques : types OK, lint OK, 50 tests unitaires OK,
build OK, `npm audit` 0 faille. Les problèmes ci-dessous ont été trouvés en lisant le code.

**Statut : les 8 points et le ménage sont corrigés.** Après correction : types, lint,
52 tests (dont `frontend/src/app/deconnexion.test.tsx`, nouveau) et build OK. Un build avec
`VITE_API_BASE` pointe bien GitHub et l'export vers l'API. Parcours Playwright non relancés
(pile Docker arrêtée).

## 🔴 Cassé en production

1. **Connexion GitHub** — `frontend/src/features/comptes/Connexion.tsx:108`
   Le lien `/auth/github` est relatif : en prod il part sur `https://$DOMAINE` (nginx du front),
   pas sur `api.$DOMAINE`. Résultat : page 404. En dev ça marche grâce au proxy Vite.
   Même souci dans la doc : `docs/exploitation.md:26` met le rappel GitHub sur le front.
   → Préfixer avec `VITE_API_BASE` ; rappel sur `https://api.$DOMAINE/auth/github/callback`.

2. **Export des sauvegardes** — `frontend/src/features/catalogue/api.ts:148`
   `lienSauvegarde()` renvoie `/api/deployments/…` relatif : en prod, le bouton « Exporter »
   télécharge `index.html` au lieu de la sauvegarde.
   → Même correctif : préfixer avec `VITE_API_BASE`.

## 🟠 Bugs visibles

3. **Déconnexion depuis une page publique** — `frontend/src/app/compte.ts:40`
   Après « Se déconnecter » depuis le Store ou l'Échange, l'en-tête montre encore l'avatar,
   la cloche et « Publier ». Vérifié par un test jetable (depuis `/tableau`, ça marche).
   Cause : `cache.clear()` détache les requêtes déjà affichées, qui gardent l'ancien compte.
   → `cache.setQueryData(['moi'], null)` puis `cache.removeQueries({ predicate: q => q.queryKey[0] !== 'moi' })`.

4. **Messages de développeur montrés aux utilisateurs**
   - `frontend/src/features/echange/api.ts:82` : « Démarrez Laravel sur le port 8000 » sur toute coupure réseau.
   - `frontend/src/features/comptes/Connexion.tsx:38` : « Vérifiez l'application OAuth et son URL de rappel ».
   → Textes pour le client (« Connexion perdue, réessayez »).

## 🟡 À améliorer

5. **Recherche du Store** — `frontend/src/features/catalogue/Store.tsx:103`
   Une requête et un squelette à chaque lettre tapée. Lourd sur un réseau mobile lent.
   → Attendre ~300 ms après la frappe et garder les anciens résultats (`placeholderData`).

6. **Visiteur sur un écran privé** — `/publier`, `/notifications`, `/revenus`, `/paiements`, `/admin/*`
   Erreur générique au lieu de « Connectez-vous ». Sur `/publier`, il remplit tout le formulaire
   avant d'apprendre qu'il faut un compte.
   → Une garde commune dans `LayoutEspace` (comme `Tableau` le fait déjà).

7. **Clé d'idempotence changée sur coupure réseau** — `frontend/src/features/paiements/Commande.tsx:116`
   La clé est regénérée à chaque erreur, y compris quand la réponse s'est perdue : c'est
   justement le cas qu'elle doit protéger. → Ne la changer que sur une erreur reçue du serveur.

8. **Reverb configuré mais injoignable** (à vérifier) — `frontend/src/features/echange/tempsReel.ts`
   Le repli vers la relecture toutes les 15 s ne se déclenche que si la bibliothèque ne charge pas,
   pas si le serveur Reverb est en panne : le fil ne bouge plus.

## ⚪ Ménage

- Code mort : `client` et `cheminApi` (restes de Prism) dans `shared/client.ts`,
  `liensEspaceAVenir` dans `app/navigation.ts`, `PageEnAttente` + `brancherComptes`
  (routes posées puis remplacées), dossiers vides `components/` et `pages/` avec `.gitkeep`.
- `MesCopies.tsx` est le seul fichier en guillemets doubles et points-virgules.

## ✅ Ce qui est bien

Chargement par route, CSP stricte par empreinte, démos en iframe `sandbox`, clés
d'idempotence sur les paiements, modales accessibles (focus piégé, Échap, retour du focus),
thème sans flash, états vide / erreur / chargement presque partout.
