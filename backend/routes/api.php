<?php

use App\Http\Controllers\MissionController;
use App\Http\Controllers\MissionMessageController;
use Illuminate\Support\Facades\Route;

// « auth » passera à « auth:sanctum » quand Sanctum de Wasfade sera sur main.
Route::middleware('auth')->group(function () {
    Route::get('/missions', [MissionController::class, 'index']);
    Route::get('/missions/{mission}', [MissionController::class, 'show']);

    Route::post('/missions/{mission}/note', [MissionController::class, 'note']);

    Route::get('/missions/{mission}/messages', [MissionMessageController::class, 'index']);
    Route::post('/missions/{mission}/messages', [MissionMessageController::class, 'store']);
});
