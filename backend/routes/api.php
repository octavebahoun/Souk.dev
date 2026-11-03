<?php

use App\Http\Controllers\AdminComptesController;
use App\Http\Controllers\AdminCopiesController;
use App\Http\Controllers\AdminFinancesController;
use App\Http\Controllers\AnalyseIaController;
use App\Http\Controllers\AppliController;
use App\Http\Controllers\AvisAppliController;
use App\Http\Controllers\CanalController;
use App\Http\Controllers\CompteController;
use App\Http\Controllers\ConnexionUtilisateurFinalController;
use App\Http\Controllers\CoordonneesReversementController;
use App\Http\Controllers\CopieTestController;
use App\Http\Controllers\CorrectifController;
use App\Http\Controllers\DeploiementController;
use App\Http\Controllers\DiscussionController;
use App\Http\Controllers\EtiquetteController;
use App\Http\Controllers\EvenementController;
use App\Http\Controllers\FactureController;
use App\Http\Controllers\MachineController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MissionController;
use App\Http\Controllers\MissionMessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReseauMobileMoneyController;
use App\Http\Controllers\RevenusController;
use App\Http\Controllers\SauvegardeController;
use App\Http\Controllers\SignalementController;
use App\Http\Controllers\TechnoController;
use App\Http\Controllers\VersionController;
use App\Http\Controllers\WebhookSasPayController;
use Illuminate\Support\Facades\Route;

Route::get('/devs/{username}', [CompteController::class, 'dev'])
    ->where('username', '[A-Za-z0-9][A-Za-z0-9-]{0,38}');
Route::get('/devs', [CompteController::class, 'index']);

Route::get('/technos', [TechnoController::class, 'index']);
Route::get('/reseaux-mobile-money', [ReseauMobileMoneyController::class, 'index']);

Route::get('/apps', [AppliController::class, 'index']);
Route::get('/apps/{appli}', [AppliController::class, 'show']);
Route::get('/apps/{appli}/avis', [AvisAppliController::class, 'index']);
Route::get('/apps/{appli}/versions', [VersionController::class, 'index']);

// Backend intégré : connexion des utilisateurs d'une appli déployée (code par SMS ou e-mail).
Route::post('/copies/{cle}/auth/code', [ConnexionUtilisateurFinalController::class, 'code']);
Route::post('/copies/{cle}/auth/session', [ConnexionUtilisateurFinalController::class, 'session']);
Route::get('/categories', [AppliController::class, 'categories']);

