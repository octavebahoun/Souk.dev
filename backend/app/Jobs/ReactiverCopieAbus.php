<?php

namespace App\Jobs;

use App\Models\Deploiement;
use App\Moteur\MoteurDeploiement;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Suspension pour abus levée : les mêmes conteneurs repartent avec leurs données, le sous-domaine
 * revient vers le service web une fois celui-ci sondé. Sur le moteur, seul à parler à Docker.
 */
class ReactiverCopieAbus implements ShouldQueue
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

        // Re-suspendue entre-temps, arrêtée, ou suspendue pour impayé : on ne la relance pas.
        if ($deploiement === null || $deploiement->suspension_admin_motif !== null
            || $deploiement->etat !== 'suspendu' || $deploiement->copie === null
            || in_array($deploiement->abonnement?->statut, ['suspendu', 'resilie'], true)) {
            return;
        }

        $url = $deploiement->url ?? str_replace('{copie}', (string) $deploiement->copie, (string) config('moteur.url_copie'));

        if (! $deploiement->changerEtat('demarrage')) {
            return;
        }

        $moteur->reprendre((string) $deploiement->repertoire, $deploiement->copie, $deploiement->cibleMachine());
        $deploiement->changerEtat('en_ligne', url: $url);
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('moteur: réactivation après abus impossible', ['deploiement' => $this->deploiementId, 'erreur' => $exception->getMessage()]);
    }
}
