<?php

use App\Exploitation\Battements;
use App\Jobs\FacturationQuotidienne;
use App\Jobs\NettoyerAnalyses;
use App\Jobs\NettoyerCopies;
use App\Jobs\RapprochementQuotidien;
use App\Jobs\RapprocherPaiementsEnAttente;
use App\Jobs\RecalculerProfils;
use App\Jobs\ReversementsMensuels;
use App\Jobs\VerifierReversements;
use App\Models\CleIdempotence;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Copies de test expirées et dossiers orphelins, sur le moteur (seul à parler à Docker).
Schedule::job(new NettoyerCopies)->everyFiveMinutes()->withoutOverlapping()->name('moteur-nettoyage');

// Sécurité avant publication : restes d'analyses interrompues chaque heure ; chaque semaine, toutes
// les applis sont réanalysées (la base des failles grandit) ; chaque nuit, celles jamais analysées.
Schedule::job(new NettoyerAnalyses)->hourly()->name('analyses-nettoyage');
Schedule::command('securite:analyser --toutes')->weeklyOn(0, '04:00')->name('securite-hebdomadaire');
Schedule::command('securite:analyser')->dailyAt('04:30')->name('securite-oubliees');

// Souk Score : la fenêtre d'activité de 30 jours avance chaque nuit.
Schedule::job(new RecalculerProfils)->dailyAt('03:00')->name('profils');

// Paiement : filet des webhooks (sessions et payouts encore ouverts), toutes les 15 minutes.
Schedule::job(new RapprocherPaiementsEnAttente)->everyFifteenMinutes()->withoutOverlapping()->name('paiements-en-attente');
Schedule::job(new VerifierReversements)->everyFifteenMinutes()->withoutOverlapping()->name('reversements-en-attente');

// Facturation à date anniversaire : rappels, relances, suspensions et arrêts (7 h au Bénin).
Schedule::job(new FacturationQuotidienne)->dailyAt('06:00')->withoutOverlapping()->name('facturation');

// Rapprochement de la veille avec GET /transactions/ de SasPay.
Schedule::job(new RapprochementQuotidien)->dailyAt('02:30')->name('rapprochement');

// Reversements mensuels aux devs, le 1er du mois.
Schedule::job(new ReversementsMensuels)->monthlyOn((int) config('facturation.jour_reversement_mensuel', 1), '08:00')->name('reversements-mensuels');

// Clés d'idempotence expirées (24 heures).
Schedule::call(fn () => CleIdempotence::query()->where('expire_le', '<=', now())->delete())->daily()->name('idempotence');

// Contrôle de santé du planificateur (voir docker/sante.php).
Schedule::call(fn (Battements $battements) => $battements->battre('planificateur'))->everyMinute()->name('battement');
