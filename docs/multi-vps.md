# Routage multi-VPS — Souk.dev

Conception du routage des copies sur des VPS séparés : TLS par machine et proxy en bordure
par machine. Elle lève les deux limites de `docs/machines.md` (routage direct v1, `tcp://`
sans TLS) avant tout second VPS réel. Aucun second VPS n'existe encore : ce document est
un plan, pas un mode d'emploi exécuté.

## Pourquoi le routage direct ne suffit plus

Aujourd'hui, Traefik central vise l'adresse du conteneur (`http://172.x:port`). Ça marche
quand les démons partagent l'espace réseau de l'hôte (démon local + DinD). Sur des VPS
séparés, l'adresse d'un conteneur du VPS B est injoignable depuis le VPS A. Et parler à
un démon distant en `tcp://` sans TLS exposerait Docker sans authentification.

## Topologie cible

```
navigateur ──HTTPS──▶ frontal (VPS plateforme, Traefik)
                        │ route par copie → bordure de SA machine
                        ▼                    (VPN WireGuard entre VPS)
                      bordure (chaque VPS, Traefik) ──HTTP──▶ conteneur local
```

- **Frontal** : le Traefik actuel. Il termine le TLS (Let's Encrypt) et connaît chaque
  copie : `Host(copie)` → `http://bordure-<machine>:8080`, en conservant le `Host`
  d'origine (pas de réécriture : la bordure route dessus).
- **Bordure** : un Traefik par VPS supplémentaire, minuscule : un fichier `copies.yml`
  (`Host(copie)` → adresse du conteneur local, comme aujourd'hui) et rien d'autre. Il
  n'est joignable que depuis le VPN.
- **VPN WireGuard** entre les VPS : tout le trafic inter-VPS (frontal → bordures, moteur
  → démons Docker) passe dedans. Deux commandes par machine, pas de PKI à gérer, chiffrement
  moderne. Le TLS Docker reste activé par-dessus (défense en profondeur).

Le frontal reste le point d'entrée unique (pas de haute disponibilité en v1 : une panne
du frontal coupe tout, comme aujourd'hui).

## TLS par machine (moteur → démons Docker)

Chaque machine distante présente des certificats serveur (CA, cert, clé), générés à
l'installation du démon (procédure Docker officielle, `dockerd --tlsverify`).

- Dépôt : l'équipe les dépose via l'API (`POST /admin/machines/{id}/tls`, multipart),
  stockés hors dépôt dans `storage/app/private/machines/{id}/tls/` (`chmod 600`), jamais
  ré-affichés. La sauvegarde plateforme les inclut (ils sont dans `stockage`).
- Usage : `MachineDocker` ajoute `DOCKER_TLS_VERIFY=1`, `DOCKER_CERT_PATH=<dossier>` et
  `DOCKER_HOST=tcp://…` aux commandes visant cette machine. En production, un `tcp://`
  distant sans TLS reste refusé (`422`, déjà en place).
- Rotation : re-dépôt puis sonde ; l'ancien jeu reste jusqu'au succès du nouveau.

## Livraison des routes aux bordures (push SSH)

Le moteur écrit déjà les routes du frontal (un fichier par copie). Pour la bordure, il
régénère un fichier unique `copies.yml` (toutes les copies locales de la machine) et le
pousse en SSH à chaque changement (mise en ligne, arrêt, suspension, retrait) :

- une clé Ed25519 par machine, générée par la plateforme, privée côté moteur
  (`storage/app/private/machines/{id}/ssh/`, `chmod 600`), publique sur la bordure pour
  un utilisateur `soukdev-routes` restreint à l'écriture du dossier (`authorized_keys`
  avec `command="cat > /routes/copies.yml"`, sans shell) ;
- `scp` avec timeout court (10 s) et 3 essais ; en cas d'échec, la copie passe en `echec`
  avec un message clair et l'équipe est alertée (comme tout incident moteur) ;
- la bordure recharge à la réception (fournisseur `file`, `watch: true`), comme le
  frontal aujourd'hui.

Variante future (si SSH pose problème) : les bordures tirent leurs routes (sidecar
`curl` + endpoint API authentifié par token de machine). Non retenue en v1 : un composant
de plus par bordure, pour le même résultat.

## Installation d'une bordure (script à écrire)

Sur un VPS Ubuntu vierge : Docker, Traefik binaire ou conteneur (fichier statique
`traefik.bordure.yml` : entrée `copies` sur `:8080`, fournisseur `file` sur `/routes`),
utilisateur `soukdev-routes`, WireGuard appairé au frontal, démon Docker en TLS. Le
script rend la main avec : l'empreinte TLS, la clé SSH publique attendue, l'IP VPN.
L'équipe enregistre ensuite la machine (`/admin/serveurs`, démon `tcp://<ip-vpn>:2376`,
capacité), dépose le TLS, vérifie la sonde, et lance une copie cobaye.

## Ce qui ne change pas

- Placement (machine active et joignable la plus libre), maintenance (drain), sondes,
  retrait refusé avec des copies : inchangés, ils visent déjà le démon de la copie.
- Pas de migration de copies : la maintenance draine naturellement.
- HTTPS des copies : toujours terminé au frontal (défis HTTP-01 par nom ; à volume,
  passer au joker par défi DNS-01, voir `docs/exploitation.md`).

## Mise en œuvre (phase future, avec un second VPS)

1. Modèle : `bordure_url`, dossier TLS/SSH par machine ; API : dépôt TLS, test de liaison.
2. Moteur : TLS par machine dans `MachineDocker`, génération et push de `copies.yml`.
3. Frontal : routes par copie vers les bordures (au lieu des conteneurs).
4. Fichiers : `traefik.bordure.yml`, script d'installation, `docs/exploitation.md` (ajout
   d'un serveur avec bordure).
5. Tests : TLS local (démon DinD en TLS), push vers une bordure simulée, parcours
   Playwright « copie sur machine distante, arrêt, suspension ».

Coûts : un aller-retour de plus par requête (frontal → bordure, quelques ms dans le VPN),
une clé SSH et des certificats par machine à garder (sauvegardés avec la plateforme).
