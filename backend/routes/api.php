<?php

use App\Http\Controllers\AppliController;
use App\Http\Controllers\CanalController;
use App\Http\Controllers\CompteController;
use App\Http\Controllers\DeploiementController;
use App\Http\Controllers\DiscussionController;
use App\Http\Controllers\EtiquetteController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MissionController;
use App\Http\Controllers\MissionMessageController;
use Illuminate\Support\Facades\Route;

Route::get('/apps', [AppliController::class, 'index']);
Route::get('/apps/{appli}', [AppliController::class, 'show']);
Route::get('/categories', [AppliController::class, 'categories']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', [CompteController::class, 'show']);
    Route::patch('/me', [CompteController::class, 'update']);

    Route::post('/apps/verification', [AppliController::class, 'verification']);
    Route::post('/apps', [AppliController::class, 'store']);
    Route::post('/apps/{appli}/deployments', [DeploiementController::class, 'store'])
        ->middleware('throttle:deploiements');
    Route::get('/deployments', [DeploiementController::class, 'index']);
    Route::get('/deployments/{deploiement}', [DeploiementController::class, 'show']);
    Route::delete('/deployments/{deploiement}', [DeploiementController::class, 'destroy']);

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

Route::get('/discussions/{discussion}/messages', [MessageController::class, 'index']);
Route::post('/discussions/{discussion}/messages', [MessageController::class, 'store'])->middleware('auth:sanctum');

Route::patch('/messages/{message}', [MessageController::class, 'update'])->middleware('auth:sanctum');
Route::delete('/messages/{message}', [MessageController::class, 'destroy'])->middleware('auth:sanctum');

Route::get('/canaux', [CanalController::class, 'index']);
Route::post('/canaux', [CanalController::class, 'store'])->middleware('auth:sanctum');

Route::get('/etiquettes', [EtiquetteController::class, 'index']);
