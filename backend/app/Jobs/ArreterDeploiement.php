<?php

namespace App\Jobs;

use App\Models\Deploiement;
use App\Moteur\NettoyeurCopie;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Arrêt d'une copie sur le moteur, seul à parler à Docker : docker compose down,
 * puis suppression du dossier. Deux échecs : echec, retenté par NettoyerCopies.
 */
class ArreterDeploiement implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $deploiementId)
    {
        $this->onQueue('moteur');
    }

    public function handle(NettoyeurCopie $nettoyeur): void
    {
        $deploiement = Deploiement::query()->find($this->deploiementId);

        if ($deploiement === null) {
            return;
        }

        $nettoyeur->arreter($deploiement);
    }
}