Route::middleware(['auth:sanctum', 'compte.actif'])->group(function (): void {
    Route::get('/me', [CompteController::class, 'show']);
    Route::get('/me/tableau-de-bord', [CompteController::class, 'tableau']);
    Route::patch('/me', [CompteController::class, 'update']);

    Route::post('/apps/verification', [AppliController::class, 'verification'])->middleware('throttle:depots');
    Route::post('/apps', [AppliController::class, 'store'])->middleware('throttle:depots');
    Route::patch('/apps/{appli}', [AppliController::class, 'update']);
    Route::delete('/apps/{appli}', [AppliController::class, 'destroy']);
    Route::post('/apps/{appli}/captures', [AppliController::class, 'captures']);
    Route::post('/apps/{appli}/deployments', [DeploiementController::class, 'store'])
        ->middleware('throttle:deploiements');
    Route::get('/deployments', [DeploiementController::class, 'index']);
    Route::get('/deployments/{deploiement}', [DeploiementController::class, 'show']);
    Route::delete('/deployments/{deploiement}', [DeploiementController::class, 'destroy']);

    Route::post('/devs/{username}/missions', [MissionController::class, 'pourDev'])
        ->middleware('throttle:missions');
    // Relance de l'analyse de sécurité par l'auteur (Trivy, sur le moteur).
    Route::post('/apps/{appli}/securite', [AppliController::class, 'securite'])
        ->middleware('throttle:securite');
    Route::post('/apps/{appli}/missions', [MissionController::class, 'pourAppli'])
        ->middleware('throttle:missions');
    Route::post('/apps/{appli}/avis', [AvisAppliController::class, 'store'])->middleware('throttle:avis');
    // Mises à jour poussées : l'auteur publie une version, le client l'applique à sa copie.
    Route::post('/apps/{appli}/versions', [VersionController::class, 'store'])->middleware('throttle:versions');
    Route::post('/deployments/{deploiement}/mise-a-jour', [DeploiementController::class, 'miseAJour'])->middleware('throttle:versions');
    // Sauvegardes d'une copie : liste, sauvegarde à la demande, export.
    Route::get('/deployments/{deploiement}/sauvegardes', [SauvegardeController::class, 'index']);
    Route::post('/deployments/{deploiement}/sauvegardes', [SauvegardeController::class, 'store'])->middleware('throttle:sauvegardes');
    Route::get('/deployments/{deploiement}/sauvegardes/{sauvegarde}', [SauvegardeController::class, 'show'])->where('sauvegarde', '\d{8}-\d{6}');
    Route::patch('/apps/{appli}/avis', [AvisAppliController::class, 'update'])->middleware('throttle:avis');

    // Paiements : factures du client, sessions SasPay, vérification au retour.
    Route::get('/factures', [FactureController::class, 'index']);
    Route::get('/factures/{facture}', [FactureController::class, 'show']);
    // L'autorisation passe avant la clé : une facture d'un autre client répond 404, jamais 428.
    Route::post('/factures/{facture}/paiement', [FactureController::class, 'payer'])
        ->middleware(['throttle:paiements', 'can:payer,facture', 'idempotence:obligatoire']);
    Route::post('/factures/{facture}/verification', [FactureController::class, 'verifier'])
        ->middleware('throttle:verifications');

    // Revenus du dev : solde réel, mouvements, reversements, coordonnées Mobile Money.
    Route::get('/me/revenus', [RevenusController::class, 'resume']);
    Route::get('/me/revenus/mouvements', [RevenusController::class, 'mouvements']);
    Route::get('/me/revenus/reversements', [RevenusController::class, 'reversements']);
    Route::post('/me/revenus/reversements', [RevenusController::class, 'demander'])
        ->middleware(['throttle:reversements', 'idempotence:obligatoire']);
    // Centre de notifications et préférences par type.
    Route::get('/me/notifications', [NotificationController::class, 'index']);
    Route::post('/me/notifications/lecture', [NotificationController::class, 'toutLire']);
    Route::post('/me/notifications/{notification}/lecture', [NotificationController::class, 'lire'])->whereUuid('notification');
    Route::get('/me/preferences-notifications', [NotificationController::class, 'preferences']);
    Route::put('/me/preferences-notifications', [NotificationController::class, 'modifierPreferences']);

    Route::get('/me/coordonnees-reversement', [CoordonneesReversementController::class, 'show']);
    Route::put('/me/coordonnees-reversement', [CoordonneesReversementController::class, 'update'])
        ->middleware('throttle:coordonnees');

    // Signaler un contenu ou un comportement à l'équipe.
    Route::post('/signalements', [SignalementController::class, 'store'])->middleware('throttle:signalements');

    // Administration : finances (grand livre), avoirs, reversements, rapprochements.
    Route::prefix('admin')->middleware('administrateur')->group(function (): void {
        Route::get('/finances', [AdminFinancesController::class, 'finances']);
        Route::get('/factures', [AdminFinancesController::class, 'factures']);
        Route::post('/factures/{facture}/avoir', [AdminFinancesController::class, 'avoir'])->middleware('idempotence:obligatoire');
        Route::post('/paiements/{paiement}/remboursement', [AdminFinancesController::class, 'rembourserTropPercu'])->middleware('idempotence:obligatoire');
        Route::get('/reversements', [AdminFinancesController::class, 'reversements']);
        Route::get('/rapprochements', [AdminFinancesController::class, 'rapprochements']);

        // Modération : signalements, comptes, copies, canaux.
        Route::get('/signalements', [SignalementController::class, 'index']);
        Route::get('/signalements/{signalement}', [SignalementController::class, 'show']);
        Route::post('/signalements/{signalement}/traiter', [SignalementController::class, 'traiter']);
        Route::post('/signalements/{signalement}/rejeter', [SignalementController::class, 'rejeter']);

        Route::get('/comptes', [AdminComptesController::class, 'index']);
        Route::post('/comptes/{compte}/suspension', [AdminComptesController::class, 'suspendre']);
        Route::delete('/comptes/{compte}/suspension', [AdminComptesController::class, 'leverSuspension']);

        Route::get('/deployments', [AdminCopiesController::class, 'index']);
        Route::post('/deployments/{deploiement}/suspension', [AdminCopiesController::class, 'suspendre']);
        Route::delete('/deployments/{deploiement}/suspension', [AdminCopiesController::class, 'reactiver']);

        Route::patch('/canaux/{canal:slug}', [CanalController::class, 'update']);
        Route::delete('/canaux/{canal:slug}', [CanalController::class, 'destroy']);

        // Serveurs du moteur : liste, enregistrement, maintenance, santé.
        Route::get('/machines', [MachineController::class, 'index']);
        Route::post('/machines', [MachineController::class, 'store']);
        Route::get('/machines/{machine}', [MachineController::class, 'show']);
        Route::patch('/machines/{machine}', [MachineController::class, 'update']);
        Route::delete('/machines/{machine}', [MachineController::class, 'destroy']);
        Route::post('/machines/{machine}/sonde', [MachineController::class, 'sonder']);
    });

    Route::get('/missions', [MissionController::class, 'index']);
    Route::get('/missions/{mission}', [MissionController::class, 'show']);
    Route::post('/missions/{mission}/note', [MissionController::class, 'note']);
    Route::get('/missions/{mission}/messages', [MissionMessageController::class, 'index']);
    Route::post('/missions/{mission}/messages', [MissionMessageController::class, 'store']);
});

