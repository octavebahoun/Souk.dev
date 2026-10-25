<?php

namespace App\Jobs;

use App\Models\Deploiement;
use App\Moteur\MoteurDeploiement;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Impayé après les 7 jours de grâce : la copie est suspendue sur le moteur (seul à parler à
 * Docker). Conteneurs arrêtés, volumes gardés, page « copie suspendue » sur le sous-domaine.
 */
class SuspendreCopie implements ShouldQueue
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
        $deploiement = Deploiement::query()->with('abonnement')->find($this->deploiementId);

        // Payée entre-temps, arrêtée, ou jamais démarrée : rien à suspendre.
        if ($deploiement === null || $deploiement->abonnement?->statut !== 'suspendu'
            || ! in_array($deploiement->etat, ['en_ligne', 'suspendu'], true) || $deploiement->copie === null) {
            return;
        }

        $moteur->suspendre((string) $deploiement->repertoire, $deploiement->copie);
        $deploiement->changerEtat('suspendu');
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('moteur: suspension impossible', ['deploiement' => $this->deploiementId, 'erreur' => $exception->getMessage()]);
    }
}
