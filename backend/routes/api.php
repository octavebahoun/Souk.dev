<?php

use App\Http\Controllers\MissionController;
use Illuminate\Support\Facades\Route;

// « auth » passera à « auth:sanctum » quand Sanctum de Wasfade sera sur main.
Route::middleware('auth')->group(function () {
    Route::get('/missions', [MissionController::class, 'index']);
    Route::get('/missions/{mission}', [MissionController::class, 'show']);
});