Route::get('/discussions', [DiscussionController::class, 'index']);
Route::post('/discussions', [DiscussionController::class, 'store'])
    ->middleware(['auth:sanctum', 'compte.actif', 'throttle:discussions']);

Route::get('/discussions/{discussion}', [DiscussionController::class, 'show']);
Route::patch('/discussions/{discussion}', [DiscussionController::class, 'update'])->middleware(['auth:sanctum', 'compte.actif']);
Route::delete('/discussions/{discussion}', [DiscussionController::class, 'destroy'])->middleware(['auth:sanctum', 'compte.actif']);

// Copie de test : seulement à la demande de l'auteur, dans la limite des 3 déploiements par heure.
Route::post('/discussions/{discussion}/copie-test', [CopieTestController::class, 'store'])
    ->middleware(['auth:sanctum', 'compte.actif', 'throttle:deploiements']);

Route::get('/discussions/{discussion}/messages', [MessageController::class, 'index']);
Route::post('/discussions/{discussion}/messages', [MessageController::class, 'store'])->middleware(['auth:sanctum', 'compte.actif']);

Route::patch('/messages/{message}', [MessageController::class, 'update'])->middleware(['auth:sanctum', 'compte.actif']);
Route::delete('/messages/{message}', [MessageController::class, 'destroy'])->middleware(['auth:sanctum', 'compte.actif']);

Route::get('/canaux', [CanalController::class, 'index']);
Route::post('/canaux', [CanalController::class, 'store'])->middleware(['auth:sanctum', 'compte.actif', 'throttle:canaux']);

Route::get('/etiquettes', [EtiquetteController::class, 'index']);

// Souk AI : l'auteur d'un bug demande une analyse (consentement, 3 par 24 heures, résultat par Reverb).
Route::post('/discussions/{discussion}/analyse-ia', [AnalyseIaController::class, 'store'])->middleware(['auth:sanctum', 'compte.actif']);

Route::get('/discussions/{discussion}/correctifs', [CorrectifController::class, 'index']);
Route::post('/discussions/{discussion}/correctifs', [CorrectifController::class, 'store'])
    ->middleware(['auth:sanctum', 'compte.actif', 'throttle:correctifs']);
Route::post('/correctifs/{correctif}/accepter', [CorrectifController::class, 'accepter'])
    ->middleware(['auth:sanctum', 'compte.actif']);
Route::post('/correctifs/{correctif}/deploiement', [CorrectifController::class, 'deployer'])
    ->middleware(['auth:sanctum', 'compte.actif', 'throttle:deploiements']);

Route::get('/events', [EvenementController::class, 'index']);
Route::post('/events', [EvenementController::class, 'store'])->middleware(['auth:sanctum', 'compte.actif']);
Route::get('/events/{evenement}', [EvenementController::class, 'show']);
Route::patch('/events/{evenement}', [EvenementController::class, 'update'])->middleware(['auth:sanctum', 'compte.actif']);
Route::delete('/events/{evenement}', [EvenementController::class, 'destroy'])->middleware(['auth:sanctum', 'compte.actif']);
Route::post('/events/{evenement}/inscription', [EvenementController::class, 'inscrire'])->middleware(['auth:sanctum', 'compte.actif']);
Route::delete('/events/{evenement}/inscription', [EvenementController::class, 'desinscrire'])->middleware(['auth:sanctum', 'compte.actif']);
Route::get('/events/{evenement}/participants', [EvenementController::class, 'participants']);
Route::get('/events/{evenement}/avis', [EvenementController::class, 'avis']);
Route::post('/events/{evenement}/avis', [EvenementController::class, 'noter'])->middleware(['auth:sanctum', 'compte.actif']);

// Webhooks SasPay : pas de session, la signature HMAC fait foi (voir WebhookSasPayController).
Route::post('/webhooks/saspay', [WebhookSasPayController::class, 'recevoir'])
    ->withoutMiddleware('throttle:api')
    ->middleware('throttle:webhooks');
