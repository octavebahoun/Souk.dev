<?php

namespace App\Jobs;

use App\Models\Deploiement;
use App\Moteur\MoteurDeploiement;
use App\Paiement\Facturation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Paiement reçu pour une copie suspendue : elle repart avec ses données, sur le moteur. */
class ReactiverCopie implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public int $deploiementId)
    {
        $this->onQueue('moteur');
    }

    public function handle(MoteurDeploiement $moteur): void
    {
        $deploiement = Deploiement::query()->with('abonnement')->find($this->deploiementId);

        // « demarrage » : une tentative précédente de ce job a échoué en route, on reprend.
        $reprise = $deploiement?->etat === 'demarrage' && $this->attempts() > 1;

        if ($deploiement === null || ($deploiement->etat !== 'suspendu' && ! $reprise) || $deploiement->copie === null
            || ! in_array($deploiement->abonnement?->statut, ['actif', 'en_retard'], true)) {
            return;
        }

        $url = $deploiement->url;

        if (! $reprise && ! $deploiement->changerEtat('demarrage')) {
            return;
        }

        $moteur->reprendre((string) $deploiement->repertoire, $deploiement->copie);
        $deploiement->changerEtat('en_ligne', url: $url ?? str_replace('{copie}', $deploiement->copie, (string) config('moteur.url_copie')));
    }

    /** Le client a payé : un échec de reprise est montré et signalé, jamais passé sous silence. */
    public function failed(Throwable $exception): void
    {
        Log::critical('moteur: réactivation impossible', ['deploiement' => $this->deploiementId, 'erreur' => $exception->getMessage()]);

        $deploiement = Deploiement::query()->find($this->deploiementId);

        if ($deploiement === null) {
            return;
        }

        $deploiement->changerEtat('echec', erreur: 'La copie n’a pas pu repartir après le paiement. L’équipe est prévenue et revient vers vous.');
        app(Facturation::class)->signalerIncident($deploiement, 'Réactivation impossible après paiement ('.$exception->getMessage().').');
    }
}
