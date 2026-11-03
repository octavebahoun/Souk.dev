<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Moteur de déploiement
    |--------------------------------------------------------------------------
    |
    | Le moteur clone un dépôt GitHub public, valide soukdev.json, écrit un
    | .env par copie, lance docker compose -p <copie>, puis vérifie que
    | le service web répond avant de déclarer la copie en ligne.
    |
    */

    'schema' => base_path('soukdev.schema.json'),

    'copies' => storage_path('app/private/copies'),

    // Sauvegardes des volumes des copies (avant une mise à jour, chaque nuit) et durée de garde.
    'sauvegardes' => env('MOTEUR_SAUVEGARDES', storage_path('app/private/sauvegardes')),
    'sauvegardes_jours' => (int) env('MOTEUR_SAUVEGARDES_JOURS', 7),
    'sauvegarde_timeout' => (int) env('MOTEUR_SAUVEGARDE_TIMEOUT', 900),

    'git_timeout' => 120,

    'compose_timeout' => 600,

    // URL publique une fois la copie en ligne. {copie} devient l'identifiant Compose.
    'url_copie' => env('MOTEUR_URL_COPIE', 'http://{copie}.localhost'),

    // Reverse proxy : Traefik lit ici un fichier de routes par copie en ligne (voir infra/proxy/).
    'proxy_routes' => env('MOTEUR_PROXY_ROUTES', storage_path('app/private/proxy')),

    // Sonde du service web : conteneur jetable branché sur le réseau de la copie.
    'image_sonde' => env('MOTEUR_IMAGE_SONDE', 'curlimages/curl:8.11.1'),

    // Sécurité avant publication : images épinglées des conteneurs d'analyse (licences MIT et
    // Apache 2.0), volume de la base des failles de Trivy, attente maximale de l'analyse des secrets.
    'image_gitleaks' => env('MOTEUR_IMAGE_GITLEAKS', 'zricethezav/gitleaks:v8.21.2'),
    'image_trivy' => env('MOTEUR_IMAGE_TRIVY', 'aquasec/trivy:0.58.1'),
    'volume_trivy' => env('MOTEUR_VOLUME_TRIVY', 'soukdev-trivy'),
    'delai_analyse_secrets' => (int) env('MOTEUR_DELAI_ANALYSE_SECRETS', 90),

    // Développement seulement : dossier de dépôts Git locaux ({owner}/{repo}.git) clonés à la place
    // de GitHub pour les applis du jeu de démo (php artisan demo:miroirs). Ignoré hors local et tests.
    'miroirs' => env('MOTEUR_MIROIRS'),

    // Traefik applique les changements de routes au plus toutes les 2 secondes : la copie n'est
    // annoncée en ligne qu'après ce délai, sinon un clic trop rapide tomberait sur un 404.
    'delai_proxy' => (int) env('MOTEUR_DELAI_PROXY', 3),

    // Page « copie suspendue » (impayé) : l'API vue depuis Traefik. En développement, Traefik est
    // sur le réseau de la machine et joint l'API sur son port publié.
    'page_suspendue' => env('MOTEUR_PAGE_SUSPENDUE', 'http://127.0.0.1:8000'),

    // Après la publication : durée de lecture du pic, puis écart entre deux docker stats.
    'mesure_duree' => 180,

    'mesure_intervalle' => 5,

    // Backend intégré : images épinglées, et essais en attendant que Postgres réponde.
    'backend_image_postgres' => 'postgres:16-alpine',

    'backend_image_postgrest' => 'postgrest/postgrest:v12.2.3',

    'backend_tentatives' => 30,

    // Arrêt d'une copie : essais de docker compose down avant de passer en echec, et délai de chacun.
    'arret_tentatives' => 2,

    'down_timeout' => 120,

    // Durée de vie d'une copie de test (en minutes), écrite dans expire_le au lancement.
    'copie_test_minutes' => 120,

    // Durcissement imposé à chaque conteneur (voir App\Moteur\Durcissement).
    // Plafond de processus par formule : bloque une fork bomb sans gêner un serveur web.
    'pids_limit' => [
        'petite' => 256,
        'moyenne' => 512,
        'grande' => 1024,
    ],

    // Toutes les capacités sont retirées (cap_drop: ALL), puis seules celles-ci sont rendues.
    // Le minimum pour nginx, postgres, node et php-fpm. Un cap_add du dépôt hors de cette liste est refusé.
    'capacites_autorisees' => ['CHOWN', 'DAC_OVERRIDE', 'FOWNER', 'KILL', 'NET_BIND_SERVICE', 'SETGID', 'SETUID'],

];
