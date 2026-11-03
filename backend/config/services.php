<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Encaissement (et reversements) : clé secrète côté serveur seulement, jamais journalisée.
    'saspay' => [
        'cle' => env('SASPAY_SECRET_KEY'),
        'secret_webhook' => env('SASPAY_WEBHOOK_SECRET'),
        'url' => env('SASPAY_BASE_URL', 'https://api.saspay.me/api/v1'),
        'delai' => 20,
        'tolerance_webhook' => 300,
    ],

    // Souk AI : API officielle DeepSeek (compatible OpenAI), clé côté serveur, jamais journalisée.
    // IA_FOURNISSEUR=simulee pour les tests et le développement hors ligne (refusé en production).
    'ia' => [
        'fournisseur' => env('IA_FOURNISSEUR', 'deepseek'),
        'par_jour' => 3,
    ],

    'deepseek' => [
        'cle' => env('DEEPSEEK_API_KEY'),
        'url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
        'modele' => env('DEEPSEEK_MODELE', 'deepseek-flash'),
        'delai' => 90,
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/auth/github/callback'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
