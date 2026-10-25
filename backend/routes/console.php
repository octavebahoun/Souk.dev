<?php

use App\Exploitation\Battements;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Copies de test expirées et dossiers orphelins. En production : cron « * * * * * php artisan schedule:run ».
Schedule::command('moteur:nettoyer')->everyFiveMinutes()->withoutOverlapping();

// Contrôle de santé du planificateur (voir sante:processus).
Schedule::call(fn (Battements $battements) => $battements->battre('planificateur'))->everyMinute()->name('battement');
