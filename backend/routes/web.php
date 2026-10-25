<?php

use App\Http\Controllers\CoordonneesReversementController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\GithubController;
use App\Http\Controllers\LienMagiqueController;
use App\Http\Controllers\LienPaiementController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\SimulateurSasPayController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/github', [GithubController::class, 'redirect'])->middleware('throttle:60,1');
Route::get('/auth/github/callback', [GithubController::class, 'callback']);
Route::post('/auth/lien-magique', [LienMagiqueController::class, 'demander'])->middleware('throttle:lien-magique');
Route::get('/auth/lien-magique/{token}', [LienMagiqueController::class, 'utiliser'])
    ->where('token', '[A-Za-z0-9]+');
Route::post('/logout', [LogoutController::class, 'destroy']);
Route::post('/auth/demo', [DemoController::class, 'connecter'])->middleware('throttle:60,1');

// Lien de paiement signé envoyé par e-mail (payer sans se connecter).
Route::get('/paiement/facture/{id}', [LienPaiementController::class, 'ouvrir'])
    ->whereNumber('id')
    ->middleware(['signed', 'throttle:paiements'])
    ->name('paiement.facture');

// Simulateur SasPay : développement et tests seulement (introuvable avec la vraie passerelle).
Route::get('/simulateur/saspay/{session}', [SimulateurSasPayController::class, 'afficher']);
Route::post('/simulateur/saspay/{session}', [SimulateurSasPayController::class, 'choisir']);

// Confirmation du numéro de reversement : le lien de l'e-mail ouvre une page, le bouton confirme.
Route::get('/reversement/confirmer/{token}', [CoordonneesReversementController::class, 'afficher'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->middleware('throttle:30,1');
Route::post('/reversement/confirmer/{token}', [CoordonneesReversementController::class, 'confirmer'])
    ->where('token', '[A-Za-z0-9]{48}')
    ->middleware('throttle:30,1');

// Page affichée par Traefik sur le sous-domaine d'une copie suspendue pour impayé.
Route::get('/copie-suspendue', function () {
    return response()->view('plateforme.copie-suspendue', [
        'accueil' => rtrim((string) config('app.frontend_url'), '/').'/',
        'paiements' => rtrim((string) config('app.frontend_url'), '/').'/paiements',
    ], 503)->header('Retry-After', '3600');
});
