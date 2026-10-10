<?php

use App\Http\Controllers\GithubController;
use App\Http\Controllers\LienMagiqueController;
use App\Http\Controllers\LogoutController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/github', [GithubController::class, 'redirect'])->middleware('throttle:60,1');
Route::get('/auth/github/callback', [GithubController::class, 'callback']);
Route::post('/auth/lien-magique', [LienMagiqueController::class, 'demander'])->middleware('throttle:60,1');
Route::get('/auth/lien-magique/{token}', [LienMagiqueController::class, 'utiliser'])
    ->where('token', '[A-Za-z0-9]+');
Route::post('/logout', [LogoutController::class, 'destroy']);
