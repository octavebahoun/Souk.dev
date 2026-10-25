<?php

use App\Http\Controllers\AppliController;
use App\Http\Controllers\CanalController;
use App\Http\Controllers\CompteController;
use App\Http\Controllers\CopieTestController;
use App\Http\Controllers\CorrectifController;
use App\Http\Controllers\DeploiementController;
use App\Http\Controllers\DiscussionController;
use App\Http\Controllers\EtiquetteController;
use App\Http\Controllers\EvenementController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MissionController;
use App\Http\Controllers\MissionMessageController;
use App\Http\Controllers\TechnoController;
use Illuminate\Support\Facades\Route;

Route::get('/devs/{username}', [CompteController::class, 'dev'])
    ->where('username', '[A-Za-z0-9][A-Za-z0-9-]{0,38}');
Route::get('/devs', [CompteController::class, 'index']);

Route::get('/technos', [TechnoController::class, 'index']);

Route::get('/apps', [AppliController::class, 'index']);
Route::get('/apps/{appli}', [AppliController::class, 'show']);
Route::get('/categories', [AppliController::class, 'categories']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', [CompteController::class, 'show']);
    Route::get('/me/tableau-de-bord', [CompteController::class, 'tableau']);
    Route::patch('/me', [CompteController::class, 'update']);

    Route::post('/apps/verification', [AppliController::class, 'verification']);
    Route::post('/apps', [AppliController::class, 'store']);
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
    Route::post('/apps/{appli}/missions', [MissionController::class, 'pourAppli'])
        ->middleware('throttle:missions');

    Route::get('/missions', [MissionController::class, 'index']);
    Route::get('/missions/{mission}', [MissionController::class, 'show']);
    Route::post('/missions/{mission}/note', [MissionController::class, 'note']);
    Route::get('/missions/{mission}/messages', [MissionMessageController::class, 'index']);
    Route::post('/missions/{mission}/messages', [MissionMessageController::class, 'store']);
});

Route::get('/discussions', [DiscussionController::class, 'index']);
Route::post('/discussions', [DiscussionController::class, 'store'])
    ->middleware(['auth:sanctum', 'throttle:discussions']);

Route::get('/discussions/{discussion}', [DiscussionController::class, 'show']);
Route::patch('/discussions/{discussion}', [DiscussionController::class, 'update'])->middleware('auth:sanctum');
Route::delete('/discussions/{discussion}', [DiscussionController::class, 'destroy'])->middleware('auth:sanctum');

// Copie de test : seulement à la demande de l'auteur, dans la limite des 3 déploiements par heure.
Route::post('/discussions/{discussion}/copie-test', [CopieTestController::class, 'store'])
    ->middleware(['auth:sanctum', 'throttle:deploiements']);

Route::get('/discussions/{discussion}/messages', [MessageController::class, 'index']);
Route::post('/discussions/{discussion}/messages', [MessageController::class, 'store'])->middleware('auth:sanctum');

Route::patch('/messages/{message}', [MessageController::class, 'update'])->middleware('auth:sanctum');
Route::delete('/messages/{message}', [MessageController::class, 'destroy'])->middleware('auth:sanctum');

Route::get('/canaux', [CanalController::class, 'index']);
Route::post('/canaux', [CanalController::class, 'store'])->middleware('auth:sanctum');

Route::get('/etiquettes', [EtiquetteController::class, 'index']);

Route::get('/discussions/{discussion}/correctifs', [CorrectifController::class, 'index']);
Route::post('/discussions/{discussion}/correctifs', [CorrectifController::class, 'store'])
    ->middleware(['auth:sanctum', 'throttle:correctifs']);
Route::post('/correctifs/{correctif}/accepter', [CorrectifController::class, 'accepter'])
    ->middleware('auth:sanctum');

Route::get('/events', [EvenementController::class, 'index']);
Route::post('/events', [EvenementController::class, 'store'])->middleware('auth:sanctum');
Route::get('/events/{evenement}', [EvenementController::class, 'show']);
Route::patch('/events/{evenement}', [EvenementController::class, 'update'])->middleware('auth:sanctum');
Route::delete('/events/{evenement}', [EvenementController::class, 'destroy'])->middleware('auth:sanctum');
Route::post('/events/{evenement}/inscription', [EvenementController::class, 'inscrire'])->middleware('auth:sanctum');
Route::delete('/events/{evenement}/inscription', [EvenementController::class, 'desinscrire'])->middleware('auth:sanctum');
Route::get('/events/{evenement}/participants', [EvenementController::class, 'participants']);
Route::get('/events/{evenement}/avis', [EvenementController::class, 'avis']);
Route::post('/events/{evenement}/avis', [EvenementController::class, 'noter'])->middleware('auth:sanctum');
