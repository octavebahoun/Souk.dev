<?php

/*
|--------------------------------------------------------------------------
| Facturation et encaissement
|--------------------------------------------------------------------------
|
| Le client paie chaque mois l'hébergement de sa copie et le prix de l'appli
| (chaque mois, ou une seule fois). Le prix de l'appli va entièrement au dev ;
| Souk.dev ne gagne que sur l'hébergement. Montants en entiers FCFA (XOF).
| Voir BRIEF.md, section 6, et docs/paiement.md.
|
*/

return [

    'devise' => 'XOF',

    // Prix d'hébergement par formule, par mois (DECISIONS.md, « Business model »).
    'hebergement' => [
        'petite' => 3500,
        'moyenne' => 7000,
        'grande' => 13500,
    ],

    // Rappel par e-mail avant l'échéance, grâce après, suspension, puis arrêt définitif.
    'jours_rappel' => 3,
    'jours_grace' => 7,
    'jours_avant_arret' => 30,
    'jours_avertissement_arret' => 7,

    // Reversements aux devs : chaque mois, ou à la demande au-delà du seuil.
    'seuil_reversement' => 5000,
    'jour_reversement_mensuel' => 1,

    // Passerelle d'encaissement : « saspay » (clé présente) ou « simulee ».
    'passerelle' => env('PAIEMENT_PASSERELLE') ?: (env('SASPAY_SECRET_KEY') ? 'saspay' : 'simulee'),

    // Les reversements passent par le simulateur tant que ceci n'est pas explicitement activé
    // (ils feraient sortir de l'argent du compte, et SasPay exige une IP en liste blanche).
    'reversements_reels' => (bool) env('REVERSEMENTS_REELS', false),

    // Webhooks envoyés par le simulateur : l'adresse de l'API vue depuis le worker.
    'webhook_simulateur' => env('SIMULATEUR_WEBHOOK_URL') ?: rtrim((string) env('APP_URL', 'http://localhost:8000'), '/').'/api/webhooks/saspay',

    // Page de retour après un paiement (front).
    'retour_paiement' => rtrim((string) env('FRONTEND_URL', 'http://localhost:5173'), '/').'/paiement/retour',

    // Réseaux Mobile Money acceptés pour un reversement (codes SasPay, voir docs.saspay.me).
    'reseaux' => [
        ['code' => 'mtn_bj', 'nom' => 'MTN MoMo', 'pays' => 'BJ'],
        ['code' => 'moov_bj', 'nom' => 'Moov Money', 'pays' => 'BJ'],
        ['code' => 'celtiis_bj', 'nom' => 'Celtiis Cash', 'pays' => 'BJ'],
        ['code' => 'mtn_ci', 'nom' => 'MTN MoMo', 'pays' => 'CI'],
        ['code' => 'moov_ci', 'nom' => 'Moov Money', 'pays' => 'CI'],
        ['code' => 'orange_ci', 'nom' => 'Orange Money', 'pays' => 'CI'],
        ['code' => 'wave_ci', 'nom' => 'Wave', 'pays' => 'CI'],
        ['code' => 'orange_sn', 'nom' => 'Orange Money', 'pays' => 'SN'],
        ['code' => 'wave_sn', 'nom' => 'Wave', 'pays' => 'SN'],
        ['code' => 'freemoney_sn', 'nom' => 'Free Money', 'pays' => 'SN'],
        ['code' => 'moov_tg', 'nom' => 'Moov Money', 'pays' => 'TG'],
        ['code' => 'togocel', 'nom' => 'Togocel Money', 'pays' => 'TG'],
        ['code' => 'moov_bf', 'nom' => 'Moov Money', 'pays' => 'BF'],
        ['code' => 'orange_bf', 'nom' => 'Orange Money', 'pays' => 'BF'],
        ['code' => 'orange_ml', 'nom' => 'Orange Money', 'pays' => 'ML'],
        ['code' => 'moov_ml', 'nom' => 'Moov Money', 'pays' => 'ML'],
        ['code' => 'airtel_ne', 'nom' => 'Airtel Money', 'pays' => 'NE'],
    ],

    // Indicatifs et longueur du numéro local, pour normaliser et masquer un numéro.
    'pays' => [
        'BJ' => ['indicatif' => '229', 'longueur' => 10],
        'CI' => ['indicatif' => '225', 'longueur' => 10],
        'SN' => ['indicatif' => '221', 'longueur' => 9],
        'TG' => ['indicatif' => '228', 'longueur' => 8],
        'BF' => ['indicatif' => '226', 'longueur' => 8],
        'ML' => ['indicatif' => '223', 'longueur' => 8],
        'NE' => ['indicatif' => '227', 'longueur' => 8],
    ],

];
