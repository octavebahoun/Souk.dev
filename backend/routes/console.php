<?php

use App\Exploitation\Battements;
use App\Jobs\NettoyerCopies;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Copies de test expirées et dossiers orphelins, sur le moteur (seul à parler à Docker).
Schedule::job(new NettoyerCopies)->everyFiveMinutes()->withoutOverlapping()->name('moteur-nettoyage');

// Contrôle de santé du planificateur (voir docker/sante.php).
Schedule::call(fn (Battements $battements) => $battements->battre('planificateur'))->everyMinute()->name('battement');
