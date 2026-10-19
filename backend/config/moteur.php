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

    'git_timeout' => 120,

    'compose_timeout' => 600,

    // URL publique une fois la copie en ligne. {copie} devient l'identifiant Compose.
    'url_copie' => 'http://{copie}.localhost',

    // Après la publication : durée de lecture du pic, puis écart entre deux docker stats.
    'mesure_duree' => 180,

    'mesure_intervalle' => 5,

    // Backend intégré : images épinglées, et essais en attendant que Postgres réponde.
    'backend_image_postgres' => 'postgres:16-alpine',

    'backend_image_postgrest' => 'postgrest/postgrest:v12.2.3',

    'backend_tentatives' => 30,

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
