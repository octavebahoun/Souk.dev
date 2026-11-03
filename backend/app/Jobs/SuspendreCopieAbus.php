<?php

namespace App\Jobs;

use App\Models\Deploiement;
use App\Moteur\MoteurDeploiement;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Abus confirmé par l'équipe (minage, hameçonnage…) : la copie est suspendue sur le moteur
 * (seul à parler à Docker). Conteneurs arrêtés, volumes gardés, page neutre sur le sous-domaine
 * (rien sur le motif : les visiteurs de la copie n'ont pas à le savoir).
 */
class SuspendreCopieAbus implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public int $deploiementId)
    {
        $this->onQueue('moteur');
    }

    public function handle(MoteurDeploiement $moteur): void
    {
        $deploiement = Deploiement::query()->find($this->deploiementId);

        // Suspension levée entre-temps, copie arrêtée, ou jamais démarrée : rien à suspendre.
        if ($deploiement === null || $deploiement->suspension_admin_motif === null
            || ! in_array($deploiement->etat, ['en_ligne', 'suspendu'], true) || $deploiement->copie === null) {
            return;
        }

        $moteur->suspendre((string) $deploiement->repertoire, $deploiement->copie, $deploiement->cibleMachine());
        $deploiement->changerEtat('suspendu');
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('moteur: suspension pour abus impossible', ['deploiement' => $this->deploiementId, 'erreur' => $exception->getMessage()]);
    }
}
