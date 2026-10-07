<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Moteur de déploiement
    |--------------------------------------------------------------------------
    |
    | Le moteur clone un dépôt GitHub public, valide soukdev.json, écrit un
    | .env par copie, puis lance docker compose -p <copie>.
    |
    */

    'schema' => dirname(base_path()).'/soukdev.schema.json',

    'copies' => storage_path('app/private/copies'),

    'git_timeout' => 120,

    'compose_timeout' => 600,

];
