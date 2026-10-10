<?php

use App\Http\Controllers\AppliController;
use App\Http\Controllers\DeploiementController;
use App\Http\Controllers\MissionController;
use App\Http\Controllers\MissionMessageController;
use Illuminate\Support\Facades\Route;

// « auth » passera à « auth:sanctum » quand Sanctum de Wasfade sera sur main.
Route::middleware('auth')->group(function () {
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
