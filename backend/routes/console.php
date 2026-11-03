<?php

use App\Exploitation\Battements;
use App\Jobs\FacturationQuotidienne;
use App\Jobs\NettoyerAnalyses;
use App\Jobs\NettoyerCopies;
use App\Jobs\NettoyerImagesDocker;
use App\Jobs\RapprochementQuotidien;
use App\Jobs\RapprocherPaiementsEnAttente;
use App\Jobs\RecalculerProfils;
use App\Jobs\ReversementsMensuels;
use App\Jobs\SauvegarderCopie;
use App\Jobs\SonderMachine;
use App\Jobs\SurveillerExploitation;
use App\Jobs\SurveillerProcesseur;
use App\Jobs\VerifierReversements;
use App\Models\AnalyseIa;
use App\Models\CleIdempotence;
use App\Models\CodeConnexion;
use App\Models\Deploiement;
use App\Moteur\SauvegardeVolumes;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Copies de test expirées et dossiers orphelins, sur le moteur (seul à parler à Docker).
Schedule::job(new NettoyerCopies)->everyFiveMinutes()->withoutOverlapping()->name('moteur-nettoyage');

// Santé des serveurs du moteur (joignable, mémoire, version), sondée par le moteur.
Schedule::job(new SonderMachine)->everyFiveMinutes()->withoutOverlapping()->name('machines-sonde');
// Politique d'abus : une copie au processeur saturé une heure d'affilée est suspendue (minage).
Schedule::job(new SurveillerProcesseur)->everyFiveMinutes()->withoutOverlapping()->name('abus-processeur');

// Sécurité avant publication : restes d'analyses interrompues chaque heure ; chaque semaine, toutes
// les applis sont réanalysées (la base des failles grandit) ; chaque nuit, celles jamais analysées.
Schedule::job(new NettoyerAnalyses)->hourly()->name('analyses-nettoyage');
Schedule::command('securite:analyser --toutes')->weeklyOn(0, '04:00')->name('securite-hebdomadaire');
Schedule::command('securite:analyser')->dailyAt('04:30')->name('securite-oubliees');

// Souk AI : une analyse restée « en cours » (travail perdu) devient « indisponible ».
Schedule::call(fn () => AnalyseIa::query()
    ->where('statut', 'en_cours')
    ->where('created_at', '<', now()->subMinutes(AnalyseIa::MINUTES_AVANT_ABANDON))
    ->update(['statut' => 'echec', 'erreur' => 'abandonnee', 'termine_le' => now()]))->everyFifteenMinutes()->name('souk-ai-abandons');

// Sauvegarde quotidienne à chaud de chaque copie client en ligne (base intégrée par pg_dump), sur le moteur.
Schedule::call(fn () => Deploiement::query()->where('type', 'client')->where('etat', 'en_ligne')->pluck('id')
    ->each(fn (int $id) => SauvegarderCopie::dispatch($id, 'quotidienne')))->dailyAt('03:30')->name('sauvegardes-quotidiennes');

// Sauvegardes des copies : gardées 7 jours, la dernière de chaque copie toujours.
Schedule::call(fn (SauvegardeVolumes $sauvegardes) => $sauvegardes->nettoyer((int) config('moteur.sauvegardes_jours', 7)))->dailyAt('05:00')->name('sauvegardes-nettoyage');

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

// Codes de connexion expirés (10 minutes) : hashes et empreintes HMAC sans usage passé l'expiration.
Schedule::call(fn () => CodeConnexion::query()->where('expire_le', '<=', now())->delete())->daily()->name('codes-connexion');

// Contrôle de santé du planificateur (voir docker/sante.php).
Schedule::call(fn (Battements $battements) => $battements->battre('planificateur'))->everyMinute()->name('battement');

// Exploitation : file bloquée, disque plein, machine injoignable → e-mail à l'équipe.
Schedule::job(new SurveillerExploitation)->hourly()->withoutOverlapping()->name('exploitation-surveillance');

// Images et caches Docker inutilisés depuis 7 jours, sur le démon local (moteur).
Schedule::job(new NettoyerImagesDocker)->weeklyOn(0, '05:30')->withoutOverlapping()->name('images-nettoyage');
