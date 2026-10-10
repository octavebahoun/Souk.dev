<?php

use App\Http\Controllers\CanalController;
use App\Http\Controllers\CompteController;
use App\Http\Controllers\DiscussionController;
use App\Http\Controllers\EtiquetteController;
use App\Http\Controllers\MessageController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', [CompteController::class, 'show']);
    Route::patch('/me', [CompteController::class, 'update']);
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
