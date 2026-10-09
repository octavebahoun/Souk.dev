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

];
