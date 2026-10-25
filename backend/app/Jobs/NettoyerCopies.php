<?php

namespace App\Jobs;

use App\Moteur\NettoyageCopies;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Lancé toutes les cinq minutes par le planificateur, exécuté par le moteur
 * (le planificateur n'a pas accès à Docker).
 */
class NettoyerCopies implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue('moteur');
    }

    public function handle(NettoyageCopies $nettoyage): void
    {
        $nettoyage->executer();
    }
}
